<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\OwnableInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;

class PermissionChecker
{
    private const REQUIRED_PRIVILEGE_MAP = [
        Subscriber::class => PrivilegeFlag::Subscribers,
        SubscriberList::class => PrivilegeFlag::Subscribers,
        Message::class => PrivilegeFlag::Campaigns,
    ];

    private const OWNERSHIP_MAP = [
        Subscriber::class => SubscriberList::class,
        Message::class => SubscriberList::class
    ];

    public function canManage(Administrator $actor, DomainModel $resource): bool
    {
        if ($actor->isSuperUser()) {
            return true;
        }

        $required = $this->resolveRequiredPrivilegeForClass(get_class($resource));
        if ($required !== null && !$actor->getPrivileges()->has($required)) {
            return false;
        }

        if ($resource instanceof OwnableInterface) {
            return $actor->owns($resource);
        }

        $notRestricted = true;
        foreach (self::OWNERSHIP_MAP as $resourceClass => $relatedClass) {
            if ($resource instanceof $resourceClass) {
                $related = $this->resolveRelatedEntity($resource, $relatedClass);
                $notRestricted = $this->checkRelatedResources($related, $actor);
            }
        }

        return $notRestricted;
    }

    /** @param class-string $resourceClass */
    public function canCreate(Administrator $actor, string $resourceClass): bool
    {
        if ($actor->isSuperUser()) {
            return true;
        }

        $required = $this->resolveRequiredPrivilegeForClass($resourceClass);

        return $required === null || $actor->getPrivileges()->has($required);
    }

    public function canList(Administrator $actor, string $resourceClass): bool
    {
        return $this->canCreate($actor, $resourceClass);
    }

    public function canView(Administrator $actor, DomainModel $resource): bool
    {
        return $this->canManage($actor, $resource);
    }

    public function canEdit(Administrator $actor, DomainModel $resource): bool
    {
        return $this->canManage($actor, $resource);
    }

    public function canDelete(Administrator $actor, DomainModel $resource): bool
    {
        return $this->canManage($actor, $resource);
    }

    /** @param class-string $resourceClass */
    private function resolveRequiredPrivilegeForClass(string $resourceClass): ?PrivilegeFlag
    {
        foreach (self::REQUIRED_PRIVILEGE_MAP as $class => $flag) {
            if (is_a($resourceClass, $class, true)) {
                return $flag;
            }
        }

        return null;
    }

    /** @return OwnableInterface[] */
    private function resolveRelatedEntity(DomainModel $resource, string $relatedClass): array
    {
        if ($resource instanceof Subscriber && $relatedClass === SubscriberList::class) {
            return $resource->getSubscribedLists()->toArray();
        }

        if ($resource instanceof Message && $relatedClass === SubscriberList::class) {
            return $resource->getListMessages()->map(fn(ListMessage $lm) => $lm->getList())->toArray();
        }

        return [];
    }

    private function checkRelatedResources(array $related, Administrator $actor): bool
    {
        foreach ($related as $relatedResource) {
            if ($actor->owns($relatedResource)) {
                return true;
            }
        }

        return false;
    }
}

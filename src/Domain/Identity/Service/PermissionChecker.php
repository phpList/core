<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Common\Model\Ability;
use PhpList\Core\Domain\Common\Model\Interfaces\OwnableInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;

class PermissionChecker
{
    public function isGranted(
        Ability $ability,
        Administrator $actor,
        ?OwnableInterface $resource = null,
    ): bool {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return match ($ability) {
            Ability::VIEW => $resource && $this->isOwner($actor, $resource),
            Ability::EDIT => $resource && $this->isOwner($actor, $resource),
            Ability::CREATE => $this->canCreate($actor),
        };
    }

    public function canView(Administrator $actor, OwnableInterface $resource): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return $this->isOwner($actor, $resource);
    }

    public function canEdit(Administrator $actor, OwnableInterface $resource): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return $this->isOwner($actor, $resource);
    }

    public function canCreate(Administrator $actor): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return $actor->getId() !== null;
    }

    private function isSuperAdmin(Administrator $actor): bool
    {
        if ($actor->isSuperUser()) {
            return true;
        }

        return false;
    }

    private function isOwner(Administrator $actor, OwnableInterface $resource): bool
    {
        $owner = $resource->getOwner();
        $myId  = $actor->getId();

        return $owner !== null
            && $myId !== null
            && $owner->getId() === $myId;
    }
}

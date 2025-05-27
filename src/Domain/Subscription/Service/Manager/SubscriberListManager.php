<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberListDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

class SubscriberListManager
{
    private SubscriberListRepository $subscriberListRepository;

    public function __construct(SubscriberListRepository $subscriberListRepository)
    {
        $this->subscriberListRepository = $subscriberListRepository;
    }

    public function createSubscriberList(
        CreateSubscriberListDto $subscriberListDto,
        Administrator $authUser
    ): SubscriberList {
        $subscriberList = (new SubscriberList())
            ->setName($subscriberListDto->name)
            ->setOwner($authUser)
            ->setDescription($subscriberListDto->description)
            ->setListPosition($subscriberListDto->listPosition)
            ->setPublic($subscriberListDto->isPublic);

        $this->subscriberListRepository->save($subscriberList);

        return $subscriberList;
    }

    /**
     * @return SubscriberList[]
     */
    public function getPaginated(int $afterId, int $limit): array
    {
        return $this->subscriberListRepository->getAfterId($afterId, $limit);
    }

    public function getTotalCount(): int
    {
        return $this->subscriberListRepository->count();
    }

    public function delete(SubscriberList $subscriberList): void
    {
        $this->subscriberListRepository->remove($subscriberList);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberListDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

class SubscriberListManager
{
    public function __construct(private readonly SubscriberListRepository $subscriberListRepository)
    {
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

        $this->subscriberListRepository->persist($subscriberList);

        return $subscriberList;
    }

    public function updateSubscriberList(
        SubscriberList $subscriberList,
        CreateSubscriberListDto $subscriberListDto,
        Administrator $authUser
    ): SubscriberList {
        return $subscriberList
            ->setName($subscriberListDto->name)
            ->setOwner($authUser)
            ->setDescription($subscriberListDto->description)
            ->setListPosition($subscriberListDto->listPosition)
            ->setCategory($subscriberListDto->category)
            ->setSubjectPrefix($subscriberListDto->subjectPrefix)
            ->setRssFeed($subscriberListDto->rssFeed)
            ->setPublic($subscriberListDto->isPublic);
    }

    /**
     * @return SubscriberList[]
     */
    public function getPaginated(int $afterId, int $limit): array
    {
        /** @var SubscriberList[] $lists*/
        $lists = $this->subscriberListRepository->getAfterId($afterId, $limit)->getItems();

        return $lists;
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

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;

class SubscriberDeletionService
{
    private LinkTrackUmlClickRepository $linkTrackUmlClickRepo;
    private EntityManagerInterface $entityManager;
    private UserMessageRepository $userMessageRepo;
    private SubscriberAttributeValueRepository $subscriberAttrValueRepo;
    private SubscriberHistoryRepository $subscriberHistoryRepo;
    private UserMessageBounceRepository $userMessageBounceRepo;
    private UserMessageForwardRepository $userMessageForwardRepo;
    private UserMessageViewRepository $userMessageViewRepo;
    private SubscriptionRepository $subscriptionRepo;

    public function __construct(
        LinkTrackUmlClickRepository $linkTrackUmlClickRepo,
        EntityManagerInterface $entityManager,
        UserMessageRepository $userMessageRepo,
        SubscriberAttributeValueRepository $subscriberAttrValueRepo,
        SubscriberHistoryRepository $subscriberHistoryRepo,
        UserMessageBounceRepository $userMessageBounceRepo,
        UserMessageForwardRepository $userMessageForwardRepo,
        UserMessageViewRepository $userMessageViewRepo,
        SubscriptionRepository $subscriptionRepo,
    ) {
        $this->linkTrackUmlClickRepo = $linkTrackUmlClickRepo;
        $this->entityManager = $entityManager;
        $this->userMessageRepo = $userMessageRepo;
        $this->subscriberAttrValueRepo = $subscriberAttrValueRepo;
        $this->subscriberHistoryRepo = $subscriberHistoryRepo;
        $this->userMessageBounceRepo = $userMessageBounceRepo;
        $this->userMessageForwardRepo = $userMessageForwardRepo;
        $this->userMessageViewRepo = $userMessageViewRepo;
        $this->subscriptionRepo = $subscriptionRepo;
    }

    public function deleteLeavingBlacklist(Subscriber $subscriber): void
    {
        $this->removeEntities($this->linkTrackUmlClickRepo->findBy(['userId' => $subscriber->getId()]));
        $this->removeEntities($this->subscriptionRepo->findBy(['subscriber' => $subscriber]));
        $this->removeEntities($this->userMessageRepo->findBy(['user' => $subscriber]));
        $this->removeEntities($this->subscriberAttrValueRepo->findBy(['subscriber' => $subscriber]));
        $this->removeEntities($this->subscriberHistoryRepo->findBy(['subscriber' => $subscriber]));
        $this->removeEntities($this->userMessageBounceRepo->findBy(['userId' => $subscriber->getId()]));
        $this->removeEntities($this->userMessageForwardRepo->findBy(['userId' => $subscriber->getId()]));
        $this->removeEntities($this->userMessageViewRepo->findBy(['userId' => $subscriber->getId()]));

        $this->entityManager->remove($subscriber);
    }

    /**
     * Remove a collection of entities
     *
     * @param array $entities
     */
    private function removeEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager->remove($entity);
        }
    }
}

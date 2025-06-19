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
    private LinkTrackUmlClickRepository $linkTrackUmlClickRepository;
    private EntityManagerInterface $entityManager;
    private UserMessageRepository $userMessageRepository;
    private SubscriberAttributeValueRepository $subscriberAttributeValueRepository;
    private SubscriberHistoryRepository $subscriberHistoryRepository;
    private UserMessageBounceRepository $userMessageBounceRepository;
    private UserMessageForwardRepository $userMessageForwardRepository;
    private UserMessageViewRepository $userMessageViewRepository;
    private SubscriptionRepository $subscriptionRepository;

    public function __construct(
        LinkTrackUmlClickRepository $linkTrackUmlClickRepository,
        EntityManagerInterface $entityManager,
        UserMessageRepository $userMessageRepository,
        SubscriberAttributeValueRepository $subscriberAttributeValueRepository,
        SubscriberHistoryRepository $subscriberHistoryRepository,
        UserMessageBounceRepository $userMessageBounceRepository,
        UserMessageForwardRepository $userMessageForwardRepository,
        UserMessageViewRepository $userMessageViewRepository,
        SubscriptionRepository $subscriptionRepository
    ) {
        $this->linkTrackUmlClickRepository = $linkTrackUmlClickRepository;
        $this->entityManager = $entityManager;
        $this->userMessageRepository = $userMessageRepository;
        $this->subscriberAttributeValueRepository = $subscriberAttributeValueRepository;
        $this->subscriberHistoryRepository = $subscriberHistoryRepository;
        $this->userMessageBounceRepository = $userMessageBounceRepository;
        $this->userMessageForwardRepository = $userMessageForwardRepository;
        $this->userMessageViewRepository = $userMessageViewRepository;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function deleteLeavingBlacklist(Subscriber $subscriber): void
    {
        $linkTrackUmlClick = $this->linkTrackUmlClickRepository->findBy(['userId' => $subscriber->getId()]);
        foreach ($linkTrackUmlClick as $click) {
            $this->entityManager->remove($click);
        }

        $subscriptions = $this->subscriptionRepository->findBy(['subscriber' => $subscriber]);
        foreach ($subscriptions as $subscription) {
            $this->entityManager->remove($subscription);
        }

        $userMessages = $this->userMessageRepository->findBy(['user' => $subscriber]);
        foreach ($userMessages as $message) {
            $this->entityManager->remove($message);
        }

        $subscriberAttributes = $this->subscriberAttributeValueRepository->findBy(['subscriber' => $subscriber]);
        foreach ($subscriberAttributes as $attribute) {
            $this->entityManager->remove($attribute);
        }

        $subscriberHistory = $this->subscriberHistoryRepository->findBy(['subscriber' => $subscriber]);
        foreach ($subscriberHistory as $history) {
            $this->entityManager->remove($history);
        }

        $userMessageBounces = $this->userMessageBounceRepository->findBy(['userId' => $subscriber->getId()]);
        foreach ($userMessageBounces as $bounce) {
            $this->entityManager->remove($bounce);
        }

        $userMessageForwards = $this->userMessageForwardRepository->findBy(['userId' => $subscriber->getId()]);
        foreach ($userMessageForwards as $forward) {
            $this->entityManager->remove($forward);
        }

        $userMessageViews = $this->userMessageViewRepository->findBy(['userId' => $subscriber->getId()]);
        foreach ($userMessageViews as $view) {
            $this->entityManager->remove($view);
        }

        $this->entityManager->remove($subscriber);
    }
}

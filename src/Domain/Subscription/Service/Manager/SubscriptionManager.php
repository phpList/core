<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Exception\SubscriptionCreationException;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionManager
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberListRepository $subscriberListRepository,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function addSubscriberToAList(Subscriber $subscriber, int $listId): ?Subscription
    {
        $existingSubscription = $this->subscriptionRepository
            ->findOneBySubscriberEmailAndListId($listId, $subscriber->getEmail());
        if ($existingSubscription) {
            return null;
        }
        $subscriberList = $this->subscriberListRepository->find($listId);
        if (!$subscriberList) {
            $message = $this->translator->trans('Subscriber list not found.');
            throw new SubscriptionCreationException($message, 404);
        }

        $subscription = new Subscription();
        $subscription->setSubscriber($subscriber);
        $subscription->setSubscriberList($subscriberList);

        $this->entityManager->persist($subscription);

        return $subscription;
    }

    /** @return Subscription[] */
    public function createSubscriptions(SubscriberList $subscriberList, array $emails, bool $autoConfirm): array
    {
        $subscriptions = [];
        foreach ($emails as $email) {
            $subscriber = $this->subscriberRepository->findOneByEmail($email);
            if (!$subscriber) {
                $subscriber = new Subscriber($email);
                $subscriber->setConfirmed($autoConfirm);
                $this->entityManager->persist($subscriber);
            }
            $subscriptions[] = $this->createSubscription(subscriberList: $subscriberList, subscriber: $subscriber);
        }

        return $subscriptions;
    }

    private function createSubscription(SubscriberList $subscriberList, Subscriber $subscriber): Subscription
    {
        $existingSubscription = $this->subscriptionRepository
            ->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);
        if ($existingSubscription) {
            return $existingSubscription;
        }

        $subscription = new Subscription();
        $subscription->setSubscriber($subscriber);
        $subscription->setSubscriberList($subscriberList);

        $this->entityManager->persist($subscription);

        return $subscription;
    }

    public function deleteSubscriptions(SubscriberList $subscriberList, array $emails): void
    {
        foreach ($emails as $email) {
            try {
                $this->deleteSubscription($subscriberList, $email);
            } catch (SubscriptionCreationException $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
            }
        }
    }

    private function deleteSubscription(SubscriberList $subscriberList, string $email): void
    {
        $subscription = $this->subscriptionRepository
            ->findOneBySubscriberEmailAndListId($subscriberList->getId(), $email);

        if (!$subscription) {
            $message = $this->translator->trans('Subscription not found for this subscriber and list.');
            throw new SubscriptionCreationException($message, 404);
        }

        $this->entityManager->remove($subscription);
    }

    /** @return Subscriber[] */
    public function getSubscriberListMembers(SubscriberList $list): array
    {
        return $this->subscriberRepository->getSubscribersBySubscribedListId($list->getId());
    }
}

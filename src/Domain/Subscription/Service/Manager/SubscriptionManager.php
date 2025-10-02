<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

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
    private SubscriptionRepository $subscriptionRepository;
    private SubscriberRepository $subscriberRepository;
    private SubscriberListRepository $subscriberListRepository;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        SubscriberRepository $subscriberRepository,
        SubscriberListRepository $subscriberListRepository,
        TranslatorInterface $translator
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriberRepository = $subscriberRepository;
        $this->subscriberListRepository = $subscriberListRepository;
        $this->translator = $translator;
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

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    /** @return Subscription[] */
    public function createSubscriptions(SubscriberList $subscriberList, array $emails): array
    {
        $subscriptions = [];
        foreach ($emails as $email) {
            $subscriptions[] = $this->createSubscription($subscriberList, $email);
        }

        return $subscriptions;
    }

    private function createSubscription(SubscriberList $subscriberList, string $email): Subscription
    {
        $subscriber = $this->subscriberRepository->findOneBy(['email' => $email]);
        if (!$subscriber) {
            $message = $this->translator->trans('Subscriber does not exists.');
            throw new SubscriptionCreationException($message, 404);
        }

        $existingSubscription = $this->subscriptionRepository
            ->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);
        if ($existingSubscription) {
            return $existingSubscription;
        }

        $subscription = new Subscription();
        $subscription->setSubscriber($subscriber);
        $subscription->setSubscriberList($subscriberList);

        $this->subscriptionRepository->save($subscription);

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

        $this->subscriptionRepository->remove($subscription);
    }

    /** @return Subscriber[] */
    public function getSubscriberListMembers(SubscriberList $list): array
    {
        return $this->subscriberRepository->getSubscribersBySubscribedListId($list->getId());
    }
}

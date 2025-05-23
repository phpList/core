<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscriberManager
{
    private SubscriberRepository $subscriberRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(SubscriberRepository $subscriberRepository, EntityManagerInterface $entityManager)
    {
        $this->subscriberRepository = $subscriberRepository;
        $this->entityManager = $entityManager;
    }

    public function createSubscriber(CreateSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail($subscriberDto->email);
        $confirmed = (bool)$subscriberDto->requestConfirmation;
        $subscriber->setConfirmed(!$confirmed);
        $subscriber->setBlacklisted(false);
        $subscriber->setHtmlEmail((bool)$subscriberDto->htmlEmail);
        $subscriber->setDisabled(false);

        $this->subscriberRepository->save($subscriber);

        return $subscriber;
    }

    public function getSubscriber(int $subscriberId): Subscriber
    {
        $subscriber = $this->subscriberRepository->findSubscriberWithSubscriptions($subscriberId);

        if (!$subscriber) {
            throw new NotFoundHttpException('Subscriber not found');
        }

        return $subscriber;
    }

    public function updateSubscriber(UpdateSubscriberDto $subscriberDto): Subscriber
    {
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find($subscriberDto->subscriberId);

        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->additionalData);

        $this->entityManager->flush();

        return $subscriber;
    }

    public function deleteSubscriber(Subscriber $subscriber): void
    {
        $this->subscriberRepository->remove($subscriber);
    }

    public function createFromImport(ImportSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->extraData);

        $this->subscriberRepository->save($subscriber);

        return $subscriber;
    }

    public function updateFromImport(Subscriber $existingSubscriber, ImportSubscriberDto $subscriberDto): Subscriber
    {
        $existingSubscriber->setEmail($subscriberDto->email);
        $existingSubscriber->setConfirmed($subscriberDto->confirmed);
        $existingSubscriber->setBlacklisted($subscriberDto->blacklisted);
        $existingSubscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $existingSubscriber->setDisabled($subscriberDto->disabled);
        $existingSubscriber->setExtraData($subscriberDto->extraData);

        return $existingSubscriber;
    }
}

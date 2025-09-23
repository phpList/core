<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberManager
{
    private SubscriberRepository $subscriberRepository;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private SubscriberDeletionService $subscriberDeletionService;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberRepository $subscriberRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        SubscriberDeletionService $subscriberDeletionService,
        TranslatorInterface $translator
    ) {
        $this->subscriberRepository = $subscriberRepository;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->subscriberDeletionService = $subscriberDeletionService;
        $this->translator = $translator;
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

        if ($subscriberDto->requestConfirmation) {
            $this->sendConfirmationEmail($subscriber);
        }

        return $subscriber;
    }

    private function sendConfirmationEmail(Subscriber $subscriber): void
    {
        $message = new SubscriberConfirmationMessage(
            email: $subscriber->getEmail(),
            uniqueId:$subscriber->getUniqueId(),
            htmlEmail: $subscriber->hasHtmlEmail()
        );

        $this->messageBus->dispatch($message);
    }

    public function getSubscriberById(int $subscriberId): ?Subscriber
    {
        return $this->subscriberRepository->find($subscriberId);
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

    public function markAsConfirmedByUniqueId(string $uniqueId): Subscriber
    {
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uniqueId);
        if (!$subscriber) {
            throw new NotFoundHttpException($this->translator->trans('Subscriber not found'));
        }

        $subscriber->setConfirmed(true);
        $this->entityManager->flush();

        return $subscriber;
    }

    public function deleteSubscriber(Subscriber $subscriber): void
    {
        $this->subscriberDeletionService->deleteLeavingBlacklist($subscriber);
    }

    public function createFromImport(ImportSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->extraData ?? '');

        $this->entityManager->persist($subscriber);

        if ($subscriberDto->sendConfirmation) {
            $this->sendConfirmationEmail($subscriber);
        }

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

    public function decrementBounceCount(Subscriber $subscriber): void
    {
        $subscriber->addToBounceCount(-1);
        $this->entityManager->flush();
    }
}

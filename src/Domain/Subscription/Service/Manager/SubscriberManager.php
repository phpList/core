<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberManager
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriberDeletionService $subscriberDeletionService,
        private readonly TranslatorInterface $translator,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberHistoryRepository $subscriberHistoryRepository,
    ) {
    }

    public function createSubscriber(CreateSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber($subscriberDto->email);
        $confirmed = (bool)$subscriberDto->requestConfirmation;
        $subscriber->setConfirmed(!$confirmed);
        $subscriber->setBlacklisted(false);
        $subscriber->setHtmlEmail((bool)$subscriberDto->htmlEmail);
        $subscriber->setDisabled(false);

        $this->subscriberRepository->persist($subscriber);

        return $subscriber;
    }

    public function getSubscriberById(int $subscriberId): ?Subscriber
    {
        return $this->subscriberRepository->find($subscriberId);
    }

    public function getSubscriberDetails(int $subscriberId): ?Subscriber
    {
        $subscriber = $this->getSubscriberById($subscriberId);
        if ($subscriber === null) {
            return null;
        }

        $history = $this->subscriberHistoryRepository->getBySubscriber($subscriber);
        $subscriber->setHistory($history);

        return $subscriber;
    }

    public function updateSubscriber(
        Subscriber $subscriber,
        UpdateSubscriberDto $subscriberDto,
        Administrator $admin
    ): Subscriber {
        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);

        $uow = $this->entityManager->getUnitOfWork();
        $meta = $this->entityManager->getClassMetadata(Subscriber::class);
        $uow->computeChangeSet($meta, $subscriber);
        $changeSet = ChangeSetDto::fromDoctrineChangeSet($uow->getEntityChangeSet($subscriber));

        $this->subscriberHistoryManager->addHistoryFromApi($subscriber, [], $changeSet, $admin);

        return $subscriber;
    }

    public function resetBounceCount(Subscriber $subscriber): Subscriber
    {
        $subscriber->setBounceCount(0);

        return $subscriber;
    }

    public function markAsConfirmedByUniqueId(string $uniqueId): Subscriber
    {
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uniqueId);
        if (!$subscriber) {
            throw new NotFoundHttpException($this->translator->trans('Subscriber not found'));
        }

        $subscriber->setConfirmed(true);

        return $subscriber;
    }

    public function deleteSubscriber(Subscriber $subscriber): void
    {
        $this->subscriberDeletionService->deleteLeavingBlacklist($subscriber);
    }

    public function createFromImport(ImportSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->extraData);
        if ($subscriberDto->foreignKey !== null) {
            $subscriber->setForeignKey($subscriberDto->foreignKey);
        }

        $this->entityManager->persist($subscriber);

        return $subscriber;
    }

    public function updateFromImport(Subscriber $existingSubscriber, ImportSubscriberDto $subscriberDto): ChangeSetDto
    {
        $existingSubscriber->setEmail($subscriberDto->email);
        $existingSubscriber->setConfirmed($subscriberDto->confirmed);
        $existingSubscriber->setBlacklisted($subscriberDto->blacklisted);
        $existingSubscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $existingSubscriber->setDisabled($subscriberDto->disabled);
        $existingSubscriber->setExtraData($subscriberDto->extraData);
        if ($subscriberDto->foreignKey !== null) {
            $existingSubscriber->setForeignKey($subscriberDto->foreignKey);
        }

        $uow = $this->entityManager->getUnitOfWork();
        $meta = $this->entityManager->getClassMetadata(Subscriber::class);
        $uow->computeChangeSet($meta, $existingSubscriber);

        return ChangeSetDto::fromDoctrineChangeSet($uow->getEntityChangeSet($existingSubscriber));
    }
}

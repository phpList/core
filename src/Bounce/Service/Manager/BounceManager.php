<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Manager;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BounceManager
{
    public function __construct(
        private readonly BounceRepository $bounceRepository,
        private readonly UserMessageBounceRepository $userMessageBounceRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function create(
        ?DateTimeImmutable $date = null,
        ?string $header = null,
        ?string $data = null,
        ?string $status = null,
        ?string $comment = null
    ): Bounce {
        $bounce = new Bounce(
            date: new DateTime($date->format('Y-m-d H:i:s')),
            header: $header,
            data: $data,
            status: $status,
            comment: $comment
        );

        $this->bounceRepository->persist($bounce);
        $this->entityManager->flush();

        return $bounce;
    }

    public function update(Bounce $bounce, ?string $status = null, ?string $comment = null): Bounce
    {
        $bounce->setStatus($status);
        $bounce->setComment($comment);
        $this->entityManager->flush();

        return $bounce;
    }

    public function delete(Bounce $bounce): void
    {
        $this->bounceRepository->remove($bounce);
        $this->entityManager->flush();
    }

    /** @return Bounce[] */
    public function getAll(): array
    {
        return $this->bounceRepository->findAll();
    }

    public function getById(int $id): ?Bounce
    {
        /** @var Bounce|null $found */
        $found = $this->bounceRepository->find($id);
        return $found;
    }

    public function linkUserMessageBounce(
        Bounce $bounce,
        DateTimeImmutable $date,
        int $subscriberId,
        ?int $messageId = -1
    ): UserMessageBounce {
        $userMessageBounce = new UserMessageBounce(
            bounceId: $bounce->getId(),
            createdAt: new DateTime($date->format('Y-m-d H:i:s'))
        );
        $userMessageBounce->setUserId($subscriberId);
        $userMessageBounce->setMessageId($messageId);

        return $userMessageBounce;
    }

    public function existsUserMessageBounce(int $subscriberId, int $messageId): bool
    {
        return $this->userMessageBounceRepo->existsByMessageIdAndUserId($messageId, $subscriberId);
    }

    /** @return Bounce[] */
    public function findByStatus(string $status): array
    {
        return $this->bounceRepository->findByStatus($status);
    }

    public function getUserMessageBounceCount(): int
    {
        return $this->userMessageBounceRepo->count();
    }

    /**
     * @return array<int, array{umb: UserMessageBounce, bounce: Bounce}>
     */
    public function fetchUserMessageBounceBatch(int $fromId, int $batchSize): array
    {
        return $this->userMessageBounceRepo->getPaginatedWithJoinNoRelation($fromId, $batchSize);
    }

    /**
     * @return array<int, array{um: UserMessage, umb: UserMessageBounce|null, b: Bounce|null}>
     */
    public function getUserMessageHistoryWithBounces(Subscriber $subscriber): array
    {
        return $this->userMessageBounceRepo->getUserMessageHistoryWithBounces($subscriber);
    }

    public function announceDeletionMode(bool $testMode): void
    {
        $testModeMessage = $this->translator->trans('Running in test mode, not deleting messages from mailbox');
        $liveModeMessage = $this->translator->trans('Processed messages will be deleted from the mailbox');

        $this->logger->info($testMode ? $testModeMessage : $liveModeMessage);
    }
}

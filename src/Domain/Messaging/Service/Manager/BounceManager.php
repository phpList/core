<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use DateTime;
use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;

class BounceManager
{
    private BounceRepository $bounceRepository;
    private UserMessageBounceRepository $userMessageBounceRepository;

    public function __construct(
        BounceRepository $bounceRepository,
        UserMessageBounceRepository $userMessageBounceRepository
    ) {
        $this->bounceRepository = $bounceRepository;
        $this->userMessageBounceRepository = $userMessageBounceRepository;
    }

    public function create(
        ?DateTimeImmutable $date = null,
        ?string $header = null,
        ?string $data = null,
        ?string $status = null,
        ?string $comment = null
    ): Bounce {
        $bounce = new Bounce(
            date: DateTime::createFromImmutable($date),
            header: $header,
            data: $data,
            status: $status,
            comment: $comment
        );

        $this->bounceRepository->save($bounce);

        return $bounce;
    }

    public function update(Bounce $bounce, ?string $status = null, ?string $comment = null): Bounce
    {
        $bounce->setStatus($status);
        $bounce->setComment($comment);
        $this->bounceRepository->save($bounce);

        return $bounce;
    }

    public function delete(Bounce $bounce): void
    {
        $this->bounceRepository->remove($bounce);
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
        $userMessageBounce = new UserMessageBounce($bounce->getId(), DateTime::createFromImmutable($date));
        $userMessageBounce->setUserId($subscriberId);
        $userMessageBounce->setMessageId($messageId);

        return $userMessageBounce;
    }

    public function existsUserMessageBounce(int $subscriberId, int $messageId): bool
    {
        return $this->userMessageBounceRepository->existsByMessageIdAndUserId($messageId, $subscriberId);
    }

    /** @return Bounce[] */
    public function findByStatus(string $status): array
    {
        return $this->bounceRepository->findByStatus($status);
    }

    public function getUserMessageBounceCount(): int
    {
        return $this->userMessageBounceRepository->count();
    }

    /**
     * @return array<int, array{umb: UserMessageBounce, bounce: Bounce}>
     */    public function fetchUserMessageBounceBatch(int $fromId, int $batchSize): array
    {
        return $this->userMessageBounceRepository->getPaginatedWithJoinNoRelation($fromId, $batchSize);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Manager;

use DateTimeInterface;
use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;

class UserMessageViewManager
{
    private UserMessageViewRepository $userMessageViewRepository;

    public function __construct(
        UserMessageViewRepository $userMessageViewRepository,
    ) {
        $this->userMessageViewRepository = $userMessageViewRepository;
    }

    /**
     * Count views by message ID
     */
    public function countViewsByMessageId(int $messageId): int
    {
        return $this->userMessageViewRepository->countByMessageId($messageId);
    }

    public function countUniqueViewsByMessageId(int $messageId): int
    {
        return $this->userMessageViewRepository->uniqueByMessageId($messageId);
    }

    public function countViewsBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return $this->userMessageViewRepository->countBetween($start, $end);
    }

    public function countViewsGroupedByDay(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->userMessageViewRepository->countGroupedByDay($start, $end);
    }

    public function countViewsByMessageIds(array $messageIds): array
    {
        return $this->userMessageViewRepository->countByMessageIds($messageIds);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Manager;

use DateTimeInterface;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;

class LinkTrackManager
{
    private LinkTrackRepository $linkTrackRepository;

    public function __construct(LinkTrackRepository $linkTrackRepository)
    {
        $this->linkTrackRepository = $linkTrackRepository;
    }

    /**
     * Get link tracks by message ID
     *
     * @param int $messageId
     * @param int $lastId Last seen ID
     * @param int|null $limit Max results
     * @return LinkTrack[]
     */
    public function getLinkTracksByMessageId(int $messageId, int $lastId = 0, ?int $limit = null): array
    {
        return $this->linkTrackRepository->getByMessageId($messageId, $lastId, $limit);
    }

    public function countClicksBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return $this->linkTrackRepository->countBetween($start, $end);
    }

    public function countClicksGroupedByDay(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->linkTrackRepository->countGroupedByDay($start, $end);
    }

    public function countUniqueClickersByMessageIds(array $messageIds): array
    {
        return $this->linkTrackRepository->countUniqueClickersByMessageIds($messageIds);
    }
}

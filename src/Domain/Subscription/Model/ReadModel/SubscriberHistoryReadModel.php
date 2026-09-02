<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\ReadModel;

use DateTime;
use PhpList\Core\Domain\Subscription\Model\Interfaces\SubscriberHistoryRecordInterface;

/** Flat projection of a history row as read back from Elasticsearch - no Doctrine association. */
class SubscriberHistoryReadModel implements SubscriberHistoryRecordInterface
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $subscriberId,
        private readonly ?string $ip,
        private readonly ?DateTime $createdAt,
        private readonly ?string $summary,
        private readonly ?string $detail,
        private readonly ?string $systemInfo,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscriberId(): ?int
    {
        return $this->subscriberId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getSystemInfo(): ?string
    {
        return $this->systemInfo;
    }
}

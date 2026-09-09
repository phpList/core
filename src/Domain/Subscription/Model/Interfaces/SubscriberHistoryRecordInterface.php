<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Interfaces;

use DateTime;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;

/**
 * The read shape shared by the Doctrine-backed SubscriberHistory entity and the flat
 * SubscriberHistoryReadModel built from Elasticsearch hits, so both SubscriberHistoryManager and
 * SubscriberManager can read history rows without caring which backend produced them.
 *
 * Deliberately excludes a full Subscriber association: the ES-backed read model would need an extra
 * per-row database round-trip to hydrate one, defeating the point of reading from Elasticsearch only.
 */
interface SubscriberHistoryRecordInterface extends DomainModel
{
    public function getId(): ?int;

    public function getSubscriberId(): ?int;

    public function getIp(): ?string;

    public function getCreatedAt(): ?DateTime;

    public function getSummary(): ?string;

    public function getDetail(): ?string;

    public function getSystemInfo(): ?string;
}

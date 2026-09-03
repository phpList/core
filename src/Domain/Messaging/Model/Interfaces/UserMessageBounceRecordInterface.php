<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Interfaces;

use DateTime;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;

/**
 * The read shape shared by the Doctrine-backed UserMessageBounce entity and the flat
 * UserMessageBounceReadModel built from Elasticsearch hits, so both database and
 * Elasticsearch-backed readers can be consumed without caring which backend produced them.
 */
interface UserMessageBounceRecordInterface extends DomainModel
{
    public function getId(): ?int;

    public function getUserId(): int;

    public function getMessageId(): int;

    public function getBounceId(): int;

    public function getCreatedAt(): DateTime;
}

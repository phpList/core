<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;

class BounceView implements DomainModel
{
    public function __construct(
        public int $id,
        public ?string $status,
        public ?string $comment,
        public ?DateTimeInterface $date,
        public ?int $messageId,
        public ?string $messageSubject,
        public ?int $subscriberId,
        public ?string $subscriberEmail,
    ) {
    }
}

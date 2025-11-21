<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class UpdateSubscriberDto
{
    public function __construct(
        public readonly string $email,
        public readonly bool $confirmed,
        public readonly bool $blacklisted,
        public readonly bool $htmlEmail,
        public readonly bool $disabled,
        public readonly string $additionalData,
    ) {
    }
}

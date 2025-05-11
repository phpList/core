<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class CreateSubscriberDto
{
    public function __construct(
        public readonly string $email,
        public readonly ?bool $requestConfirmation = null,
        public readonly ?bool $htmlEmail = null,
    ) {
    }
}

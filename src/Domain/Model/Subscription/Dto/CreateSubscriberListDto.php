<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

final class CreateSubscriberListDto
{
    public function __construct(
        public readonly string $name,
        public readonly bool $public = false,
        public readonly ?int $listPosition = null,
        public readonly ?string $description = null,
    ) {}
}

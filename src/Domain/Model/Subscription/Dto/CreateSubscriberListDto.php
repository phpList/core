<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

class CreateSubscriberListDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $isPublic = false,
        public readonly ?int $listPosition = null,
        public readonly ?string $description = null,
    ) {
    }
}

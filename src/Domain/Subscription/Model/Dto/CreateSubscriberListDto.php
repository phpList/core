<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class CreateSubscriberListDto
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isPublic = false,
        public readonly ?int $listPosition = null,
        public readonly ?string $description = null,
        public readonly ?string $category = null,
        public readonly ?string $subjectPrefix = null,
        public readonly ?string $rssFeed = null
    ) {
    }
}

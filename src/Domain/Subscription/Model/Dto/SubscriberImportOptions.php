<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class SubscriberImportOptions
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly bool $updateExisting = false,
        public readonly array $listIds = [],
        public readonly bool $dryRun = false,
        public readonly bool $skipInvalidEmail = true,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service;

interface BounceProcessingServiceInterface
{
    public function processMailbox(string $mailbox, int $max, bool $testMode): string;
}

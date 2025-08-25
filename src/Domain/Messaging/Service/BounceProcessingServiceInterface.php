<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Symfony\Component\Console\Style\SymfonyStyle;

interface BounceProcessingServiceInterface
{
    public function processMailbox(SymfonyStyle $io, string $mailbox, int $max, bool $testMode): string;
}

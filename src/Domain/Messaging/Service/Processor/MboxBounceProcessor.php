<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingService;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MboxBounceProcessor implements BounceProtocolProcessor
{
    public function __construct(private readonly BounceProcessingService $processingService)
    {
    }

    public function getProtocol(): string
    {
        return 'mbox';
    }

    public function process(InputInterface $input, SymfonyStyle $io): string
    {
        $testMode = (bool)$input->getOption('test');
        $max = (int)$input->getOption('maximum');
        $purgeProcessed = $input->getOption('purge') && !$testMode;
        $purgeUnprocessed = $input->getOption('purge-unprocessed') && !$testMode;

        $file = (string)$input->getOption('mailbox');
        if (!$file) {
            $io->error('mbox file path must be provided with --mailbox.');
            throw new RuntimeException('Missing --mailbox for mbox protocol');
        }

        $io->section("Opening mbox $file");
        $link = @imap_open($file, '', '', $testMode ? 0 : CL_EXPUNGE);
        if (!$link) {
            $io->error('Cannot open mailbox file: '.imap_last_error());
            throw new RuntimeException('Cannot open mbox file');
        }

        return $this->processingService->processMailbox(
            $io,
            $link,
            $max,
            $purgeProcessed,
            $purgeUnprocessed,
            $testMode
        );
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\NativeBounceProcessingService;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MboxBounceProcessor implements BounceProtocolProcessor
{
    private $processingService;
    private string $user;
    private string $pass;

    public function __construct(NativeBounceProcessingService $processingService, string $user, string $pass)
    {
        $this->processingService = $processingService;
        $this->user = $user;
        $this->pass = $pass;
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

        return $this->processingService->processMailbox(
            $io,
            $file,
            $this->user,
            $this->pass,
            $max,
            $purgeProcessed,
            $purgeUnprocessed,
            $testMode
        );
    }
}

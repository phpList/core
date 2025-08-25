<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingServiceInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PopBounceProcessor implements BounceProtocolProcessor
{
    private BounceProcessingServiceInterface $processingService;
    private string $host;
    private int $port;
    private string $mailboxNames;

    public function __construct(
        BounceProcessingServiceInterface $processingService,
        string $host,
        int $port,
        string $mailboxNames
    ) {
        $this->processingService = $processingService;
        $this->host = $host;
        $this->port = $port;
        $this->mailboxNames = $mailboxNames;
    }

    public function getProtocol(): string
    {
        return 'pop';
    }

    public function process(InputInterface $input, SymfonyStyle $io): string
    {
        $testMode = (bool)$input->getOption('test');
        $max = (int)$input->getOption('maximum');

        $downloadReport = '';
        foreach (explode(',', $this->mailboxNames) as $mailboxName) {
            $mailboxName = trim($mailboxName);
            if ($mailboxName === '') { $mailboxName = 'INBOX'; }
            $mailbox = sprintf('{%s:%s}%s', $this->host, $this->port, $mailboxName);
            $io->section("Connecting to $mailbox");

            $downloadReport .= $this->processingService->processMailbox(
                $io,
                $mailbox,
                $max,
                $testMode
            );
        }

        return $downloadReport;
    }
}

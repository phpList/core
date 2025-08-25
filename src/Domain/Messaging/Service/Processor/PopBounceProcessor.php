<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\NativeBounceProcessingService;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PopBounceProcessor implements BounceProtocolProcessor
{
    private $processingService;

    public function __construct(NativeBounceProcessingService $processingService)
    {
        $this->processingService = $processingService;
    }

    public function getProtocol(): string
    {
        return 'pop';
    }

    public function process(InputInterface $input, SymfonyStyle $io): string
    {
        $testMode = (bool)$input->getOption('test');
        $max = (int)$input->getOption('maximum');
        $purgeProcessed = $input->getOption('purge') && !$testMode;
        $purgeUnprocessed = $input->getOption('purge-unprocessed') && !$testMode;

        $host = (string)$input->getOption('host');
        $user = (string)$input->getOption('user');
        $password = (string)$input->getOption('password');
        $port = (string)$input->getOption('port');
        $mailboxes = (string)$input->getOption('mailbox');

        if (!$host || !$user || !$password) {
            $io->error('POP configuration incomplete: host, user, and password are required.');
            throw new RuntimeException('POP configuration incomplete');
        }

        $downloadReport = '';
        foreach (explode(',', $mailboxes) as $mailboxName) {
            $mailboxName = trim($mailboxName);
            if ($mailboxName === '') { $mailboxName = 'INBOX'; }
            $mailbox = sprintf('{%s:%s}%s', $host, $port, $mailboxName);
            $io->section("Connecting to $mailbox");

            $downloadReport .= $this->processingService->processMailbox(
                $io,
                $mailbox,
                $user,
                $password,
                $max,
                $purgeProcessed,
                $purgeUnprocessed,
                $testMode
            );
        }

        return $downloadReport;
    }
}

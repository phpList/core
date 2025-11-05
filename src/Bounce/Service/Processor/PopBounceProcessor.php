<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Processor;

use PhpList\Core\Bounce\Service\BounceProcessingServiceInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class PopBounceProcessor implements BounceProtocolProcessor
{
    private BounceProcessingServiceInterface $processingService;
    private string $host;
    private int $port;
    private string $mailboxNames;
    private TranslatorInterface $translator;

    public function __construct(
        BounceProcessingServiceInterface $processingService,
        string $host,
        int $port,
        string $mailboxNames,
        TranslatorInterface $translator
    ) {
        $this->processingService = $processingService;
        $this->host = $host;
        $this->port = $port;
        $this->mailboxNames = $mailboxNames;
        $this->translator = $translator;
    }

    public function getProtocol(): string
    {
        return 'pop';
    }

    public function process(InputInterface $input, SymfonyStyle $inputOutput): string
    {
        $testMode = (bool)$input->getOption('test');
        $max = (int)$input->getOption('maximum');

        $downloadReport = '';
        foreach (explode(',', $this->mailboxNames) as $mailboxName) {
            $mailboxName = trim($mailboxName);
            if ($mailboxName === '') {
                $mailboxName = 'INBOX';
            }
            $mailbox = sprintf('{%s:%s}%s', $this->host, $this->port, $mailboxName);
            $inputOutput->section($this->translator->trans('Connecting to %mailbox%', ['%mailbox%' => $mailbox]));
            $inputOutput->writeln($this->translator->trans('Please do not interrupt this process'));

            $downloadReport .= $this->processingService->processMailbox(
                mailbox: $mailbox,
                max: $max,
                testMode: $testMode
            );
        }

        return $downloadReport;
    }
}

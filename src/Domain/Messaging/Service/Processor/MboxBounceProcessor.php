<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingServiceInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MboxBounceProcessor implements BounceProtocolProcessor
{
    private BounceProcessingServiceInterface $processingService;

    public function __construct(BounceProcessingServiceInterface $processingService)
    {
        $this->processingService = $processingService;
    }

    public function getProtocol(): string
    {
        return 'mbox';
    }

    public function process(InputInterface $input, SymfonyStyle $inputOutput): string
    {
        $testMode = (bool)$input->getOption('test');
        $max = (int)$input->getOption('maximum');

        $file = (string)$input->getOption('mailbox');
        if (!$file) {
            $inputOutput->error('mbox file path must be provided with --mailbox.');
            throw new RuntimeException('Missing --mailbox for mbox protocol');
        }

        $inputOutput->section("Opening mbox $file");

        return $this->processingService->processMailbox(
            $inputOutput,
            $file,
            $max,
            $testMode
        );
    }
}

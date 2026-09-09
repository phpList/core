<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingServiceInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class MboxBounceProcessor implements BounceProtocolProcessor
{
    private BounceProcessingServiceInterface $processingService;
    private TranslatorInterface $translator;

    public function __construct(BounceProcessingServiceInterface $processingService, TranslatorInterface $translator)
    {
        $this->processingService = $processingService;
        $this->translator = $translator;
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
            $inputOutput->error($this->translator->trans('mbox file path must be provided with --mailbox.'));
            throw new RuntimeException('Missing --mailbox for mbox protocol');
        }

        $inputOutput->section($this->translator->trans('Opening mbox %file%', ['%file%' => $file]));
        $inputOutput->writeln($this->translator->trans('Please do not interrupt this process'));

        return $this->processingService->processMailbox(
            mailbox: $file,
            max: $max,
            testMode: $testMode
        );
    }
}

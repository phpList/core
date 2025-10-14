<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Command;

use Exception;
use PhpList\Core\Bounce\Service\ConsecutiveBounceHandler;
use PhpList\Core\Bounce\Service\LockService;
use PhpList\Core\Bounce\Service\Processor\AdvancedBounceRulesProcessor;
use PhpList\Core\Bounce\Service\Processor\BounceProtocolProcessor;
use PhpList\Core\Bounce\Service\Processor\UnidentifiedBounceReprocessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'phplist:bounces:process', description: 'Process bounce mailbox')]
class ProcessBouncesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('protocol', null, InputOption::VALUE_REQUIRED, 'Mailbox protocol: pop or mbox', 'pop')
            ->addOption(
                'purge-unprocessed',
                null,
                InputOption::VALUE_NONE,
                'Delete/remove unprocessed messages from mailbox'
            )
            ->addOption('rules-batch-size', null, InputOption::VALUE_OPTIONAL, 'Advanced rules batch size', '1000')
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test mode: do not delete from mailbox')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force run: kill other processes if locked');
    }

    public function __construct(
        private readonly LockService $lockService,
        private readonly LoggerInterface $logger,
        /** @var iterable<BounceProtocolProcessor> */
        private readonly iterable $protocolProcessors,
        private readonly AdvancedBounceRulesProcessor $advancedRulesProcessor,
        private readonly UnidentifiedBounceReprocessor $unidentifiedReprocessor,
        private readonly ConsecutiveBounceHandler $consecutiveBounceHandler,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputOutput = new SymfonyStyle($input, $output);

        if (!function_exists('imap_open')) {
            $inputOutput->note($this->translator->trans(
                'PHP IMAP extension not available. Falling back to Webklex IMAP.'
            ));
        }

        $force = (bool)$input->getOption('force');
        $lock = $this->lockService->acquirePageLock('bounce_processor', $force);

        if (($lock ?? 0) === 0) {
            $forceLockFailed = $this->translator->trans('Could not apply force lock. Aborting.');
            $lockFailed = $this->translator->trans('Another bounce processing is already running. Aborting.');

            $inputOutput->warning($force ? $forceLockFailed : $lockFailed);

            return $force ? Command::FAILURE : Command::SUCCESS;
        }

        try {
            $inputOutput->title('Processing bounces');
            $protocol = (string)$input->getOption('protocol');

            $downloadReport = '';

            $processor = $this->findProcessorFor($protocol);
            if ($processor === null) {
                $inputOutput->error('Unsupported protocol: '.$protocol);

                return Command::FAILURE;
            }

            $downloadReport .= $processor->process($input, $inputOutput);
            $this->unidentifiedReprocessor->process($inputOutput);
            $this->advancedRulesProcessor->process($inputOutput, (int)$input->getOption('rules-batch-size'));
            $this->consecutiveBounceHandler->handle($inputOutput);

            $this->logger->info('Bounce processing completed', ['downloadReport' => $downloadReport]);
            $inputOutput->success($this->translator->trans('Bounce processing completed.'));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('Bounce processing failed', ['exception' => $e]);
            $inputOutput->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->lockService->release($lock);
        }
    }

    private function findProcessorFor(string $protocol): ?BounceProtocolProcessor
    {
        foreach ($this->protocolProcessors as $processor) {
            if ($processor->getProtocol() === $protocol) {
                return $processor;
            }
        }

        return null;
    }
}

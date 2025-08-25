<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use Exception;
use PhpList\Core\Domain\Messaging\Service\ConsecutiveBounceHandler;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceProtocolProcessor;
use PhpList\Core\Domain\Messaging\Service\Processor\AdvancedBounceRulesProcessor;
use PhpList\Core\Domain\Messaging\Service\Processor\UnidentifiedBounceReprocessor;
use PhpList\Core\Domain\Messaging\Service\LockService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'phplist:bounces:process', description: 'Process bounce mailbox')]
class ProcessBouncesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('protocol', null, InputOption::VALUE_REQUIRED, 'Mailbox protocol: pop or mbox', 'pop')
            ->addOption('purge-unprocessed', null, InputOption::VALUE_NONE, 'Delete/remove unprocessed messages from mailbox')
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
        private readonly UnidentifiedBounceReprocessor $unidentifiedBounceReprocessor,
        private readonly ConsecutiveBounceHandler $consecutiveBounceHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!function_exists('imap_open')) {
            $io->note('PHP IMAP extension not available. Falling back to Webklex IMAP where applicable.');
        }

        $force = (bool)$input->getOption('force');
        $lock = $this->lockService->acquirePageLock('bounce_processor', $force);

        if (!$lock) {
            $io->warning('Another bounce processing is already running. Aborting.');

            return Command::SUCCESS;
        }

        try {
            $io->title('Processing bounces');
            $protocol = (string)$input->getOption('protocol');

            $downloadReport = '';

            $processor = null;
            foreach ($this->protocolProcessors as $p) {
                if ($p->getProtocol() === $protocol) {
                    $processor = $p;
                    break;
                }
            }

            if ($processor === null) {
                $io->error('Unsupported protocol: '.$protocol);

                return Command::FAILURE;
            }

            $downloadReport .= $processor->process($input, $io);
            $this->unidentifiedBounceReprocessor->process($io);
            $this->advancedRulesProcessor->process($io, (int)$input->getOption('rules-batch-size'));
            $this->consecutiveBounceHandler->handle($io);

            $this->logger->info('Bounce processing completed', ['downloadReport' => $downloadReport]);
            $io->success('Bounce processing completed.');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('Bounce processing failed', ['exception' => $e]);
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->lockService->release($lock);
        }
    }
}

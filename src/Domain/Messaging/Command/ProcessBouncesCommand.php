<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use DateTimeImmutable;
use Exception;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Service\BounceProcessingService;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceProtocolProcessor;
use PhpList\Core\Domain\Messaging\Service\Processor\AdvancedBounceRulesProcessor;
use PhpList\Core\Domain\Messaging\Service\LockService;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
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
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'POP host (without braces) e.g. mail.example.com')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'POP port/options, e.g. 110/pop3/notls', '110/pop3/notls')
            ->addOption('user', null, InputOption::VALUE_OPTIONAL, 'Mailbox username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Mailbox password')
            ->addOption('mailbox', null, InputOption::VALUE_OPTIONAL, 'Mailbox name(s) for POP (comma separated) or mbox file path', 'INBOX')
            ->addOption('maximum', null, InputOption::VALUE_OPTIONAL, 'Max messages to process per run', '1000')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Delete/remove processed messages from mailbox')
            ->addOption('purge-unprocessed', null, InputOption::VALUE_NONE, 'Delete/remove unprocessed messages from mailbox')
            ->addOption('rules-batch-size', null, InputOption::VALUE_OPTIONAL, 'Advanced rules batch size', '1000')
            ->addOption('unsubscribe-threshold', null, InputOption::VALUE_OPTIONAL, 'Consecutive bounces threshold to unconfirm user', '3')
            ->addOption('blacklist-threshold', null, InputOption::VALUE_OPTIONAL, 'Consecutive bounces threshold to blacklist email (0 to disable)', '0')
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test mode: do not delete from mailbox')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force run: kill other processes if locked');
    }

    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly LockService $lockService,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly BounceProcessingService $processingService,
        /** @var iterable<BounceProtocolProcessor> */
        private readonly iterable $protocolProcessors,
        private readonly AdvancedBounceRulesProcessor $advancedRulesProcessor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!function_exists('imap_open')) {
            $io->error('IMAP extension not available. Cannot continue.');

            return Command::FAILURE;
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

            $this->reprocessUnidentified($io);

            $this->advancedRulesProcessor->process($io, (int)$input->getOption('rules-batch-size'));

            $this->handleConsecutiveBounces(
                $io,
                (int)$input->getOption('unsubscribe-threshold'),
                (int)$input->getOption('blacklist-threshold')
            );

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

    private function reprocessUnidentified(SymfonyStyle $io): void
    {
        $io->section('Reprocessing unidentified bounces');
        $bounces = $this->bounceManager->findByStatus('unidentified bounce');
        $total = count($bounces);
        $io->writeln(sprintf('%d bounces to reprocess', $total));
        $count = 0; $reparsed = 0; $reidentified = 0;
        foreach ($bounces as $bounce) {
            $count++;
            if ($count % 25 === 0) {
                $io->writeln(sprintf('%d out of %d processed', $count, $total));
            }
            $decodedBody = $this->processingService->decodeBody($bounce->getHeader(), $bounce->getData());
            $userId = $this->processingService->findUserId($decodedBody);
            $messageId = $this->processingService->findMessageId($decodedBody);
            if ($userId || $messageId) {
                $reparsed++;
                if ($this->processingService->processBounceData($bounce, $messageId, $userId, new DateTimeImmutable())) {
                    $reidentified++;
                }
            }
        }
        $io->writeln(sprintf('%d out of %d processed', $count, $total));
        $io->writeln(sprintf('%d bounces were re-processed and %d bounces were re-identified', $reparsed, $reidentified));
    }

    private function handleConsecutiveBounces(SymfonyStyle $io, int $unsubscribeThreshold, int $blacklistThreshold): void
    {
        $io->section('Identifying consecutive bounces');
        $users = $this->subscriberRepository->distinctUsersWithBouncesConfirmedNotBlacklisted();
        $total = count($users);
        if ($total === 0) {
            $io->writeln('Nothing to do');
            return;
        }
        $usercnt = 0;
        foreach ($users as $user) {
            $usercnt++;
            $history = $this->bounceManager->getUserMessageHistoryWithBounces($user);
            $cnt = 0; $removed = false; $msgokay = false; $unsubscribed = false;
            foreach ($history as $bounce) {
                /** @var $bounce array{um: UserMessage, umb: UserMessageBounce|null, b: Bounce|null} */
                if (
                    stripos($bounce['b']->getStatus() ?? '', 'duplicate') === false
                    && stripos($bounce['b']->getComment() ?? '', 'duplicate') === false
                ) {
                    if ($bounce['b']->getId()) {
                        $cnt++;
                        if ($cnt >= $unsubscribeThreshold) {
                            if (!$unsubscribed) {
                                $this->subscriberManager->markUnconfirmed($user->getId());
                                $this->subscriberHistoryManager->addHistory(
                                    subscriber: $user,
                                    message: 'Auto Unconfirmed',
                                    details: sprintf('Subscriber auto unconfirmed for %d consecutive bounces', $cnt)
                                );
                                $unsubscribed = true;
                            }
                            if ($blacklistThreshold > 0 && $cnt >= $blacklistThreshold) {
                                $this->subscriberManager->blacklist(
                                    subscriber: $user,
                                    reason: sprintf('%d consecutive bounces, threshold reached', $cnt)
                                );
                                $removed = true;
                            }
                        }
                    } else {
                        break;
                    }
                }
                if ($removed) {
                    break;
                }
            }
            if ($usercnt % 5 === 0) {
                $io->writeln(sprintf('processed %d out of %d subscribers', $usercnt, $total));
            }
        }
        $io->writeln(sprintf('total of %d subscribers processed', $total));
    }
}

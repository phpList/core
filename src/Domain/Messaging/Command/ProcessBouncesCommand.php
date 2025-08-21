<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use DateTimeImmutable;
use Exception;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\LockService;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRuleManager;
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
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Delete processed messages from mailbox')
            ->addOption('purge-unprocessed', null, InputOption::VALUE_NONE, 'Delete unprocessed messages from mailbox')
            ->addOption('rules-batch-size', null, InputOption::VALUE_OPTIONAL, 'Advanced rules batch size', '1000')
            ->addOption('unsubscribe-threshold', null, InputOption::VALUE_OPTIONAL, 'Consecutive bounces threshold to unconfirm user', '3')
            ->addOption('blacklist-threshold', null, InputOption::VALUE_OPTIONAL, 'Consecutive bounces threshold to blacklist email (0 to disable)', '0')
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test mode: do not delete from mailbox')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force run: kill other processes if locked');
    }

    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberRepository $users,
        private readonly MessageRepository $messages,
        private readonly BounceRuleManager $ruleManager,
        private readonly LockService $lockService,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
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
            $testMode = (bool)$input->getOption('test');
            $max = (int)$input->getOption('maximum');
            $purgeProcessed = $input->getOption('purge') && !$testMode;
            $purgeUnprocessed = $input->getOption('purge-unprocessed') && !$testMode;

            $downloadReport = '';

            if ($protocol === 'pop') {
                $host = (string)$input->getOption('host');
                $user = (string)$input->getOption('user');
                $password = (string)$input->getOption('password');
                $port = (string)$input->getOption('port');
                $mailboxes = (string)$input->getOption('mailbox');

                if (!$host || !$user || !$password) {
                    $io->error('POP configuration incomplete: host, user, and password are required.');

                    return Command::FAILURE;
                }

                foreach (explode(',', $mailboxes) as $mailboxName) {
                    $mailboxName = trim($mailboxName);
                    if ($mailboxName === '') { $mailboxName = 'INBOX'; }
                    $mailbox = sprintf('{%s:%s}%s', $host, $port, $mailboxName);
                    $io->section("Connecting to $mailbox");

                    $link = @imap_open($mailbox, $user, $password);
                    if (!$link) {
                        $io->error('Cannot create connection to '.$mailbox.': '.imap_last_error());

                        return Command::FAILURE;
                    }

                    $downloadReport .= $this->processMessages($io, $link, $max, $purgeProcessed, $purgeUnprocessed, $testMode);
                }
            } elseif ($protocol === 'mbox') {
                $file = (string)$input->getOption('mailbox');
                if (!$file) {
                    $io->error('mbox file path must be provided with --mailbox.');

                    return Command::FAILURE;
                }
                $io->section("Opening mbox $file");
                $link = @imap_open($file, '', '', $testMode ? 0 : CL_EXPUNGE);
                if (!$link) {
                    $io->error('Cannot open mailbox file: '.imap_last_error());

                    return Command::FAILURE;
                }
                $downloadReport .= $this->processMessages($io, $link, $max, $purgeProcessed, $purgeUnprocessed, $testMode);
            } else {
                $io->error('Unsupported protocol: '.$protocol);

                return Command::FAILURE;
            }

            // Reprocess unidentified bounces (status = "unidentified bounce")
            $this->reprocessUnidentified($io);

            // Advanced bounce rules
            $this->processAdvancedRules($io, (int)$input->getOption('rules-batch-size'));

            // Identify and unconfirm users with consecutive bounces
            $this->handleConsecutiveBounces(
                $io,
                (int)$input->getOption('unsubscribe-threshold'),
                (int)$input->getOption('blacklist-threshold')
            );

            // Summarize and report (email or log)
            $this->logger->info('Bounce processing completed', [
                'downloadReport' => $downloadReport,
            ]);

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

    private function processMessages(SymfonyStyle $io, $link, int $max, bool $purgeProcessed, bool $purgeUnprocessed, bool $testMode): string
    {
        $num = imap_num_msg($link);
        $io->writeln(sprintf('%d bounces to fetch from the mailbox', $num));
        if ($num === 0) {
            imap_close($link);

            return '';
        }
        $io->writeln('Please do not interrupt this process');
        if ($num > $max) {
            $io->writeln(sprintf('Processing first %d bounces', $max));
            $num = $max;
        }
        $io->writeln($testMode ? 'Running in test mode, not deleting messages from mailbox' : 'Processed messages will be deleted from the mailbox');

        for ($x = 1; $x <= $num; $x++) {
            $header = imap_fetchheader($link, $x);
            $processed = $this->processImapBounce($link, $x, $header, $io);
            if ($processed) {
                if (!$testMode && $purgeProcessed) {
                    imap_delete($link, (string)$x);
                }
            } else {
                if (!$testMode && $purgeUnprocessed) {
                    imap_delete($link, (string)$x);
                }
            }
        }

        $io->writeln('Closing mailbox, and purging messages');
        if (!$testMode) {
            imap_close($link, CL_EXPUNGE);
        } else {
            imap_close($link);
        }

        return '';
    }

    private function processImapBounce($link, int $num, string $header, SymfonyStyle $io): bool
    {
        $headerInfo = imap_headerinfo($link, $num);
        $date = $headerInfo->date ?? null;
        $bounceDate = $date ? new DateTimeImmutable($date) : new DateTimeImmutable();
        $body = imap_body($link, $num);
        $body = $this->decodeBody($header, $body);

        // Quick hack: ignore MsExchange delayed notices (as in original)
        if (preg_match('/Action: delayed\s+Status: 4\.4\.7/im', $body)) {
            return true;
        }

        $msgId = $this->findMessageId($body);
        $userId = $this->findUserId($body);

        $bounce = $this->bounceManager->create($bounceDate, $header, $body);

        return $this->processBounceData($bounce, $msgId, $userId, $bounceDate);
    }

    private function processBounceData(
        Bounce $bounce,
        string|int|null $msgId,
        ?int $userId,
        DateTimeImmutable $bounceDate,
    ): bool {
        $msgId = $msgId ?: null;
        if ($userId) {
            $user = $this->subscriberManager->getSubscriberById($userId);
        }

        if ($msgId === 'systemmessage' && $userId) {
            $this->bounceManager->update(
                bounce: $bounce,
                status: 'bounced system message',
                comment: sprintf('%d marked unconfirmed', $userId))
            ;
            $this->bounceManager->linkUserMessageBounce($bounce,$bounceDate, $userId);
            $this->subscriberManager->markUnconfirmed($userId);
            $this->logger->info('system message bounced, user marked unconfirmed', ['userId' => $userId]);
            $this->subscriberHistoryManager->addHistory(
                subscriber: $user,
                message: 'Bounced system message',
                details: sprintf('User marked unconfirmed. Bounce #%d', $bounce->getId())
            );

            return true;
        }

        if ($msgId && $userId) {
            if (!$this->bounceManager->existsUserMessageBounce($userId, (int)$msgId)) {
                $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate,$userId, (int)$msgId);
                $this->bounceManager->update(
                    bounce: $bounce,
                    status: sprintf('bounced list message %d', $msgId),
                    comment: sprintf('%d bouncecount increased', $userId)
                );
                $this->messages->incrementBounceCount((int)$msgId);
                $this->users->incrementBounceCount($userId);
            } else {
                $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId, (int)$msgId);
                $this->bounceManager->update(
                    bounce: $bounce,
                    status: sprintf('duplicate bounce for %d', $userId),
                    comment: sprintf('duplicate bounce for subscriber %d on message %d', $userId, $msgId)
                );
            }
            return true;
        }

        if ($userId) {
            $this->bounceManager->update(
                bounce: $bounce,
                status: 'bounced unidentified message',
                comment: sprintf('%d bouncecount increased', $userId)
            );
            $this->users->incrementBounceCount($userId);
            return true;
        }

        if ($msgId === 'systemmessage') {
            $this->bounceManager->update($bounce, 'bounced system message', 'unknown user');
            $this->logger->info('system message bounced, but unknown user');
            return true;
        }

        if ($msgId) {
            $this->bounceManager->update($bounce, sprintf('bounced list message %d', $msgId), 'unknown user');
            $this->messages->incrementBounceCount((int)$msgId);
            return true;
        }

        $this->bounceManager->update($bounce, 'unidentified bounce', 'not processed');

        return false;
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
            $decodedBody = $this->decodeBody($bounce->getHeader(), $bounce->getData());
            $userId = $this->findUserId($decodedBody);
            $messageId = $this->findMessageId($decodedBody);
            if ($userId || $messageId) {
                $reparsed++;
                if ($this->processBounceData($bounce->getId(), $messageId, $userId, new DateTimeImmutable())) {
                    $reidentified++;
                }
            }
        }
        $io->writeln(sprintf('%d out of %d processed', $count, $total));
        $io->writeln(sprintf('%d bounces were re-processed and %d bounces were re-identified', $reparsed, $reidentified));
    }

    private function processAdvancedRules(SymfonyStyle $io, int $batchSize): void
    {
        $io->section('Processing bounces based on active bounce rules');
        $rules = $this->ruleManager->loadActiveRules();
        if (!$rules) {
            $io->writeln('No active rules');
            return;
        }

        $total = $this->bounceManager->getUserMessageBounceCount();
        $fromId = 0;
        $matched = 0;
        $notmatched = 0;
        $counter = 0;

        while ($counter < $total) {
            $batch = $this->bounceManager->fetchUserMessageBounceBatch($fromId, $batchSize);
            $counter += count($batch);
            $io->writeln(sprintf('processed %d out of %d bounces for advanced bounce rules', min($counter, $total), $total));
            foreach ($batch as $row) {
                $fromId = $row['umb']->getId();
                // $row has: bounce(header,data,id), umb(user,message,bounce)
                $text = $row['bounce']->getHeader()."\n\n".$row['bounce']->getData();
                $rule = $this->ruleManager->matchBounceRules($text, $rules);
                $userId = (int)$row['umb']->getUserId();
                $bounce = $row['bounce'];
                $userdata = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;
                $confirmed = $userdata?->isConfirmed() ?? false;
                $blacklisted = $userdata?->isBlacklisted() ?? false;

                if ($rule) {
                    $this->ruleManager->incrementCount($rule);
                    $rule->setCount($rule->getCount() + 1);
                    $this->ruleManager->linkRuleToBounce($rule, $bounce);

                    switch ($rule->getAction()) {
                        case 'deleteuser':
                            if ($userdata) {
                                $this->logger->info('User deleted by bounce rule', ['user' => $userdata->getEmail(), 'rule' => $rule->getId()]);
                                $this->subscriberManager->deleteSubscriber($userdata);
                            }
                            break;
                        case 'unconfirmuser':
                            if ($userdata && $confirmed) {
                                $this->subscriberManager->markUnconfirmed($userId);
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unconfirmed', 'Subscriber auto unconfirmed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'deleteuserandbounce':
                            if ($userdata) {
                                $this->subscriberManager->deleteSubscriber($userdata);
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'unconfirmuseranddeletebounce':
                            if ($userdata && $confirmed) {
                                $this->subscriberManager->markUnconfirmed($userId);
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto unconfirmed', 'Subscriber auto unconfirmed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'decreasecountconfirmuseranddeletebounce':
                            if ($userdata) {
                                $this->subscriberManager->decrementBounceCount($userdata);
                                if (!$confirmed) {
                                    $this->subscriberManager->markConfirmed($userId);
                                    $this->subscriberHistoryManager->addHistory($userdata, 'Auto confirmed', 'Subscriber auto confirmed for bounce rule '.$rule->getId());
                                }
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'blacklistuser':
                            if ($userdata && !$blacklisted) {
                                $this->subscriberManager->blacklist($userdata, 'Subscriber auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'blacklistuseranddeletebounce':
                            if ($userdata && !$blacklisted) {
                                $this->subscriberManager->blacklist($userdata, 'Subscriber auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'blacklistemail':
                            if ($userdata) {
                                $this->subscriberManager->blacklist($userdata, 'Email address auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'email auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'blacklistemailanddeletebounce':
                            if ($userdata) {
                                $this->subscriberManager->blacklist($userdata, 'Email address auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'deletebounce':
                            $this->bounceManager->delete($bounce);
                            break;
                    }
                    $matched++;
                } else {
                    $notmatched++;
                }
            }
        }
        $io->writeln(sprintf('%d bounces processed by advanced processing', $matched));
        $io->writeln(sprintf('%d bounces were not matched by advanced processing rules', $notmatched));
    }

    // --- Consecutive bounces logic (mirrors final section) ---
    private function handleConsecutiveBounces(SymfonyStyle $io, int $unsubscribeThreshold, int $blacklistThreshold): void
    {
        $io->section('Identifying consecutive bounces');
        $userIds = $this->bounces->distinctUsersWithBouncesConfirmedNotBlacklisted();
        $total = \count($userIds);
        if ($total === 0) {
            $io->writeln('Nothing to do');
            return;
        }
        $usercnt = 0;
        foreach ($userIds as $userId) {
            $usercnt++;
            $history = $this->bounces->userMessageHistoryWithBounces($userId); // ordered desc, includes bounce status/comment
            $cnt = 0; $removed = false; $msgokay = false; $unsubscribed = false;
            foreach ($history as $bounce) {
                if (stripos($bounce->status ?? '', 'duplicate') === false && stripos($bounce->comment ?? '', 'duplicate') === false) {
                    if ($bounce->bounceId) { // there is a bounce
                        $cnt++;
                        if ($cnt >= $unsubscribeThreshold) {
                            if (!$unsubscribed) {
                                $email = $this->users->emailById($userId);
                                $this->users->markUnconfirmed($userId);
                                $this->users->addHistory($email, 'Auto Unconfirmed', sprintf('Subscriber auto unconfirmed for %d consecutive bounces', $cnt));
                                $unsubscribed = true;
                            }
                            if ($blacklistThreshold > 0 && $cnt >= $blacklistThreshold) {
                                $email = $this->users->emailById($userId);
                                $this->users->blacklistByEmail($email, sprintf('%d consecutive bounces, threshold reached', $cnt));
                                $removed = true;
                            }
                        }
                    } else { // empty bounce means message received ok
                        $cnt = 0;
                        $msgokay = true;
                        break;
                    }
                }
                if ($removed || $msgokay) { break; }
            }
            if ($usercnt % 5 === 0) {
                $io->writeln(sprintf('processed %d out of %d subscribers', $usercnt, $total));
            }
        }
        $io->writeln(sprintf('total of %d subscribers processed', $total));
    }

    // --- Helpers: decoding and parsing ---
    private function decodeBody(string $header, string $body): string
    {
        $transferEncoding = '';
        if (preg_match('/Content-Transfer-Encoding: ([\w-]+)/i', $header, $regs)) {
            $transferEncoding = strtolower($regs[1]);
        }
        $decoded = null;
        switch ($transferEncoding) {
            case 'quoted-printable':
                $decoded = quoted_printable_decode($body);
                break;
            case 'base64':
                $decoded = base64_decode($body) ?: '';
                break;
            default:
                $decoded = $body;
        }
        return $decoded;
    }

    private function findMessageId(string $text): string|int|null
    {
        if (preg_match('/(?:X-MessageId|X-Message): (.*)\r\n/iU', $text, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    private function findUserId(string $text): ?int
    {
        // Try X-ListMember / X-User first
        if (preg_match('/(?:X-ListMember|X-User): (.*)\r\n/iU', $text, $match)) {
            $user = trim($match[1]);
            if (str_contains($user, '@')) {
                return $this->users->idByEmail($user);
            } elseif (preg_match('/^\d+$/', $user)) {
                return (int)$user;
            } elseif ($user !== '') {
                return $this->users->idByUniqId($user);
            }
        }
        // Fallback: parse any email in the body and see if it is a subscriber
        if (preg_match_all('/[._a-zA-Z0-9-]+@[.a-zA-Z0-9-]+/', $text, $regs)) {
            foreach ($regs[0] as $email) {
                $id = $this->users->idByEmail($email);
                if ($id) { return $id; }
            }
        }
        return null;
    }
}

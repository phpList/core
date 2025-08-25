<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Mail\MailReaderInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class NativeBounceProcessingService
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberRepository $users,
        private readonly MessageRepository $messages,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly MailReaderInterface $mailReader,
        private readonly MessageParser $messageParser,
    ) {
    }

    public function processMailbox(
        SymfonyStyle $io,
        string $mailbox,
        string $user,
        string $password,
        int $max,
        bool $purgeProcessed,
        bool $purgeUnprocessed,
        bool $testMode
    ): string {
        try {
            $link = $this->mailReader->open($mailbox, $user, $password, $testMode ? 0 : CL_EXPUNGE);
        } catch (Throwable $e) {
            $io->error('Cannot open mailbox file: '.$e->getMessage());
            throw new RuntimeException('Cannot open mbox file');
        }

        $num = $this->mailReader->numMessages($link);
        $io->writeln(sprintf('%d bounces to fetch from the mailbox', $num));
        if ($num === 0) {
            $this->mailReader->close($link, false);

            return '';
        }

        $io->writeln('Please do not interrupt this process');
        if ($num > $max) {
            $io->writeln(sprintf('Processing first %d bounces', $max));
            $num = $max;
        }
        $io->writeln($testMode ? 'Running in test mode, not deleting messages from mailbox' : 'Processed messages will be deleted from the mailbox');

        for ($x = 1; $x <= $num; $x++) {
            $header = $this->mailReader->fetchHeader($link, $x);
            $processed = $this->processImapBounce($link, $x, $header, $io);
            if ($processed) {
                if (!$testMode && $purgeProcessed) {
                    $this->mailReader->delete($link, $x);
                }
            } else {
                if (!$testMode && $purgeUnprocessed) {
                    $this->mailReader->delete($link, $x);
                }
            }
        }

        $io->writeln('Closing mailbox, and purging messages');
        if (!$testMode) {
            $this->mailReader->close($link, true);
        } else {
            $this->mailReader->close($link, false);
        }

        return '';
    }

    private function processImapBounce($link, int $num, string $header): bool
    {
        $bounceDate = $this->mailReader->headerDate($link, $num);
        $body = $this->mailReader->body($link, $num);
        $body = $this->messageParser->decodeBody($header, $body);

        // Quick hack: ignore MsExchange delayed notices (as in original)
        if (preg_match('/Action: delayed\s+Status: 4\.4\.7/im', $body)) {
            return true;
        }

        $msgId = $this->messageParser->findMessageId($body);
        $userId = $this->messageParser->findUserId($body);

        $bounce = $this->bounceManager->create($bounceDate, $header, $body);

        return $this->processBounceData($bounce, $msgId, $userId, $bounceDate);
    }

    public function processBounceData(Bounce $bounce, ?string $msgId, ?int $userId, DateTimeImmutable $bounceDate): bool
    {
        $user = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;

        if ($msgId === 'systemmessage' && $userId) {
            $this->bounceManager->update(
                bounce: $bounce,
                status: 'bounced system message',
                comment: sprintf('%d marked unconfirmed', $userId)
            );
            $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId);
            $this->subscriberManager->markUnconfirmed($userId);
            $this->logger->info('system message bounced, user marked unconfirmed', ['userId' => $userId]);
            if ($user) {
                $this->subscriberHistoryManager->addHistory(
                    subscriber: $user,
                    message: 'Bounced system message',
                    details: sprintf('User marked unconfirmed. Bounce #%d', $bounce->getId())
                );
            }

            return true;
        }

        if ($msgId && $userId) {
            if (!$this->bounceManager->existsUserMessageBounce($userId, (int)$msgId)) {
                $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId, (int)$msgId);
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
}

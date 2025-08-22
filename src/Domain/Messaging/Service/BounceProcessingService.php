<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeImmutable;
use IMAP\Connection;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Contains the core bounce processing logic shared across protocols.
 */
class BounceProcessingService
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberRepository $users,
        private readonly MessageRepository $messages,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
    ) {
    }

    public function processMailbox(
        SymfonyStyle $io,
        Connection $link,
        int $max,
        bool $purgeProcessed,
        bool $purgeUnprocessed,
        bool $testMode
    ): string {
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

    public function processBounceData(
        Bounce $bounce,
        string|int|null $msgId,
        ?int $userId,
        DateTimeImmutable $bounceDate,
    ): bool {
        $msgId = $msgId ?: null;
        $user = null;
        if ($userId) {
            $user = $this->subscriberManager->getSubscriberById($userId);
        }

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

    public function decodeBody(string $header, string $body): string
    {
        $transferEncoding = '';
        if (preg_match('/Content-Transfer-Encoding: ([\w-]+)/i', $header, $regs)) {
            $transferEncoding = strtolower($regs[1]);
        }
        return match ($transferEncoding) {
            'quoted-printable' => quoted_printable_decode($body),
            'base64' => base64_decode($body) ?: '',
            default => $body,
        };
    }

    public function findMessageId(string $text): string|int|null
    {
        if (preg_match('/(?:X-MessageId|X-Message): (.*)\r\n/iU', $text, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    public function findUserId(string $text): ?int
    {
        // Try X-ListMember / X-User first
        if (preg_match('/(?:X-ListMember|X-User): (.*)\r\n/iU', $text, $match)) {
            $user = trim($match[1]);
            if (str_contains($user, '@')) {
                return $this->subscriberManager->getSubscriberByEmail($user)?->getId();
            } elseif (preg_match('/^\d+$/', $user)) {
                return (int)$user;
            } elseif ($user !== '') {
                return $this->subscriberManager->getSubscriberByEmail($user)?->getId();
            }
        }
        // Fallback: parse any email in the body and see if it is a subscriber
        if (preg_match_all('/[._a-zA-Z0-9-]+@[.a-zA-Z0-9-]+/', $text, $regs)) {
            foreach ($regs[0] as $email) {
                $id = $this->subscriberManager->getSubscriberByEmail($email)?->getId();
                if ($id) { return $id; }
            }
        }
        return null;
    }
}

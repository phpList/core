<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeImmutable;
use DateTimeInterface;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceDataProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class WebklexBounceProcessingService implements BounceProcessingServiceInterface
{
    private BounceManager $bounceManager;
    private LoggerInterface $logger;
    private MessageParser $messageParser;
    private WebklexImapClientFactory $clientFactory;
    private BounceDataProcessor $bounceDataProcessor;
    private bool $purgeProcessed;
    private bool $purgeUnprocessed;

    public function __construct(
        BounceManager $bounceManager,
        LoggerInterface $logger,
        MessageParser $messageParser,
        WebklexImapClientFactory $clientFactory,
        BounceDataProcessor $bounceDataProcessor,
        bool $purgeProcessed,
        bool $purgeUnprocessed
    ) {
        $this->bounceManager = $bounceManager;
        $this->logger = $logger;
        $this->messageParser = $messageParser;
        $this->clientFactory = $clientFactory;
        $this->bounceDataProcessor = $bounceDataProcessor;
        $this->purgeProcessed = $purgeProcessed;
        $this->purgeUnprocessed = $purgeUnprocessed;
    }

    /**
     * Process unseen messages from the given mailbox using Webklex.
     *
     * $mailbox: IMAP host; if you pass "host#FOLDER", FOLDER will be used instead of INBOX.
     *
     * @throws RuntimeException If connection to the IMAP server cannot be established.
     */
    public function processMailbox(
        string $mailbox,
        int $max,
        bool $testMode
    ): string {
        $client = $this->clientFactory->makeForMailbox();

        try {
            $client->connect();
        } catch (Throwable $e) {
            $this->logger->error('Cannot connect to mailbox: '.$e->getMessage());
            throw new RuntimeException('Cannot connect to IMAP server');
        }

        try {
            $folder = $client->getFolder($this->clientFactory->getFolderName());
            $query = $folder->query()->unseen()->limit($max);

            $messages = $query->get();
            $num = $messages->count();

            $this->logger->info(sprintf('%d bounces to fetch from the mailbox', $num));
            if ($num === 0) {
                return '';
            }

            $this->bounceManager->announceDeletionMode($testMode);

            foreach ($messages as $message) {
                $header = $this->headerToStringSafe($message);
                $body = $this->bodyBestEffort($message);
                $body = $this->messageParser->decodeBody($header, $body);

                if (\preg_match('/Action: delayed\s+Status: 4\.4\.7/im', $body)) {
                    if (!$testMode && $this->purgeProcessed) {
                        $this->safeDelete($message);
                    }
                    continue;
                }

                $messageId  = $this->messageParser->findMessageId($body."\r\n".$header);
                $userId = $this->messageParser->findUserId($body."\r\n".$header);

                $bounceDate = $this->extractDate($message);
                $bounce = $this->bounceManager->create($bounceDate, $header, $body);

                $processed = $this->bounceDataProcessor->process($bounce, $messageId, $userId, $bounceDate);

                $this->processDelete($testMode, $processed, $message);
            }

            $this->logger->info('Closing mailbox, and purging messages');
            $this->processExpunge($testMode, $folder, $client);

            return '';
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable $e) {
                $this->logger->warning('Disconnect failed', ['error' => $e->getMessage()]);
            }
        }
    }

    private function headerToStringSafe(mixed $message): string
    {
        $raw = $this->tryRawHeader($message);
        if ($raw !== null) {
            return $raw;
        }

        $lines = [];
        $subj = $message->getSubject() ?? '';
        $from = $this->addrFirstToString($message->getFrom());
        $messageTo = $this->addrManyToString($message->getTo());
        $date = $this->extractDate($message)->format(\DATE_RFC2822);

        if ($subj !== '') {
            $lines[] = 'Subject: ' . $subj;
        }
        if ($from !== '') {
            $lines[] = 'From: ' . $from;
        }
        if ($messageTo !== '') {
            $lines[] = 'To: ' . $messageTo;
        }
        $lines[] = 'Date: ' . $date;

        $mid = $message->getMessageId() ?? '';
        if ($mid !== '') {
            $lines[] = 'Message-ID: ' . $mid;
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    private function tryRawHeader(mixed $message): ?string
    {
        if (!method_exists($message, 'getHeader')) {
            return null;
        }

        try {
            $headerObj = $message->getHeader();
            if ($headerObj && method_exists($headerObj, 'toString')) {
                $raw = (string) $headerObj->toString();
                if ($raw !== '') {
                    return $raw;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private function bodyBestEffort($message): string
    {
        $text = ($message->getTextBody() ?? '');
        if ($text !== '') {
            return $text;
        }
        $html = ($message->getHTMLBody() ?? '');
        if ($html !== '') {
            return trim(strip_tags($html));
        }

        return '';
    }

    private function extractDate(mixed $message): DateTimeImmutable
    {
        $date = $message->getDate();
        if ($date instanceof DateTimeInterface) {
            return new DateTimeImmutable($date->format('Y-m-d H:i:s'));
        }

        if (method_exists($message, 'getInternalDate')) {
            $internalDate = (int) $message->getInternalDate();
            if ($internalDate > 0) {
                return new DateTimeImmutable('@'.$internalDate);
            }
        }

        return new DateTimeImmutable();
    }

    private function addrFirstToString($addresses): string
    {
        $many = $this->addrManyToArray($addresses);
        return $many[0] ?? '';
    }

    private function addrManyToString($addresses): string
    {
        $arr = $this->addrManyToArray($addresses);
        return implode(', ', $arr);
    }

    private function addrManyToArray($addresses): array
    {
        if ($addresses === null) {
            return [];
        }
        $out = [];
        foreach ($addresses as $addr) {
            $email = ($addr->mail ?? $addr->getAddress() ?? '');
            $name  = ($addr->personal ?? $addr->getName() ?? '');
            $out[] = $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
        }

        return $out;
    }

    private function processDelete(bool $testMode, bool $processed, mixed $message): void
    {
        if (!$testMode) {
            if ($processed && $this->purgeProcessed) {
                $this->safeDelete($message);
            } elseif (!$processed && $this->purgeUnprocessed) {
                $this->safeDelete($message);
            }
        }
    }

    private function safeDelete($message): void
    {
        try {
            if (method_exists($message, 'delete')) {
                $message->delete();
            } elseif (method_exists($message, 'setFlag')) {
                $message->setFlag('DELETED');
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to delete message', ['error' => $e->getMessage()]);
        }
    }

    private function processExpunge(bool $testMode, ?Folder $folder, Client $client): void
    {
        if (!$testMode) {
            try {
                if (method_exists($folder, 'expunge')) {
                    $folder->expunge();
                } elseif (method_exists($client, 'expunge')) {
                    $client->expunge();
                }
            } catch (Throwable $e) {
                $this->logger->warning('EXPUNGE failed', ['error' => $e->getMessage()]);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeImmutable;
use DateTimeInterface;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceDataProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

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
     */
    public function processMailbox(
        SymfonyStyle $io,
        string $mailbox,
        int $max,
        bool $testMode
    ): string {
        $client = $this->clientFactory->makeForMailbox();

        try {
            $client->connect();
        } catch (Throwable $e) {
            $io->error('Cannot connect to mailbox: '.$e->getMessage());
            throw new RuntimeException('Cannot connect to IMAP server');
        }

        try {
            $folder = $client->getFolder($this->clientFactory->getFolderName());
            $query = $folder->query()->unseen()->limit($max);

            $messages = $query->get();
            $num = $messages->count();

            $io->writeln(sprintf('%d bounces to fetch from the mailbox', $num));
            if ($num === 0) {
                return '';
            }

            $io->writeln('Please do not interrupt this process');
            $io->writeln($testMode
                ? 'Running in test mode, not deleting messages from mailbox'
                : 'Processed messages will be deleted from the mailbox'
            );

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

                if (!$testMode) {
                    if ($processed && $this->purgeProcessed) {
                        $this->safeDelete($message);
                    } elseif (!$processed && $this->purgeUnprocessed) {
                        $this->safeDelete($message);
                    }
                }
            }

            $io->writeln('Closing mailbox, and purging messages');
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

            return '';
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable $e) {
                // swallow
            }
        }
    }

    private function headerToStringSafe(mixed $message): string
    {
        if (method_exists($message, 'getHeader')) {
            try {
                $headerObj = $message->getHeader();
                if ($headerObj && method_exists($headerObj, 'toString')) {
                    $raw = (string) $headerObj->toString();
                    if ($raw !== '') {
                        return $raw;
                    }
                }
            } catch (Throwable $e) {
                // fall back below
            }
        }

        $lines = [];
        $subj = $message->getSubject() ?? '';
        $from = $this->addrFirstToString($message->getFrom());
        $to = $this->addrManyToString($message->getTo());
        $date = $this->extractDate($message)->format(\DATE_RFC2822);

        if ($subj !== '') { $lines[] = 'Subject: '.$subj; }
        if ($from !== '') { $lines[] = 'From: '.$from; }
        if ($to !== '') { $lines[] = 'To: '.$to; }
        $lines[] = 'Date: '.$date;

        $mid = $message->getMessageId() ?? '';
        if ($mid !== '') { $lines[] = 'Message-ID: '.$mid; }

        return implode("\r\n", $lines)."\r\n";
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
        $d = $message->getDate();
        if ($d instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($d);
        }

        if (method_exists($message, 'getInternalDate')) {
            $ts = (int) $message->getInternalDate();
            if ($ts > 0) {
                return new DateTimeImmutable('@'.$ts);
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
}

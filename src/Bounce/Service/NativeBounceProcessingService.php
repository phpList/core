<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service;

use Doctrine\ORM\EntityManagerInterface;
use IMAP\Connection;
use PhpList\Core\Bounce\Exception\OpenMboxFileException;
use PhpList\Core\Bounce\Service\Manager\BounceManager;
use PhpList\Core\Bounce\Service\Processor\BounceDataProcessor;
use PhpList\Core\Domain\Common\Mail\NativeImapMailReader;
use Psr\Log\LoggerInterface;
use Throwable;

class NativeBounceProcessingService implements BounceProcessingServiceInterface
{
    private BounceManager $bounceManager;
    private NativeImapMailReader $mailReader;
    private MessageParser $messageParser;
    private BounceDataProcessor $bounceDataProcessor;
    private LoggerInterface $logger;
    private bool $purgeProcessed;
    private bool $purgeUnprocessed;
    private EntityManagerInterface $entityManager;

    public function __construct(
        BounceManager $bounceManager,
        NativeImapMailReader $mailReader,
        MessageParser $messageParser,
        BounceDataProcessor $bounceDataProcessor,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        bool $purgeProcessed,
        bool $purgeUnprocessed
    ) {
        $this->bounceManager = $bounceManager;
        $this->mailReader = $mailReader;
        $this->messageParser = $messageParser;
        $this->bounceDataProcessor = $bounceDataProcessor;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->purgeProcessed = $purgeProcessed;
        $this->purgeUnprocessed = $purgeUnprocessed;
    }

    public function processMailbox(
        string $mailbox,
        int $max,
        bool $testMode
    ): string {
        $link = $this->openOrFail($mailbox, $testMode);

        $num = $this->prepareAndCapCount($link, $max);
        if ($num === 0) {
            $this->mailReader->close($link, false);

            return '';
        }

        $this->bounceManager->announceDeletionMode($testMode);

        for ($messageNumber = 1; $messageNumber <= $num; $messageNumber++) {
            $this->handleMessage($link, $messageNumber, $testMode);
        }

        $this->finalize($link, $testMode);

        return '';
    }

    private function openOrFail(string $mailbox, bool $testMode): Connection
    {
        try {
            return $this->mailReader->open($mailbox, $testMode ? 0 : CL_EXPUNGE);
        } catch (Throwable $throwable) {
            $this->logger->error('Cannot open mailbox file', [
                'mailbox' => $mailbox,
                'error' => $throwable->getMessage(),
            ]);
            throw new OpenMboxFileException($throwable);
        }
    }

    private function prepareAndCapCount(Connection $link, int $max): int
    {
        $num = $this->mailReader->numMessages($link);
        $this->logger->info(sprintf('%d bounces to fetch from the mailbox', $num));
        if ($num === 0) {
            return 0;
        }

        $this->logger->info('Please do not interrupt this process');
        if ($num > $max) {
            $this->logger->info(sprintf('Processing first %d bounces', $max));
            $num = $max;
        }

        return $num;
    }

    private function handleMessage(Connection $link, int $messageNumber, bool $testMode): void
    {
        $header = $this->mailReader->fetchHeader($link, $messageNumber);
        $processed = $this->processImapBounce($link, $messageNumber, $header);

        if ($testMode) {
            return;
        }

        if ($processed && $this->purgeProcessed) {
            $this->mailReader->delete($link, $messageNumber);
            return;
        }

        if (!$processed && $this->purgeUnprocessed) {
            $this->mailReader->delete($link, $messageNumber);
        }
    }

    private function finalize(Connection $link, bool $testMode): void
    {
        $this->logger->info('Closing mailbox, and purging messages');
        $this->mailReader->close($link, !$testMode);
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
        $this->entityManager->flush();
        return $this->bounceDataProcessor->process($bounce, $msgId, $userId, $bounceDate);
    }
}

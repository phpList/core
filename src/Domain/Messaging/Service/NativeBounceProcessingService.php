<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\Mail\NativeImapMailReader;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceDataProcessor;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class NativeBounceProcessingService implements BounceProcessingServiceInterface
{
    private BounceManager $bounceManager;
    private NativeImapMailReader $mailReader;
    private MessageParser $messageParser;
    private BounceDataProcessor $bounceDataProcessor;
    private bool $purgeProcessed;
    private bool $purgeUnprocessed;

    public function __construct(
        BounceManager $bounceManager,
        NativeImapMailReader $mailReader,
        MessageParser $messageParser,
        BounceDataProcessor $bounceDataProcessor,
        bool $purgeProcessed,
        bool $purgeUnprocessed
    ) {
        $this->bounceManager = $bounceManager;
        $this->mailReader = $mailReader;
        $this->messageParser = $messageParser;
        $this->bounceDataProcessor = $bounceDataProcessor;
        $this->purgeProcessed = $purgeProcessed;
        $this->purgeUnprocessed = $purgeUnprocessed;
    }

    public function processMailbox(
        SymfonyStyle $io,
        string $mailbox,
        int $max,
        bool $testMode
    ): string {
        try {
            $link = $this->mailReader->open($mailbox, $testMode ? 0 : CL_EXPUNGE);
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
            $processed = $this->processImapBounce($link, $x, $header);
            if ($processed) {
                if (!$testMode && $this->purgeProcessed) {
                    $this->mailReader->delete($link, $x);
                }
            } else {
                if (!$testMode && $this->purgeUnprocessed) {
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

        return $this->bounceDataProcessor->process($bounce, $msgId, $userId, $bounceDate);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Service\MessageParser;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class UnidentifiedBounceReprocessor
{
    private BounceManager $bounceManager;
    private MessageParser $messageParser;
    private BounceDataProcessor $bounceDataProcessor;


    public function __construct(
        BounceManager $bounceManager,
        MessageParser $messageParser,
        BounceDataProcessor $bounceDataProcessor,
    ) {
        $this->bounceManager = $bounceManager;
        $this->messageParser = $messageParser;
        $this->bounceDataProcessor = $bounceDataProcessor;
    }

    public function process(SymfonyStyle $inputOutput): void
    {
        $inputOutput->section('Reprocessing unidentified bounces');
        $bounces = $this->bounceManager->findByStatus('unidentified bounce');
        $total = count($bounces);
        $inputOutput->writeln(sprintf('%d bounces to reprocess', $total));

        $count = 0;
        $reparsed = 0;
        $reidentified = 0;
        foreach ($bounces as $bounce) {
            $count++;
            if ($count % 25 === 0) {
                $inputOutput->writeln(sprintf('%d out of %d processed', $count, $total));
            }

            $decodedBody = $this->messageParser->decodeBody($bounce->getHeader(), $bounce->getData());
            $userId = $this->messageParser->findUserId($decodedBody);
            $messageId = $this->messageParser->findMessageId($decodedBody);

            if ($userId || $messageId) {
                $reparsed++;
                if ($this->bounceDataProcessor->process(
                    $bounce,
                    $messageId,
                    $userId,
                    new DateTimeImmutable())
                ) {
                    $reidentified++;
                }
            }
        }

        $inputOutput->writeln(sprintf('%d out of %d processed', $count, $total));
        $inputOutput->writeln(sprintf(
            '%d bounces were re-processed and %d bounces were re-identified',
            $reparsed, $reidentified
        ));
    }
}

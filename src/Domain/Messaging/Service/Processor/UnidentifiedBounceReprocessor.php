<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Service\NativeBounceProcessingService;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class UnidentifiedBounceReprocessor
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly NativeBounceProcessingService $processingService,
    ) {
    }

    public function process(SymfonyStyle $io): void
    {
        $io->section('Reprocessing unidentified bounces');
        $bounces = $this->bounceManager->findByStatus('unidentified bounce');
        $total = count($bounces);
        $io->writeln(sprintf('%d bounces to reprocess', $total));

        $count = 0;
        $reparsed = 0;
        $reidentified = 0;
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
                if ($this->processingService->processBounceData(
                    $bounce,
                    $messageId,
                    $userId,
                    new DateTimeImmutable())
                ) {
                    $reidentified++;
                }
            }
        }

        $io->writeln(sprintf('%d out of %d processed', $count, $total));
        $io->writeln(sprintf(
            '%d bounces were re-processed and %d bounces were re-identified',
            $reparsed, $reidentified
        ));
    }
}

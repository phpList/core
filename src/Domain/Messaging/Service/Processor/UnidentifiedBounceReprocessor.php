<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Model\BounceStatus;
use PhpList\Core\Domain\Messaging\Service\MessageParser;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnidentifiedBounceReprocessor
{
    private BounceManager $bounceManager;
    private MessageParser $messageParser;
    private BounceDataProcessor $bounceDataProcessor;
    private TranslatorInterface $translator;

    public function __construct(
        BounceManager $bounceManager,
        MessageParser $messageParser,
        BounceDataProcessor $bounceDataProcessor,
        TranslatorInterface $translator,
    ) {
        $this->bounceManager = $bounceManager;
        $this->messageParser = $messageParser;
        $this->bounceDataProcessor = $bounceDataProcessor;
        $this->translator = $translator;
    }

    public function process(SymfonyStyle $inputOutput): void
    {
        $inputOutput->section($this->translator->trans('Reprocessing unidentified bounces'));
        $bounces = $this->bounceManager->findByStatus(BounceStatus::UnidentifiedBounce->value);
        $total = count($bounces);
        $inputOutput->writeln($this->translator->trans('%total% bounces to reprocess', ['%total%' => $total]));

        $count = 0;
        $reparsed = 0;
        $reidentified = 0;
        foreach ($bounces as $bounce) {
            $count++;
            if ($count % 25 === 0) {
                $inputOutput->writeln($this->translator->trans('%count% out of %total% processed', [
                    '%count%' => $count,
                    '%total%' => $total
                ]));
            }

            $decodedBody = $this->messageParser->decodeBody(header: $bounce->getHeader(), body: $bounce->getData());
            $userId = $this->messageParser->findUserId($decodedBody);
            $messageId = $this->messageParser->findMessageId($decodedBody);

            if ($userId || $messageId) {
                $reparsed++;
                if ($this->bounceDataProcessor->process(
                    bounce: $bounce,
                    msgId: $messageId,
                    userId: $userId,
                    bounceDate: new DateTimeImmutable()
                )
                ) {
                    $reidentified++;
                }
            }
        }

        $inputOutput->writeln($this->translator->trans('%count% out of %total% processed', [
            '%count%' => $count,
            '%total%' => $total
        ]));
        $inputOutput->writeln($this->translator->trans(
            '%reparsed% bounces were re-processed and %reidentified% bounces were re-identified',
            ['%reparsed%' => $reparsed, '%reidentified%' => $reidentified]
        ));
    }
}

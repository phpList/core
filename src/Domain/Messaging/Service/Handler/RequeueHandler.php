<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use DateInterval;
use DateTime;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RequeueHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function handle(Message $campaign, ?OutputInterface $output = null): bool
    {
        $schedule = $campaign->getSchedule();
        $interval = $schedule->getRequeueInterval() ?? 0;
        $until = $schedule->getRequeueUntil();

        if ($interval <= 0) {
            return false;
        }
        $now = new DateTime();
        if ($until instanceof DateTime && $now > $until) {
            return false;
        }

        $embargoIsInFuture = $schedule->getEmbargo() instanceof DateTime && $schedule->getEmbargo() > new DateTime();
        $base = $embargoIsInFuture ? clone $schedule->getEmbargo() : new DateTime();
        $next = (clone $base)->add(new DateInterval('PT' . max(1, $interval) . 'M'));
        if ($until instanceof DateTime && $next > $until) {
            return false;
        }

        $schedule->setEmbargo($next);
        $campaign->setSchedule($schedule);
        $campaign->getMetadata()->setStatus(MessageStatus::Submitted);

        $output?->writeln($this->translator->trans(
            'Requeued campaign; next embargo at %time%',
            ['%time%' => $next->format(DateTime::ATOM)],
        ));
        $this->logger->info('Campaign requeued with new embargo', [
            'campaign_id' => $campaign->getId(),
            'embargo' => $next->format(DateTime::ATOM),
        ]);

        return true;
    }
}

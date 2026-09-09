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
    /**
     * Fallback delay (minutes) used when a campaign stops early (time limit, domain throttle,
     * etc.) but has no explicit requeueInterval configured. requeueInterval/requeueUntil control
     * *how long* to wait before resuming, not *whether* to resume: a campaign that stopped early
     * must always be retried, mirroring phplist3's unconditional "don't mark sent while anything
     * failed/was throttled" guard - it must never be silently marked Sent with recipients still
     * unprocessed. requeueUntil remains a legitimate opt-out (a real deadline).
     */
    private const DEFAULT_REQUEUE_INTERVAL_MINUTES = 1;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function handle(Message $campaign, ?OutputInterface $output = null): bool
    {
        $schedule = $campaign->getSchedule();
        $interval = $schedule->getRequeueInterval() ?? 0;
        if ($interval <= 0) {
            $interval = self::DEFAULT_REQUEUE_INTERVAL_MINUTES;
        }
        $until = $schedule->getRequeueUntil();

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

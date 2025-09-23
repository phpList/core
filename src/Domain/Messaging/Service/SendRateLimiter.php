<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateInterval;
use DateTimeImmutable;
use PhpList\Core\Domain\Common\IspRestrictionsProvider;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Encapsulates batching and throttling logic for sending emails respecting
 * configuration and ISP restrictions.
 */
class SendRateLimiter
{
    private int $batchSize;
    private int $batchPeriod;
    private int $throttleSec;
    private int $sentInBatch = 0;
    private float $batchStart = 0.0;
    private bool $initializedFromHistory = false;

    public function __construct(
        private readonly IspRestrictionsProvider $ispRestrictionsProvider,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly TranslatorInterface $translator,
        private readonly ?int $mailqueueBatchSize = null,
        private readonly ?int $mailqueueBatchPeriod = null,
        private readonly ?int $mailqueueThrottle = null,
    ) {
        $this->initializeLimits();
    }

    private function initializeLimits(): void
    {
        $isp = $this->ispRestrictionsProvider->load();

        $cfgBatch = $this->mailqueueBatchSize ?? 0;
        $ispMax = isset($isp->maxBatch) ? (int)$isp->maxBatch : null;

        $cfgPeriod = $this->mailqueueBatchPeriod ?? 0;
        $ispMinPeriod = $isp->minBatchPeriod ?? 0;

        $cfgThrottle = $this->mailqueueThrottle ?? 0;
        $ispMinThrottle = (int)($isp->minThrottle ?? 0);

        if ($cfgBatch <= 0) {
            $this->batchSize = $ispMax !== null ? max(0, $ispMax) : 0;
        } else {
            $this->batchSize = $ispMax !== null ? min($cfgBatch, max(1, $ispMax)) : $cfgBatch;
        }
        $this->batchPeriod = max(0, $cfgPeriod, $ispMinPeriod);
        $this->throttleSec = max(0, $cfgThrottle, $ispMinThrottle);

        $this->sentInBatch = 0;
        $this->batchStart = microtime(true);
        $this->initializedFromHistory = false;
    }

    /**
     * Call before attempting to send another message. It will sleep if needed to
     * respect batch limits. Returns true when it's okay to proceed.
     */
    public function awaitTurn(?OutputInterface $output = null): bool
    {
        if (!$this->initializedFromHistory && $this->batchSize > 0 && $this->batchPeriod > 0) {
            $since = (new DateTimeImmutable())->sub(new DateInterval('PT' . $this->batchPeriod . 'S'));
            $alreadySent = $this->userMessageRepository->countSentSince($since);
            $this->sentInBatch = max($this->sentInBatch, $alreadySent);
            $this->initializedFromHistory = true;
        }

        if ($this->batchSize > 0 && $this->batchPeriod > 0 && $this->sentInBatch >= $this->batchSize) {
            $elapsed = microtime(true) - $this->batchStart;
            $remaining = (int)ceil($this->batchPeriod - $elapsed);
            if ($remaining > 0) {
                $output?->writeln($this->translator->trans(
                    'Batch limit reached, sleeping %sleep%s to respect MAILQUEUE_BATCH_PERIOD',
                    ['%sleep%' => $remaining]
                ));
                sleep($remaining);
            }
            $this->batchStart = microtime(true);
            $this->sentInBatch = 0;
            $this->initializedFromHistory = false;
        }

        return true;
    }

    /**
     * Call after a successful sending to update counters and apply per-message throttle.
     */
    public function afterSend(): void
    {
        $this->sentInBatch++;
        if ($this->throttleSec > 0) {
            sleep($this->throttleSec);
        }
    }
}

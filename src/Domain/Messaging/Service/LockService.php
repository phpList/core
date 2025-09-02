<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Repository\SendProcessRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\SendProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\UnicodeString;

class LockService
{
    private SendProcessRepository $repo;
    private SendProcessManager $manager;
    private LoggerInterface $logger;
    private int $staleAfterSeconds;
    private int $sleepSeconds;
    private int $maxWaitCycles;

    public function __construct(
        SendProcessRepository $repo,
        SendProcessManager $manager,
        LoggerInterface $logger,
        int $staleAfterSeconds = 600,
        int $sleepSeconds = 20,
        int $maxWaitCycles = 10
    ) {
        $this->repo = $repo;
        $this->manager = $manager;
        $this->logger = $logger;
        $this->staleAfterSeconds = $staleAfterSeconds;
        $this->sleepSeconds = $sleepSeconds;
        $this->maxWaitCycles = $maxWaitCycles;
    }

    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function acquirePageLock(
        string $page,
        bool $force = false,
        bool $isCli = false,
        bool $multiSend = false,
        int $maxSendProcesses = 1,
        ?string $clientIp = null,
    ): ?int {
        $page = $this->sanitizePage($page);
        $max = $this->resolveMax($isCli, $multiSend, $maxSendProcesses);

        if ($force) {
            $this->logger->info('Force set, killing other send processes (deleting lock rows).');
            $this->repo->deleteByPage($page);
        }

        $waited = 0;

        while (true) {
            $count = $this->repo->countAliveByPage($page);
            $running = $this->manager->findNewestAliveWithAge($page);

            if ($count >= $max) {
                if ($this->tryStealIfStale($running)) {
                    continue;
                }

                $this->logAliveAge($running);

                if ($isCli) {
                    $this->logger->info("Running commandline, quitting. We'll find out what to do in the next run.");

                    return null;
                }

                if (!$this->waitOrGiveUp($waited)) {
                    $this->logger->info('We have been waiting too long, I guess the other process is still going ok');

                    return null;
                }

                continue;
            }

            $processIdentifier = $this->buildProcessIdentifier($isCli, $clientIp);
            $sendProcess = $this->manager->create($page, $processIdentifier);

            return $sendProcess->getId();
        }
    }

    public function keepLock(int $processId): void
    {
        $this->repo->incrementAlive($processId);
    }

    public function checkLock(int $processId): int
    {
        return $this->repo->getAliveValue($processId);
    }

    public function release(int $processId): void
    {
        $this->repo->markDeadById($processId);
    }

    private function sanitizePage(string $page): string
    {
        $unicodeString = new UnicodeString($page);
        $clean = preg_replace('/\W/', '', (string) $unicodeString);

        return $clean === '' ? 'default' : $clean;
    }

    private function resolveMax(bool $isCli, bool $multiSend, int $maxSendProcesses): int
    {
        if (!$isCli) {
            return 1;
        }
        return $multiSend ? \max(1, $maxSendProcesses) : 1;
    }

    /**
     * Returns true if it detected a stale process and killed it (so caller should loop again).
     *
     * @param array{id?: int, age?: int}|null $running
     */
    private function tryStealIfStale(?array $running): bool
    {
        $age = (int)($running['age'] ?? 0);
        if ($age > $this->staleAfterSeconds && isset($running['id'])) {
            $this->repo->markDeadById((int)$running['id']);

            return true;
        }

        return false;
    }

    /**
     * @param array{id?: int, age?: int}|null $running
     */
    private function logAliveAge(?array $running): void
    {
        $age = (int)($running['age'] ?? 0);
        $this->logger->info(
            \sprintf(
                'A process for this page is already running and it was still alive %d seconds ago',
                $age
            )
        );
    }

    /**
     * Sleeps once and increments $waited. Returns false if we exceeded max wait cycles.
     */
    private function waitOrGiveUp(int &$waited): bool
    {
        $this->logger->info(\sprintf('Sleeping for %d seconds, aborting will quit', $this->sleepSeconds));
        \sleep($this->sleepSeconds);
        $waited++;
        return $waited <= $this->maxWaitCycles;
    }

    private function buildProcessIdentifier(bool $isCli, ?string $clientIp): string
    {
        if ($isCli) {
            $host = \php_uname('n') ?: 'localhost';
            return $host . ':' . \getmypid();
        }
        return $clientIp ?? '0.0.0.0';
    }
}

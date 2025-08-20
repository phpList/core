<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Repository\SendProcessRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\SendProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\UnicodeString;

class LockService
{
    public function __construct(
        private readonly SendProcessRepository $repo,
        private readonly SendProcessManager $manager,
        private readonly LoggerInterface $logger,
        private readonly int $staleAfterSeconds = 600,
        private readonly int $sleepSeconds = 20,
        private readonly int $maxWaitCycles = 10
    ) {}

    /**
     * Acquire a per-page lock (phpList getPageLock behavior).
     *
     * @return int|null  inserted row id when acquired; null if we gave up
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
        $max  = $isCli ? ($multiSend ? max(1, $maxSendProcesses) : 1) : 1;

        if ($force) {
            $this->logger->info('Force set, killing other send processes (deleting lock rows).');
            $this->repo->deleteByPage($page);
        }

        $waited = 0;
        while (true) {
            $count = $this->repo->countAliveByPage($page);
            $running = $this->manager->findNewestAliveWithAge($page);

            if ($count >= $max) {
                $age = (int)($running['age'] ?? 0);

                if ($age > $this->staleAfterSeconds && isset($running['id'])) {
                    $this->repo->markDeadById((int)$running['id']);

                    continue;
                }

                $this->logger->info(sprintf(
                    'A process for this page is already running and it was still alive %d seconds ago',
                    $age
                ));

                if ($isCli) {
                    $this->logger->info("Running commandline, quitting. We'll find out what to do in the next run.");
                    return null;
                }

                $this->logger->info('Sleeping for 20 seconds, aborting will quit');
                sleep($this->sleepSeconds);

                if (++$waited > $this->maxWaitCycles) {
                    $this->logger->info('We have been waiting too long, I guess the other process is still going ok');
                    return null;
                }

                continue;
            }

            $processIdentifier = $isCli
                ? (php_uname('n') ?: 'localhost') . ':' . getmypid()
                : ($clientIp ?? '0.0.0.0');

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
        $u = new UnicodeString($page);
        $clean = preg_replace('/\W/', '', (string)$u);
        return $clean === '' ? 'default' : $clean;
    }
}

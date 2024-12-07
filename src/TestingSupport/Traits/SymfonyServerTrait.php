<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

use InvalidArgumentException;
use PhpList\Core\Core\ApplicationStructure;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Trait for running the Symfony server in the background.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait SymfonyServerTrait
{
    private ?Process $serverProcess = null;

    private static string $lockFileName = '.web-server-pid';
    private static int $maximumWaitTimeForServerLockFile = 5000000;
    private static int $waitTimeBetweenServerCommands = 50000;

    private static ?ApplicationStructure $applicationStructure = null;

    /**
     * Starts the Symfony server.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    protected function startSymfonyServer(): void
    {
        if ($this->lockFileExists()) {
            throw new RuntimeException(
                sprintf(
                    'The server lock file "%s" already exists.',
                    self::$lockFileName
                )
            );
        }

        $this->serverProcess = new Process(
            $this->getSymfonyServerStartCommand(),
            $this->getApplicationRoot()
        );
        $this->serverProcess->start();

        usleep(self::$waitTimeBetweenServerCommands);
        $this->waitForServerLockFileToAppear();
        usleep(self::$waitTimeBetweenServerCommands);
    }

    private function lockFileExists(): bool
    {
        return file_exists($this->getFullLockFilePath());
    }

    protected function getBaseUrl(): string
    {
        if (!$this->lockFileExists()) {
            throw new RuntimeException('Lock file does not exist. Is the server running?');
        }

        $port = file_get_contents($this->getFullLockFilePath());
        if ($port === false) {
            throw new RuntimeException('Failed to read the lock file.');
        }

        return sprintf('http://localhost:%s', trim($port));
    }

    private function waitForServerLockFileToAppear(): void
    {
        $currentWaitTime = 0;
        while (!$this->lockFileExists() && $currentWaitTime < static::$maximumWaitTimeForServerLockFile) {
            $process = new Process(['symfony', 'server:status', '--no-ansi']);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                if (preg_match('/Listening on (http[s]?:\/\/127\.0\.0\.1:(\d+))/', $output, $matches)) {
                    $port = $matches[2];
                    file_put_contents(self::$lockFileName, trim($port));
                }
            }
            usleep(static::$waitTimeBetweenServerCommands);
            $currentWaitTime += static::$waitTimeBetweenServerCommands;
        }

        if (!$this->lockFileExists()) {
            throw new RuntimeException(
                'There is no symfony server lock file "' . static::$lockFileName . '".',
                1516625236
            );
        }
    }

    private function getFullLockFilePath(): string
    {
        return sprintf('%s/%s', $this->getApplicationRoot(), self::$lockFileName);
    }

    protected function stopSymfonyServer(): void
    {
        if ($this->serverProcess && $this->serverProcess->isRunning()) {
            $this->serverProcess->stop();
        }

        if ($this->lockFileExists()) {
            unlink($this->getFullLockFilePath());
        }
    }

    private function getSymfonyServerStartCommand(): array
    {
        $documentRoot = $this->getApplicationRoot() . '/public/';
        $this->checkDocumentRoot($documentRoot);

        return [
            'symfony',
            'server:start',
            '--daemon',
        ];
    }

    protected function getApplicationRoot(): string
    {
        if (self::$applicationStructure === null) {
            self::$applicationStructure = new ApplicationStructure();
        }

        return self::$applicationStructure->getApplicationRoot();
    }

    private function checkDocumentRoot(string $documentRoot): void
    {
        if (!file_exists($documentRoot)) {
            throw new RuntimeException(sprintf('The document root "%s" does not exist.', $documentRoot));
        }

        if (!is_dir($documentRoot)) {
            throw new RuntimeException(sprintf('The document root "%s" exists but is not a directory.', $documentRoot));
        }

        if (!is_readable($documentRoot)) {
            throw new RuntimeException(sprintf('The document root "%s" is not readable.', $documentRoot));
        }
    }
}

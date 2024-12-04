<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

use InvalidArgumentException;
use PhpList\Core\Core\ApplicationStructure;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Trait for running the Symfony server in the background.
 */
trait SymfonyServerTrait
{
    private ?Process $serverProcess = null;

    private static array $validEnvironments = ['test', 'dev', 'prod'];
    private static string $lockFileName = '.web-server-pid';
    private static int $maximumWaitTimeForServerLockFile = 5000000; // microseconds
    private static int $waitTimeBetweenServerCommands = 50000; // microseconds

    private static ?ApplicationStructure $applicationStructure = null;

    /**
     * Starts the Symfony server.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    protected function startSymfonyServer(string $environment): void
    {
        if (!in_array($environment, self::$validEnvironments, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid environment.', $environment));
        }

        if ($this->lockFileExists()) {
            throw new RuntimeException(
                sprintf(
                    'The server lock file "%s" already exists. A Symfony server might already be running. Please stop the server or delete the lock file.',
                    self::$lockFileName
                )
            );
        }

        $this->serverProcess = new Process(
            $this->getSymfonyServerStartCommand($environment),
            $this->getApplicationRoot()
        );

        try {
            $this->serverProcess->start();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('Failed to start the Symfony server.', 0, $exception);
        }

        $this->waitForServerLockFileToAppear();
        usleep(75000); // Allow the server time to initialize
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

        while (!$this->lockFileExists() && $currentWaitTime < self::$maximumWaitTimeForServerLockFile) {
            usleep(self::$waitTimeBetweenServerCommands);
            $currentWaitTime += self::$waitTimeBetweenServerCommands;
        }

        if (!$this->lockFileExists()) {
            throw new RuntimeException(sprintf('Symfony server lock file "%s" did not appear.', self::$lockFileName));
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

    private function getSymfonyServerStartCommand(string $environment): array
    {
        $documentRoot = $this->getApplicationRoot() . '/public/';
        $this->checkDocumentRoot($documentRoot);

        return [
            'symfony',
            'server:start',
            '--daemon',
            '--document-root=' . $documentRoot,
            '--env=' . $environment,
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

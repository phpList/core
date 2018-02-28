<?php
declare(strict_types=1);

namespace PhpList\PhpList4\TestingSupport\Traits;

use PhpList\PhpList4\Core\ApplicationStructure;
use Symfony\Bundle\WebServerBundle\WebServer;
use Symfony\Component\Process\Process;

/**
 * Trait for running the Symfony server in the background.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait SymfonyServerTrait
{
    /**
     * @var Process
     */
    private $serverProcess = null;

    /**
     * @var string[]
     */
    private static $validEnvironments = ['test', 'dev', 'prod'];

    /**
     * @var string
     */
    private static $lockFileName = '.web-server-pid';

    /**
     * @var int microseconds
     */
    private static $maximumWaitTimeForServerLockFile = 5000000;

    /**
     * @var int microseconds
     */
    private static $waitTimeBetweenServerCommands = 50000;

    /**
     * @var ApplicationStructure
     */
    private static $applicationStructure = null;

    /**
     * Starts the symfony server. The resulting base URL then can be retrieved using getBaseUrl().
     *
     * @see getBaseUrl
     *
     * @param string $environment
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function startSymfonyServer(string $environment)
    {
        if (!\in_array($environment, static::$validEnvironments, true)) {
            throw new \InvalidArgumentException('"' . $environment . '" is not a valid environment.', 1516284149);
        }
        if ($this->lockFileExists()) {
            throw new \RuntimeException(
                'The server lock file "' . static::$lockFileName . '" already exists. ' .
                'Most probably, a symfony server already is running. ' .
                'Please stop the symfony server or delete the lock file.',
                1516622609
            );
        }

        $this->serverProcess = new Process(
            $this->getSymfonyServerStartCommand($environment),
            $this->getApplicationRoot()
        );
        $this->serverProcess->start();

        $this->waitForServerLockFileToAppear();
        // Give the server some more time to initialize so it will accept connections.
        \usleep(75000);
    }

    /**
     * @return bool
     */
    private function lockFileExists(): bool
    {
        return \file_exists($this->getFullLockFilePath());
    }

    /**
     * @return string the base URL (including protocol and port, but without the trailing slash)
     */
    protected function getBaseUrl(): string
    {
        return 'http://' . \file_get_contents($this->getFullLockFilePath());
    }

    /**
     * Waits for the server lock file to appear, and throws an exception if the file has not appeared after the
     * maximum wait time.
     *
     * If the file already exists, this method returns instantly.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function waitForServerLockFileToAppear()
    {
        $currentWaitTime = 0;
        while (!$this->lockFileExists() && $currentWaitTime < static::$maximumWaitTimeForServerLockFile) {
            \usleep(static::$waitTimeBetweenServerCommands);
            $currentWaitTime += static::$waitTimeBetweenServerCommands;
        }

        if (!$this->lockFileExists()) {
            throw new \RuntimeException(
                'There is no symfony server lock file "' . static::$lockFileName . '".',
                1516625236
            );
        }
    }

    /**
     * @return string
     */
    private function getFullLockFilePath(): string
    {
        return $this->getApplicationRoot() . '/' . static::$lockFileName;
    }

    /**
     * @return void
     */
    protected function stopSymfonyServer()
    {
        if ($this->lockFileExists()) {
            $server = new WebServer();
            $server->stop($this->getFullLockFilePath());
        }
        if ($this->serverProcess !== null && $this->serverProcess->isRunning()) {
            $this->serverProcess->stop();
        }
    }

    /**
     * @param string $environment
     *
     * @return string
     */
    private function getSymfonyServerStartCommand(string $environment): string
    {
        $documentRoot = $this->getApplicationRoot() . '/public/';
        $this->checkDocumentRoot($documentRoot);

        return sprintf(
            '%1$s server:start -d %2$s --env=%3$s',
            $this->getApplicationRoot() . '/bin/console',
            $documentRoot,
            $environment
        );
    }

    /**
     * @return string
     */
    protected function getApplicationRoot(): string
    {
        if (static::$applicationStructure === null) {
            static::$applicationStructure = new ApplicationStructure();
        }

        return static::$applicationStructure->getApplicationRoot();
    }

    /**
     * Checks that $documentRoot exists, is a directory and readable.
     *
     * @param string $documentRoot
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function checkDocumentRoot(string $documentRoot)
    {
        if (!\file_exists($documentRoot)) {
            throw new \RuntimeException('The document root "' . $documentRoot . '" does not exist.', 1499513246);
        }
        if (!\is_dir($documentRoot)) {
            throw new \RuntimeException(
                'The document root "' . $documentRoot . '" exists, but is no directory.',
                1499513263
            );
        }
        if (!\is_readable($documentRoot)) {
            throw new \RuntimeException(
                'The document root "' . $documentRoot . '" exists and is a directory, but is not readable.',
                1499513279
            );
        }
    }
}

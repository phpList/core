<?php

declare(strict_types=1);

namespace PhpList\Core\Core;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

/**
 * This class bootstraps the phpList core system.
 *
 * Include it from the entry point and call Bootstrap::getInstance() to get an instance,
 * and $bootstrap->setEnvironment($environment) if you would like to run the application in
 * the development or testing environment. (For the production environment,
 * the setEnvironment call is not needed).
 *
 * After that, call $bootstrap->configure() and $bootstrap->dispatch().
 *
 * This class is the only "real" singleton in the system. All other singletons will use
 * Symfony dependency injection.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Bootstrap
{
    /**
     * @var Bootstrap|null
     */
    private static ?Bootstrap $instance = null;

    /**
     * @var bool
     */
    private bool $isConfigured = false;

    /**
     * @var string
     */
    private string $environment = Environment::DEFAULT_ENVIRONMENT;

    /**
     * @var ApplicationKernel
     */
    private $applicationKernel = null;

    /**
     * @var ApplicationStructure
     */
    private ApplicationStructure $applicationStructure;

    /**
     * Protected constructor to avoid direct instantiation of this class.
     *
     * Please use getInstance instead.
     */
    protected function __construct()
    {
        $this->applicationStructure = new ApplicationStructure();
    }

    /**
     * Disable direct cloning of this object.
     */
    protected function __clone()
    {
    }

    /**
     * Returns 'this' as singleton.
     *
     * @return Bootstrap
     */
    public static function getInstance(): Bootstrap
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Purges the singleton instance.
     *
     * Note: This method is intended to be used for tests only.
     *
     * @return void
     */
    public static function purgeInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @param string $environment must be one of the Environment::* constants
     *
     * @return Bootstrap fluent interface
     *
     * @throws UnexpectedValueException
     */
    public function setEnvironment(string $environment): Bootstrap
    {
        Environment::validateEnvironment($environment);
        $this->environment = $environment;

        return $this;
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * @return bool
     */
    private function isSymfonyDebugModeEnabled(): bool
    {
        return $this->environment !== Environment::PRODUCTION;
    }

    /**
     * @return bool
     */
    private function isDebugEnabled(): bool
    {
        return $this->environment !== Environment::PRODUCTION;
    }

    /**
     * Checks that the application is running on a local testing machine or via CLI, not on a production server.
     *
     * If a production server is detected, a 403 header is sent and execution is stopped.
     *
     * @SuppressWarnings("PHPMD.ExitExpression")
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @return Bootstrap fluent interface
     */
    public function ensureDevelopmentOrTestingEnvironment(): static
    {
        $usesProxy = isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $isOnCli = PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server';
        $isLocalRequest = isset($_SERVER['REMOTE_ADDR'])
            && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true);
        if ($usesProxy || (!$isOnCli && !$isLocalRequest)) {
            header('HTTP/1.0 403 Forbidden');
            exit('You are not allowed to access this file.');
        }

        return $this;
    }

    /**
     * Main entry point called at every request usually from global scope. Checks if everything is correct
     * and loads the configuration.
     *
     * @return Bootstrap fluent interface
     */
    public function configure(): Bootstrap
    {
        $this->isConfigured = true;

        return $this->configureDebugging()
            ->configureApplicationKernel();
    }

    /**
     * Makes sure that configure has been called before.
     *
     * @return void
     *
     * @throws RuntimeException if configure has not been called before
     */
    private function assertConfigureHasBeenCalled(): void
    {
        if (!$this->isConfigured) {
            throw new RuntimeException('Please call configure() first.', 1501170550);
        }
    }

    /**
     * Dispatches the current HTTP request (if there is any).
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     *
     * @return null
     *
     * @throws RuntimeException if configure has not been called before
     * @throws Exception
     */
    public function dispatch()
    {
        $this->assertConfigureHasBeenCalled();

        $request = Request::createFromGlobals();
        $response = $this->getApplicationKernel()->handle($request);
        $response->send();
        $this->getApplicationKernel()->terminate($request, $response);

        return null;
    }

    /**
     * @return Bootstrap fluent interface
     */
    private function configureDebugging(): Bootstrap
    {
        if ($this->isDebugEnabled()) {
            ErrorHandler::register();
        }

        return $this;
    }

    /**
     * @return Bootstrap fluent interface
     */
    private function configureApplicationKernel(): Bootstrap
    {
        $this->applicationKernel = new ApplicationKernel(
            $this->getEnvironment(),
            $this->isSymfonyDebugModeEnabled()
        );

        return $this;
    }

    /**
     * @return ApplicationKernel
     *
     * @throws RuntimeException if configure has not been called before
     */
    public function getApplicationKernel(): ApplicationKernel
    {
        $this->assertConfigureHasBeenCalled();

        return $this->applicationKernel;
    }

    /**
     * Returns the Symfony DI container.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        $this->applicationKernel->boot();

        return $this->getApplicationKernel()->getContainer();
    }

    /**
     * @return EntityManagerInterface
     *
     * @throws RuntimeException if configure has not been called before
     */
    public function getEntityManager(): EntityManagerInterface
    {
        $this->assertConfigureHasBeenCalled();

        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * Returns the absolute path to the application root.
     *
     * When core is installed as a dependency (library) of an application, this method will return
     * the application's package path.
     *
     * When phpList4-core is installed stand-alone (i.e., as an application - usually only for testing),
     * this method will be the phpList4-core package path.
     *
     * @return string the absolute path without the trailing slash.
     *
     * @throws RuntimeException if there is no composer.json in the application root
     */
    public function getApplicationRoot(): string
    {
        return $this->applicationStructure->getApplicationRoot();
    }
}

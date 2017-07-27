<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Core;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class bootstraps the phpList core system.
 *
 * Include it from the entry point and call Bootstrap::getInstance() to get an instance,
 * and $bootstrap->setApplicationContext($context) if you would like to run the application in
 * the development or testing context. (For the production context, the setApplicationContext call
 * is not needed).
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
     * application context for running a live site
     *
     * @var string
     */
    const APPLICATION_CONTEXT_PRODUCTION = 'prod';

    /**
     * application context for developing locally
     *
     * @var string
     */
    const APPLICATION_CONTEXT_DEVELOPMENT = 'dev';

    /**
     * application context for running automated tests
     *
     * @var string
     */
    const APPLICATION_CONTEXT_TESTING = 'test';

    /**
     * @var string
     */
    const DEFAULT_APPLICATION_CONTEXT = self::APPLICATION_CONTEXT_PRODUCTION;

    /**
     * @var string[]
     */
    private static $validApplicationContexts = [
        self::APPLICATION_CONTEXT_PRODUCTION,
        self::APPLICATION_CONTEXT_DEVELOPMENT,
        self::APPLICATION_CONTEXT_TESTING,
    ];

    /**
     * @var Bootstrap|null
     */
    private static $instance = null;

    /**
     * @var bool
     */
    private $isConfigured = false;

    /**
     * @var string
     */
    private $applicationContext = self::DEFAULT_APPLICATION_CONTEXT;

    /**
     * @var EntityManager
     */
    private $entityManager = null;

    /**
     * @var ApplicationKernel
     */
    private $applicationKernel = null;

    /**
     * Protected constructor to avoid direct instantiation of this class.
     *
     * Please use getInstance instead.
     */
    protected function __construct()
    {
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
        if (static::$instance === null) {
            self::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Purges the singleton instance.
     *
     * Note: This method is intended to be used for tests only.
     *
     * @return void
     */
    public static function purgeInstance()
    {
        self::$instance = null;
    }

    /**
     * @param string $context must be one of the APPLICATION_CONTEXT_* constants
     *
     * @return Bootstrap fluent interface
     *
     * @throws \UnexpectedValueException
     */
    public function setApplicationContext(string $context): Bootstrap
    {
        if (!in_array($context, self::$validApplicationContexts, true)) {
            throw new \UnexpectedValueException(
                '$context must be one of "Production", "Development", or "Testing", but actually is: ' . $context,
                1499112172108
            );
        }

        $this->applicationContext = $context;

        return $this;
    }

    /**
     * @return string
     */
    public function getApplicationContext(): string
    {
        return $this->applicationContext;
    }

    /**
     * @return bool
     */
    private function isDoctrineOrmDevelopmentModeEnabled(): bool
    {
        return $this->applicationContext !== self::APPLICATION_CONTEXT_PRODUCTION;
    }

    /**
     * @return bool
     */
    private function isSymfonyDebugModeEnabled(): bool
    {
        return $this->applicationContext !== self::APPLICATION_CONTEXT_PRODUCTION;
    }

    /**
     * @return bool
     */
    private function isDebugEnabled(): bool
    {
        return $this->applicationContext !== self::APPLICATION_CONTEXT_PRODUCTION;
    }

    /**
     * Checks that the application is not running on a production server, but on a local
     * machine or via CLI.
     *
     * If a production server is detected, a 403 header is sent and execution is stopped.
     *
     * @SuppressWarnings("PHPMD.ExitExpression")
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @return Bootstrap fluent interface
     */
    public function preventProductionEnvironment(): Bootstrap
    {
        $usesProxy = isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $isOnCli = PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server';
        $isLocalRequest = isset($_SERVER['REMOTE_ADDR'])
            && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true);
        if ($usesProxy || (!$isOnCli && $isLocalRequest)) {
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
            ->configureDoctrineOrm()
            ->configureApplicationKernel();
    }

    /**
     * Makes sure that configure has been called before.
     *
     * @return void
     *
     * @throws \RuntimeException if configure has not been called before
     */
    private function assertConfigureHasBeenCalled()
    {
        if (!$this->isConfigured) {
            throw new \RuntimeException('Please call configure() first.', 1501170550897);
        }
    }

    /**
     * Dispatches the current HTTP request (if there is any).
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     *
     * @return null
     *
     * @throws \RuntimeException if configure has not been called before
     * @throws \Exception
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
            Debug::enable();
        }

        return $this;
    }

    /**
     * @return Bootstrap fluent interface
     */
    private function configureDoctrineOrm(): Bootstrap
    {
        $packageRootPath = dirname(__DIR__, 2);
        $domainModelPath = $packageRootPath . 'Classes/Domain/Model/';
        $domainModelPaths = [$domainModelPath];

        // The getenv calls will be replaced by YAML configuration later
        // (with the option to use environment variables as overrides).
        $databaseConfiguration = [
            'driver' => 'pdo_mysql',
            'user' => getenv('PHPLIST_DATABASE_USER'),
            'password' => getenv('PHPLIST_DATABASE_PASSWORD'),
            'dbname' => getenv('PHPLIST_DATABASE_NAME'),
        ];

        $ormConfiguration = Setup::createAnnotationMetadataConfiguration(
            $domainModelPaths,
            $this->isDoctrineOrmDevelopmentModeEnabled()
        );
        $this->entityManager = EntityManager::create($databaseConfiguration, $ormConfiguration);

        return $this;
    }

    /**
     * @return Bootstrap fluent interface
     */
    private function configureApplicationKernel(): Bootstrap
    {
        $this->applicationKernel = new ApplicationKernel(
            $this->getApplicationContext(),
            $this->isSymfonyDebugModeEnabled()
        );
        $this->applicationKernel->setProjectDir($this->getApplicationRoot());

        return $this;
    }

    /**
     * @return EntityManagerInterface
     *
     * @throws \RuntimeException if configure has not been called before
     */
    public function getEntityManager(): EntityManagerInterface
    {
        $this->assertConfigureHasBeenCalled();

        return $this->entityManager;
    }

    /**
     * @return ApplicationKernel
     *
     * @throws \RuntimeException if configure has not been called before
     */
    public function getApplicationKernel(): ApplicationKernel
    {
        $this->assertConfigureHasBeenCalled();

        return $this->applicationKernel;
    }

    /**
     * Returns the absolute path to the application root.
     *
     * When phplist4-core is installed as a dependency (library) of an application, this method will return
     * the application's package path.
     *
     * When phpList4-core is installed stand-alone (i.e., as an application - usually only for testing),
     * this method will be the phpList4-core package path.
     *
     * @return string the absolute path without the trailing slash.
     *
     * @throws \RuntimeException if there is no composer.json in the application root
     */
    public function getApplicationRoot(): string
    {
        $corePackagePath = dirname(__DIR__, 2);
        $corePackageIsRootPackage = interface_exists('PhpList\\PhpList4\\Tests\\Support\\Interfaces\\TestMarker');
        if ($corePackageIsRootPackage) {
            $applicationRoot = $corePackagePath;
        } else {
            // remove 3 more path segments, i.e., "vendor/phplist/phplist4-core/"
            $corePackagePath = dirname($corePackagePath, 3);
            $applicationRoot = $corePackagePath;
        }

        if (!file_exists($applicationRoot . '/composer.json')) {
            throw new \RuntimeException(
                'There is no composer.json in the supposed application root "' . $applicationRoot . '".',
                1501169001588
            );
        }

        return $applicationRoot;
    }
}

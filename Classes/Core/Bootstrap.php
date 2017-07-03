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
    const APPLICATION_CONTEXT_PRODUCTION = 'Production';

    /**
     * application context for developing locally
     *
     * @var string
     */
    const APPLICATION_CONTEXT_DEVELOPMENT = 'Development';

    /**
     * application context for running automated tests
     *
     * @var string
     */
    const APPLICATION_CONTEXT_TESTING = 'Testing';

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
        return $this->configureDebugging()
            ->configureDoctrineOrm()
            ->configureApplicationKernel();
    }

    /**
     * Dispatches the current HTTP request (if there is any).
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     *
     * @return null
     *
     * @throws \Exception
     */
    public function dispatch()
    {
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

        return $this;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @return ApplicationKernel
     */
    public function getApplicationKernel(): ApplicationKernel
    {
        return $this->applicationKernel;
    }
}

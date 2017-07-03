<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Core;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Debug\Debug;

/**
 * This class bootstraps the phpList core system.
 *
 * Include it in from the entry point and call Bootstrap::getInstance()->configure().
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
     * Note: This method is intended to be use for tests only.
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
    private function isDebugEnabled(): bool
    {
        return $this->applicationContext !== self::APPLICATION_CONTEXT_PRODUCTION;
    }

    /**
     * Main entry point called at every request usually from global scope. Checks if everything is correct
     * and loads the configuration.
     *
     * @return Bootstrap fluent interface
     */
    public function configure(): Bootstrap
    {
        $this->configureDebugging();
        $this->configureDoctrineOrm();

        return $this;
    }

    /**
     * @return void
     */
    private function configureDebugging()
    {
        if ($this->isDebugEnabled()) {
            Debug::enable();
        }
    }

    /**
     * @return void
     */
    private function configureDoctrineOrm()
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
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}

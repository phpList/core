<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PHPUnit\DbUnit\Database\Connection;
use PHPUnit\DbUnit\DataSet\CsvDataSet;
use PHPUnit\DbUnit\Operation\Factory;
use PHPUnit\DbUnit\Operation\Operation;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is the base class for integration tests that use database records.
 *
 * Make sure to call parent::setUp() first thing in your setUp method.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
abstract class AbstractDatabaseTest extends TestCase
{
    use TestCaseTrait;

    /**
     * @var Connection
     */
    private $databaseConnection = null;

    /**
     * @var \PDO
     */
    private static $pdo = null;

    /**
     * @var CsvDataSet
     */
    private $dataSet = null;

    /**
     * @var Bootstrap
     */
    protected $bootstrap = null;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager = null;

    /**
     * @var ContainerInterface
     */
    protected $container = null;

    protected function setUp()
    {
        $this->initializeDatabaseTester();
        $this->bootstrap = Bootstrap::getInstance()->setEnvironment(Environment::TESTING)->configure();
        $this->container = $this->bootstrap->getContainer();
        $this->entityManager = $this->bootstrap->getEntityManager();
        self::assertTrue($this->entityManager->isOpen());
    }

    /**
     * Initializes the CSV data set and the database tester.
     *
     * @return void
     */
    protected function initializeDatabaseTester()
    {
        $this->dataSet = new CsvDataSet();

        $this->databaseTester = null;
        $this->getDatabaseTester()->setSetUpOperation($this->getSetUpOperation());
    }

    protected function tearDown()
    {
        $this->entityManager->close();
        $this->getDatabaseTester()->setTearDownOperation($this->getTearDownOperation());
        $this->getDatabaseTester()->setDataSet($this->getDataSet());
        $this->getDatabaseTester()->onTearDown();

        // Destroy the tester after the test is run to keep DB connections
        // from piling up.
        $this->databaseTester = null;

        Bootstrap::purgeInstance();
    }

    /**
     * Returns the database operation executed in test cleanup.
     *
     * @return Operation
     */
    protected function getTearDownOperation(): Operation
    {
        return Factory::TRUNCATE();
    }

    /**
     * Returns the test database connection.
     *
     * @return Connection
     */
    protected function getConnection(): Connection
    {
        if ($this->databaseConnection === null) {
            if (self::$pdo === null) {
                self::$pdo = new \PDO(
                    'mysql:dbname=' . getenv('PHPLIST_DATABASE_NAME'),
                    getenv('PHPLIST_DATABASE_USER'),
                    getenv('PHPLIST_DATABASE_PASSWORD')
                );
            }
            $this->databaseConnection = $this->createDefaultDBConnection(self::$pdo);
        }

        return $this->databaseConnection;
    }

    /**
     * Returns the test data set.
     *
     * Add data to in the individual test by calling $this->getDataSet()->addTable.
     *
     * @return CsvDataSet
     */
    protected function getDataSet(): CsvDataSet
    {
        return $this->dataSet;
    }

    /**
     * Applies all database changes on $this->dataSet.
     *
     * This methods needs to be called after the last addTable call in each test.
     *
     * @return void
     */
    protected function applyDatabaseChanges()
    {
        $this->getDatabaseTester()->setDataSet($this->getDataSet());
        $this->getDatabaseTester()->onSetUp();
    }

    /**
     * Marks the table with the given name as "touched", i.e., it will be truncated in the tearDown method.
     *
     * This is useful if the table gets populated only by the tested code instead of by using the addTable
     * and applyDatabaseChanges method.
     *
     * @param string $tableName
     *
     * @return void
     */
    protected function touchDatabaseTable(string $tableName)
    {
        $this->getDataSet()->addTable($tableName, __DIR__ . '/Fixtures/TouchTable.csv');
    }
}

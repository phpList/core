<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PHPUnit\DbUnit\Database\Connection;
use PHPUnit\DbUnit\DataSet\CsvDataSet;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * This is the base class for all repository integration tests.
 *
 * Make sure to call parent::setUp() first thing in your setUp method.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
abstract class AbstractRepositoryTest extends TestCase
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

    protected function setUp()
    {
        $this->initializeDatabaseTester();
        $this->bootstrap = Bootstrap::getInstance()->activateDevelopmentMode()->configure();
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
     * Sets the (private) ID of $subject.
     *
     * @param Identity $subject
     * @param int $id
     *
     * @return void
     */
    protected function setId(Identity $subject, int $id)
    {
        $reflectionObject = new \ReflectionObject($subject);
        $reflectionProperty = $reflectionObject->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($subject, $id);
    }
}

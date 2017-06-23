<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Domain\Model\Identity\AdministratorToken;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository;
use PHPUnit\DbUnit\Database\Connection;
use PHPUnit\DbUnit\DataSet\CsvDataSet;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepositoryTest extends TestCase
{
    use TestCaseTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_admintoken';

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
     * @var AdministratorTokenRepository
     */
    private $subject = null;

    protected function setUp()
    {
        $bootstrap = Bootstrap::getInstance()->configure();
        $this->subject = $bootstrap->getEntityManager()->getRepository(AdministratorToken::class);

        $this->dataSet = new CsvDataSet();

        $this->databaseTester = null;
        $this->getDatabaseTester()->setSetUpOperation($this->getSetUpOperation());
    }

    protected function tearDown()
    {
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
    private function applyDatabaseChanges()
    {
        $this->getDatabaseTester()->setDataSet($this->getDataSet());
        $this->getDatabaseTester()->onSetUp();
    }

    /**
     * Sets the (private) ID of $subject.
     *
     * @param AdministratorToken $subject
     * @param int $id
     *
     * @return void
     */
    private function setId(AdministratorToken $subject, int $id)
    {
        $reflectionObject = new \ReflectionObject($subject);
        $reflectionProperty = $reflectionObject->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($subject, $id);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorToken.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $expectedModel = new AdministratorToken();
        $this->setId($expectedModel, $id);
        $expectedModel->setExpiry(new \DateTime('2017-06-22 16:43:29'));
        $expectedModel->setKey('cfdf64eecbbf336628b0f3071adba762');

        $actualModel = $this->subject->find($id);

        self::assertEquals($expectedModel, $actualModel);
    }
}

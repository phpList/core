<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use InvalidArgumentException;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use RuntimeException;

/**
 * This trait provides support for integration tests involving database records.
 */
trait DatabaseTestTrait
{
    protected ?Bootstrap $bootstrap = null;
    protected ?EntityManagerInterface $entityManager = null;
    protected static $container;

    /**
     * Sets up the database test environment.
     */
    protected function setUpDatabaseTest(): void
    {
        $this->initializeBootstrap();
    }

    /**
     * Tears down the database test environment.
     */
    protected function tearDownDatabaseTest(): void
    {
        $this->entityManager?->clear();
        $this->entityManager?->close();
        $this->bootstrap = null;
        $this->entityManager = null;
    }

    /**
     * Initializes the Bootstrap and Doctrine EntityManager.
     *
     * @throws RuntimeException
     */
    private function initializeBootstrap(): void
    {
        $this->bootstrap = Bootstrap::getInstance()
            ->setEnvironment(Environment::TESTING)
            ->configure();

        $this->entityManager = $this->bootstrap->getEntityManager();

        if (!$this->entityManager->isOpen()) {
            throw new RuntimeException('The Doctrine EntityManager is not open.');
        }
    }

    /**
     * Loads data fixtures into the database.
     *
     * @param array $fixtures List of fixture classes to load
     * @throws InvalidArgumentException
     */
    protected function loadFixtures(array $fixtures): void
    {
        foreach ($fixtures as $fixture) {
            $fixtureInstance = new $fixture();
            if (!method_exists($fixtureInstance, 'load')) {
                throw new InvalidArgumentException(sprintf('Fixture %s must have a load() method.', $fixture));
            }

            $fixtureInstance->load($this->entityManager);
            $this->entityManager->flush();
        }
    }

    protected function loadSchema(): void
    {
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if ($this->entityManager->getConnection()->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->runForSqlite($metadata, $schemaTool);
        } else {
            $this->runForMySql($metadata, $schemaTool);
        }
    }

    private function runForMySql($metadata, $schemaTool): void
    {
        try {
            $schemaTool->createSchema($metadata);
        } catch (ToolsException $e) {
            $missing = $this->filterMissingTables($metadata);

            if ($missing !== []) {
                $schemaTool->createSchema($missing);
            }
        }
    }

    private function runForSqlite($metadata, $schemaTool): void
    {
        $missing = $this->filterMissingTables($metadata);

        if ($missing !== []) {
            try {
                $schemaTool->createSchema($missing);
            } catch (ToolsException $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * Creating tables one class at a time (rather than in a single createSchema() call for all
     * of them) would break foreign key ordering: a single-class createSchema() call emits that
     * class's own ADD CONSTRAINT statements immediately, which fails if a table it references
     * hasn't been (re)created yet. Passing the whole batch of missing classes to createSchema()
     * lets Doctrine order all CREATE TABLE statements before any ADD CONSTRAINT statements.
     */
    private function filterMissingTables(array $metadata): array
    {
        return array_values(array_filter(
            $metadata,
            fn ($classMetadata) => !$this->tableExistsIgnoringSchemaFilter($classMetadata->getTableName())
        ));
    }

    /**
     * Doesn't use the DBAL schema manager's tablesExist() here: the 'default' connection has
     * OnlyOrmTablesFilter registered as a doctrine.dbal.schema_filter, which hides tables mapped
     * from bundles outside the project namespace (e.g. TatevikGr\RssFeedBundle's phplist_item_data),
     * so tablesExist() would always report them as missing. Querying the platform's own table
     * catalog directly bypasses that filter.
     */
    private function tableExistsIgnoringSchemaFilter(string $tableName): bool
    {
        $connection = $this->entityManager->getConnection();

        if ($connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $count = $connection->fetchOne(
                'SELECT COUNT(*) FROM sqlite_master WHERE type = ? AND name = ?',
                ['table', $tableName]
            );
        } else {
            $count = $connection->fetchOne(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ? AND table_schema = DATABASE()',
                [$tableName]
            );
        }

        return (int) $count > 0;
    }
}

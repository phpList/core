<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

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

        try {
            $schemaTool->createSchema($metadata);
        } catch (ToolsException $e) {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();

            foreach ($metadata as $classMetadata) {
                $tableName = $classMetadata->getTableName();

                if (!$schemaManager->tablesExist([$tableName])) {
                    $schemaTool->createSchema([$classMetadata]);
                }
            }
        }
    }
}

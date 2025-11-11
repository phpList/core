<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

/**
 * Functional test for DynamicListAttrManager and DynamicListAttrRepository working together
 * with a real database connection and Symfony serializer.
 */
class DynamicListAttrManagerFunctionalTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ?DynamicListAttrRepository $dynamicListAttrRepo = null;
    private ?DynamicListAttrManager $manager = null;
    private ?DynamicListAttrTablesManager $tablesManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialize ORM and DB connection (SQLite/MySQL depending on config)
        $this->loadSchema();

        $connection = $this->entityManager->getConnection();
        $serializer = self::getContainer()->get('serializer');

        // Use real repository and manager services (but we construct them explicitly
        // to be independent from service wiring changes).
        $this->dynamicListAttrRepo = new DynamicListAttrRepository(
            connection: $connection,
            serializer: $serializer,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
        );

        $subscriberAttributeValueRepo = null;
        $subscriberAttributeValueRepo = self::getContainer()->get(SubscriberAttributeValueRepository::class);

        // Get tables manager from container for creating/ensuring dynamic tables
        $this->tablesManager = self::getContainer()->get(DynamicListAttrTablesManager::class);

        // Create manager with actual constructor signature
        $this->manager = new DynamicListAttrManager(
            dynamicListAttrRepository: $this->dynamicListAttrRepo,
            attributeValueRepo: $subscriberAttributeValueRepo
        );
    }

    public function testCreateInsertAndFetchOptionsEndToEnd(): void
    {
        // Create the dynamic options table for logical name "colors"
        $this->tablesManager->createOptionsTableIfNotExists('colours');

        // Insert options (including a duplicate name differing by case)
        $inserted = $this->manager->insertOptions('colours', [
            new DynamicListAttrDto(id: null, name: 'Red'),
            new DynamicListAttrDto(id: null, name: 'Blue'),
            // case-insensitive duplicate -> should be skipped
            new DynamicListAttrDto(id: null, name: 'red'),
        ]);

        // We expect exactly 2 distinct rows inserted
        Assert::assertCount(2, $inserted);
        $names = array_map(fn(DynamicListAttrDto $d) => $d->name, $inserted);
        sort($names);
        Assert::assertSame(['Blue', 'Red'], $names);

        // Now fetch through the repository
        $all = $this->dynamicListAttrRepo->getAll('colours');
        Assert::assertCount(2, $all);

        // Collect ids to test fetchOptionNames/fetchSingleOptionName
        $ids = array_map(fn(DynamicListAttrDto $d) => (int)$d->id, $inserted);
        sort($ids);

        $fetchedNames = $this->dynamicListAttrRepo->fetchOptionNames('colours', $ids);
        sort($fetchedNames);
        Assert::assertSame(['Blue', 'Red'], $fetchedNames);

        // Single fetch
        $oneName = $this->dynamicListAttrRepo->fetchSingleOptionName('colours', $ids[0]);
        Assert::assertNotNull($oneName);
        Assert::assertTrue(in_array($oneName, ['Blue', 'Red'], true));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->entityManager !== null) {
                $connection = $this->entityManager->getConnection();
                $fullTable = 'phplist_listattr_colours';
                // Use raw SQL for cleanup to avoid relying on SchemaManager in tests
                $connection->executeStatement('DROP TABLE IF EXISTS ' . $fullTable);
            }
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (Throwable $e) {
            // Ignore cleanup failures to not mask test results
        } finally {
            $this->dynamicListAttrRepo = null;
            $this->manager = null;
            $this->tablesManager = null;
            parent::tearDown();
        }
    }
}

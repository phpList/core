<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Dto\DynamicListAttrDto;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrManager;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Functional test for DynamicListAttrManager and DynamicListAttrRepository working together
 * with a real database connection and Symfony serializer.
 */
class DynamicListAttrManagerFunctionalTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ?DynamicListAttrRepository $repo = null;
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
        $this->repo = new DynamicListAttrRepository(
            connection: $connection,
            serializer: $serializer,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
        );

        // Get tables manager from container for creating/ensuring dynamic tables
        $this->tablesManager = self::getContainer()->get(DynamicListAttrTablesManager::class);

        // Create manager with actual constructor signature
        $this->manager = new DynamicListAttrManager(
            dynamicListAttrRepository: $this->repo,
            connection: $connection,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
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
        $all = $this->repo->getAll('colors');
        Assert::assertCount(2, $all);

        // Collect ids to test fetchOptionNames/fetchSingleOptionName
        $ids = array_map(fn(DynamicListAttrDto $d) => (int)$d->id, $inserted);
        sort($ids);

        $fetchedNames = $this->repo->fetchOptionNames('colors', $ids);
        sort($fetchedNames);
        Assert::assertSame(['Blue', 'Red'], $fetchedNames);

        // Single fetch
        $oneName = $this->repo->fetchSingleOptionName('colors', $ids[0]);
        Assert::assertNotNull($oneName);
        Assert::assertTrue(in_array($oneName, ['Blue', 'Red'], true));
    }
}

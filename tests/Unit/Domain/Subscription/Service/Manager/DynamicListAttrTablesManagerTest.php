<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Message\DynamicTableMessage;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\DynamicListAttrTablesManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DynamicListAttrTablesManagerTest extends TestCase
{
    private SubscriberAttributeDefinitionRepository&MockObject $definitionRepo;
    private MessageBusInterface&MockObject $bus;

    protected function setUp(): void
    {
        $this->definitionRepo = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturnCallback(function ($message) {
            return new Envelope($message);
        });
    }

    private function makeManager(): DynamicListAttrTablesManager
    {
        return new DynamicListAttrTablesManager(
            definitionRepository: $this->definitionRepo,
            messageBus: $this->bus,
            dbPrefix: 'phplist_',
            dynamicListTablePrefix: 'listattr_'
        );
    }

    public function testResolveTableNameReturnsNullWhenTypeIsNullOrUnsupported(): void
    {
        $manager = $this->makeManager();

        $this->assertNull($manager->resolveTableName('My Name', null));

        // For unsupported type (e.g., Text) should be null
        $this->assertNull($manager->resolveTableName('My Name', AttributeTypeEnum::Text));
    }

    public function testResolveTableNameSnakeCasesAndEnsuresUniqueness(): void
    {
        // First two candidates exist, third is unique
        $this->definitionRepo
            ->method('existsByTableName')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $manager = $this->makeManager();
        $name = $manager->resolveTableName('Fancy Label', AttributeTypeEnum::Select);

        // "Fancy Label" -> "fancy_label" with numeric suffix appended after collisions
        $this->assertSame('fancy_label2', $name);
    }

    public function testCreateOptionsTableIfNotExistsDispatchesMessage(): void
    {
        $this->bus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                $this->assertInstanceOf(DynamicTableMessage::class, $message);
                $this->assertSame('phplist_listattr_sizes', $message->getTableName());
                return true;
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $manager = $this->makeManager();
        $manager->createOptionsTableIfNotExists('sizes');
        $manager->createOptionsTableIfNotExists('sizes');
        $this->assertTrue(true);
    }
}

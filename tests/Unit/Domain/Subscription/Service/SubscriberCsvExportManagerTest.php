<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class SubscriberCsvExportManagerTest extends TestCase
{
    private SubscriberAttributeManager&MockObject $attributeManagerMock;
    private SubscriberRepository&MockObject $subscriberRepositoryMock;
    private SubscriberAttributeDefinitionRepository&MockObject $attributeDefinitionRepositoryMock;
    private SubscriberCsvExporter $subject;

    protected function setUp(): void
    {
        $this->attributeManagerMock = $this->createMock(SubscriberAttributeManager::class);
        $this->subscriberRepositoryMock = $this->createMock(SubscriberRepository::class);
        $this->attributeDefinitionRepositoryMock = $this->createMock(SubscriberAttributeDefinitionRepository::class);

        $this->subject = new SubscriberCsvExporter(
            $this->attributeManagerMock,
            $this->subscriberRepositoryMock,
            $this->attributeDefinitionRepositoryMock
        );
    }

    public function testExportToCsvWithFilterReturnsStreamedResponse(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber1->method('getEmail')->willReturn('test@example.com');
        $subscriber1->method('isConfirmed')->willReturn(true);
        $subscriber1->method('isBlacklisted')->willReturn(false);
        $subscriber1->method('hasHtmlEmail')->willReturn(true);
        $subscriber1->method('isDisabled')->willReturn(false);
        $subscriber1->method('getExtraData')->willReturn('Some data');

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);
        $subscriber2->method('getEmail')->willReturn('another@example.com');
        $subscriber2->method('isConfirmed')->willReturn(false);
        $subscriber2->method('isBlacklisted')->willReturn(true);
        $subscriber2->method('hasHtmlEmail')->willReturn(false);
        $subscriber2->method('isDisabled')->willReturn(true);
        $subscriber2->method('getExtraData')->willReturn('More data');

        $filter = new SubscriberFilter();
        $filter->setListId(1);

        $this->subscriberRepositoryMock
            ->expects($this->exactly(2))
            ->method('getFilteredAfterId')
            ->willReturnOnConsecutiveCalls(
                [$subscriber1, $subscriber2],
                []
            );

        $attributeDefinition = $this->createMock(SubscriberAttributeDefinition::class);
        $attributeDefinition->method('getName')->willReturn('first_name');
        $attributeDefinition->method('getId')->willReturn(1);

        $this->attributeDefinitionRepositoryMock
            ->method('findAll')
            ->willReturn([$attributeDefinition]);

        $attributeValue1 = $this->createMock(SubscriberAttributeValue::class);
        $attributeValue1->method('getValue')->willReturn('John');

        $attributeValue2 = $this->createMock(SubscriberAttributeValue::class);
        $attributeValue2->method('getValue')->willReturn('Jane');

        $this->attributeManagerMock
            ->method('getSubscriberAttribute')
            ->willReturnMap([
                [1, 1, $attributeValue1],
                [2, 1, $attributeValue2],
            ]);

        $response = $this->subject->exportToCsv($filter, 2);
        $response->sendContent();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            needle: 'attachment; filename=subscribers_export_',
            haystack: $response->headers->get('Content-Disposition')
        );
    }

    public function testExportToCsvWithoutFilterCreatesDefaultFilter(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);
        $subscriber1->method('getEmail')->willReturn('test@example.com');
        $subscriber1->method('isConfirmed')->willReturn(true);
        $subscriber1->method('isBlacklisted')->willReturn(false);
        $subscriber1->method('hasHtmlEmail')->willReturn(true);
        $subscriber1->method('isDisabled')->willReturn(false);
        $subscriber1->method('getExtraData')->willReturn('Some data');

        $this->subscriberRepositoryMock
            ->expects($this->exactly(1))
            ->method('getFilteredAfterId')
            ->willReturnOnConsecutiveCalls(
                [$subscriber1],
                []
            );

        $attributeDefinition = $this->createMock(SubscriberAttributeDefinition::class);
        $attributeDefinition->method('getName')->willReturn('first_name');
        $attributeDefinition->method('getId')->willReturn(1);

        $this->attributeDefinitionRepositoryMock
            ->method('findAll')
            ->willReturn([$attributeDefinition]);

        $attributeValue1 = $this->createMock(SubscriberAttributeValue::class);
        $attributeValue1->method('getValue')->willReturn('John');

        $this->attributeManagerMock
            ->method('getSubscriberAttribute')
            ->willReturnMap([
                [1, 1, $attributeValue1],
            ]);

        $response = $this->subject->exportToCsv();
        $response->sendContent();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            needle: 'attachment; filename=subscribers_export_',
            haystack: $response->headers->get('Content-Disposition')
        );
    }
}

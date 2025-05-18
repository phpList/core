<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class SubscriberCsvManagerTest extends TestCase
{
    private SubscriberManager&MockObject $subscriberManagerMock;
    private SubscriberAttributeManager&MockObject $attributeManagerMock;
    private SubscriberRepository&MockObject $subscriberRepositoryMock;
    private SubscriberAttributeDefinitionRepository&MockObject $attributeDefinitionRepositoryMock;
    private SubscriberCsvManager $subject;

    protected function setUp(): void
    {
        $this->subscriberManagerMock = $this->createMock(SubscriberManager::class);
        $this->attributeManagerMock = $this->createMock(SubscriberAttributeManager::class);
        $this->subscriberRepositoryMock = $this->createMock(SubscriberRepository::class);
        $this->attributeDefinitionRepositoryMock = $this->createMock(SubscriberAttributeDefinitionRepository::class);

        $this->subject = new SubscriberCsvManager(
            subscriberManager: $this->subscriberManagerMock,
            attributeManager: $this->attributeManagerMock,
            subscriberRepository: $this->subscriberRepositoryMock,
            attributeDefinitionRepository: $this->attributeDefinitionRepositoryMock
        );
    }

    public function testImportFromCsvCreatesNewSubscribers(): void
    {
        $csvContent = "email,confirmed,html_email,blacklisted,disabled,extra_data,first_name\n";
        $csvContent .= "test@example.com,1,1,0,0,\"Some extra data\",John\n";
        $csvContent .= "another@example.com,0,0,1,1,\"More data\",Jane\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn($tempFile);

        $attributeDefinition = $this->createMock(SubscriberAttributeDefinition::class);
        $attributeDefinition->method('getName')->willReturn('first_name');
        $attributeDefinition->method('getId')->willReturn(1);

        $this->attributeDefinitionRepositoryMock
            ->method('findOneBy')
            ->with(['name' => 'first_name'])
            ->willReturn($attributeDefinition);

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);

        $this->subscriberRepositoryMock
            ->method('findOneByEmail')
            ->willReturn(null);

        $this->subscriberManagerMock
            ->expects($this->exactly(2))
            ->method('createSubscriber')
            ->willReturnOnConsecutiveCalls($subscriber1, $subscriber2);

        $this->attributeManagerMock
            ->expects($this->exactly(2))
            ->method('createOrUpdate')
            ->withConsecutive(
                [$subscriber1, $attributeDefinition, 'John'],
                [$subscriber2, $attributeDefinition, 'Jane']
            );

        $result = $this->subject->importFromCsv($uploadedFile);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        unlink($tempFile);
    }

    public function testImportFromCsvUpdatesExistingSubscribers(): void
    {
        $csvContent = "email,confirmed,html_email,blacklisted,disabled,extra_data\n";
        $csvContent .= "existing@example.com,1,1,0,0,\"Updated data\"\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn($tempFile);

        $existingSubscriber = $this->createMock(Subscriber::class);
        $existingSubscriber->method('getId')->willReturn(1);
        $existingSubscriber->method('isConfirmed')->willReturn(false);
        $existingSubscriber->method('hasHtmlEmail')->willReturn(false);
        $existingSubscriber->method('isBlacklisted')->willReturn(true);
        $existingSubscriber->method('isDisabled')->willReturn(true);
        $existingSubscriber->method('getExtraData')->willReturn('Old data');

        $this->subscriberRepositoryMock
            ->method('findOneByEmail')
            ->with('existing@example.com')
            ->willReturn($existingSubscriber);

        $updatedSubscriber = $this->createMock(Subscriber::class);

        $this->subscriberManagerMock
            ->expects($this->once())
            ->method('updateSubscriber')
            ->with($this->callback(function (UpdateSubscriberDto $dto) {
                return $dto->subscriberId === 1 
                    && $dto->email === 'existing@example.com' 
                    && $dto->confirmed === true 
                    && $dto->htmlEmail === true 
                    && $dto->blacklisted === false 
                    && $dto->disabled === false 
                    && $dto->additionalData === 'Updated data';
            }))
            ->willReturn($updatedSubscriber);

        $result = $this->subject->importFromCsv($uploadedFile, true);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        unlink($tempFile);
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

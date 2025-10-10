<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\CsvImporter;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriptionManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvImporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\Translator;

class SubscriberCsvImporterTest extends TestCase
{
    private SubscriberManager&MockObject $subscriberManagerMock;
    private SubscriberAttributeManager&MockObject $attributeManagerMock;
    private SubscriberRepository&MockObject $subscriberRepositoryMock;
    private CsvImporter&MockObject $csvImporterMock;
    private SubscriberAttributeDefinitionRepository&MockObject $attributeDefinitionRepositoryMock;
    private SubscriberCsvImporter $subject;

    protected function setUp(): void
    {
        $this->subscriberManagerMock = $this->createMock(SubscriberManager::class);
        $this->attributeManagerMock = $this->createMock(SubscriberAttributeManager::class);
        $subscriptionManagerMock = $this->createMock(SubscriptionManager::class);
        $this->subscriberRepositoryMock = $this->createMock(SubscriberRepository::class);
        $this->csvImporterMock = $this->createMock(CsvImporter::class);
        $this->attributeDefinitionRepositoryMock = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->subject = new SubscriberCsvImporter(
            subscriberManager: $this->subscriberManagerMock,
            attributeManager: $this->attributeManagerMock,
            subscriptionManager: $subscriptionManagerMock,
            subscriberRepository: $this->subscriberRepositoryMock,
            csvImporter: $this->csvImporterMock,
            entityManager: $entityManager,
            translator: new Translator('en'),
            messageBus: $this->createMock(MessageBusInterface::class),
            subscriberHistoryManager: $this->createMock(SubscriberHistoryManager::class),
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
        $uploadedFile->method('getRealPath')->willReturn($tempFile);

        $attributeDefinition = $this->createMock(SubscriberAttributeDefinition::class);
        $attributeDefinition->method('getName')->willReturn('first_name');
        $attributeDefinition->method('getId')->willReturn(1);

        $this->attributeDefinitionRepositoryMock
            ->method('findOneByName')
            ->with('first_name')
            ->willReturn($attributeDefinition);

        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getId')->willReturn(1);

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getId')->willReturn(2);

        $this->subscriberRepositoryMock
            ->method('findOneByEmail')
            ->willReturn(null);

        $importDto1 = new ImportSubscriberDto(
            email: 'test@example.com',
            confirmed: true,
            blacklisted: false,
            htmlEmail: true,
            disabled: false,
        );
        $importDto1->extraData = 'Some extra data';
        $importDto1->extraAttributes = ['first_name' => 'John'];

        $importDto2 = new ImportSubscriberDto(
            email: 'another@example.com',
            confirmed: false,
            blacklisted: true,
            htmlEmail: false,
            disabled: true
        );
        $importDto2->extraData = 'More data';
        $importDto2->extraAttributes = ['first_name' => 'Jane'];

        $this->csvImporterMock
            ->method('import')
            ->with($tempFile)
            ->willReturn([
                'valid' => [$importDto1, $importDto2],
                'errors' => []
            ]);

        $this->subscriberManagerMock
            ->expects($this->exactly(2))
            ->method('createFromImport')
            ->willReturnOnConsecutiveCalls($subscriber1, $subscriber2);

        $this->attributeManagerMock
            ->expects($this->exactly(2))
            ->method('processAttributes')
            ->withConsecutive(
                [$subscriber1, $importDto1],
                [$subscriber2, $importDto2]
            );

        $options = new SubscriberImportOptions();
        $result = $this->subject->importFromCsv($uploadedFile, $options);

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
        $uploadedFile->method('getRealPath')->willReturn($tempFile);

        $existingSubscriber = $this->createMock(Subscriber::class);
        $existingSubscriber->method('getId')->willReturn(1);

        $this->subscriberRepositoryMock
            ->method('findOneByEmail')
            ->with('existing@example.com')
            ->willReturn($existingSubscriber);

        $importDto = new ImportSubscriberDto(
            email: 'existing@example.com',
            confirmed: true,
            blacklisted: false,
            htmlEmail: true,
            disabled: false,
        );
        $importDto->extraData = 'Updated data';
        $importDto->extraAttributes = [];

        $this->csvImporterMock
            ->method('import')
            ->with($tempFile)
            ->willReturn([
                'valid' => [$importDto],
                'errors' => []
            ]);

        $this->subscriberManagerMock
            ->expects($this->once())
            ->method('updateFromImport')
            ->with($existingSubscriber, $importDto)
            ->willReturn([]);

        $options = new SubscriberImportOptions(updateExisting: true);
        $result = $this->subject->importFromCsv($uploadedFile, $options);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        unlink($tempFile);
    }
}

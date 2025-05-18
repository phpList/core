<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvManager;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for the SubscriberCsvManager.
 */
class SubscriberCsvManagerTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private ?SubscriberCsvManager $subscriberCsvManager = null;
    private ?SubscriberRepository $subscriberRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subscriberCsvManager = self::getContainer()->get(SubscriberCsvManager::class);
        $this->subscriberRepository = self::getContainer()->get(SubscriberRepository::class);
    }

    public function testImportFromCsvCreatesNewSubscribers(): void
    {
        $attributeDefinition = new SubscriberAttributeDefinition();
        $attributeDefinition->setName('first_name');
        $this->entityManager->persist($attributeDefinition);
        $this->entityManager->flush();

        $csvContent = "email,confirmed,html_email,blacklisted,disabled,extra_data,first_name\n";
        $csvContent .= "test@example.com,1,1,0,0,\"Some extra data\",John\n";
        $csvContent .= "another@example.com,0,0,1,1,\"More data\",Jane\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'subscribers.csv',
            'text/csv',
            null,
            true
        );

        $subscriberCountBefore = count($this->subscriberRepository->findAll());

        $result = $this->subscriberCsvManager->importFromCsv($uploadedFile);

        $subscriberCountAfter = count($this->subscriberRepository->findAll());

        self::assertSame(2, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertEmpty($result['errors']);
        self::assertSame($subscriberCountBefore + 2, $subscriberCountAfter);

        $subscriber1 = $this->subscriberRepository->findOneByEmail('test@example.com');
        self::assertInstanceOf(Subscriber::class, $subscriber1);
        self::assertTrue($subscriber1->isConfirmed());
        self::assertTrue($subscriber1->hasHtmlEmail());
        self::assertFalse($subscriber1->isBlacklisted());
        self::assertFalse($subscriber1->isDisabled());
        self::assertSame('Some extra data', $subscriber1->getExtraData());

        $subscriber2 = $this->subscriberRepository->findOneByEmail('another@example.com');
        self::assertInstanceOf(Subscriber::class, $subscriber2);
        self::assertFalse($subscriber2->isConfirmed());
        self::assertFalse($subscriber2->hasHtmlEmail());
        self::assertTrue($subscriber2->isBlacklisted());
        self::assertTrue($subscriber2->isDisabled());
        self::assertSame('More data', $subscriber2->getExtraData());

        unlink($tempFile);
    }

    public function testImportFromCsvUpdatesExistingSubscribers(): void
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail('existing@example.com');
        $subscriber->setConfirmed(false);
        $subscriber->setHtmlEmail(false);
        $subscriber->setBlacklisted(true);
        $subscriber->setDisabled(true);
        $subscriber->setExtraData('Old data');
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $csvContent = "email,confirmed,html_email,blacklisted,disabled,extra_data\n";
        $csvContent .= "existing@example.com,1,1,0,0,\"Updated data\"\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'subscribers.csv',
            'text/csv',
            null,
            true
        );

        $result = $this->subscriberCsvManager->importFromCsv($uploadedFile, true);

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertEmpty($result['errors']);

        $updatedSubscriber = $this->subscriberRepository->findOneByEmail('existing@example.com');
        self::assertInstanceOf(Subscriber::class, $updatedSubscriber);
        self::assertTrue($updatedSubscriber->isConfirmed());
        self::assertTrue($updatedSubscriber->hasHtmlEmail());
        self::assertFalse($updatedSubscriber->isBlacklisted());
        self::assertFalse($updatedSubscriber->isDisabled());
        self::assertSame('Updated data', $updatedSubscriber->getExtraData());

        unlink($tempFile);
    }

    public function testExportToCsvReturnsStreamedResponse(): void
    {
        $subscriber1 = new Subscriber();
        $subscriber1->setEmail('test1@example.com');
        $subscriber1->setConfirmed(true);
        $subscriber1->setHtmlEmail(true);
        $subscriber1->setBlacklisted(false);
        $subscriber1->setDisabled(false);
        $subscriber1->setExtraData('Data 1');
        $this->entityManager->persist($subscriber1);

        $subscriber2 = new Subscriber();
        $subscriber2->setEmail('test2@example.com');
        $subscriber2->setConfirmed(false);
        $subscriber2->setHtmlEmail(false);
        $subscriber2->setBlacklisted(true);
        $subscriber2->setDisabled(true);
        $subscriber2->setExtraData('Data 2');
        $this->entityManager->persist($subscriber2);

        $this->entityManager->flush();

        $savedSubscribers = $this->subscriberRepository->findAll();
        self::assertCount(2, $savedSubscribers);

        $filter = new SubscriberFilter();

        $response = $this->subscriberCsvManager->exportToCsv($filter);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            'attachment; filename=subscribers_export_',
            $response->headers->get('Content-Disposition')
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        echo 'CSV Content: ' . $content . "\n";

        self::assertStringContainsString('email,confirmed,blacklisted,html_email,disabled,extra_data', $content);
    }
}

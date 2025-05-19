<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvExportManager;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for the SubscriberCsvExportManager.
 */
class SubscriberCsvExportManagerTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private ?SubscriberCsvExportManager $subscriberCsvExportManager = null;
    private ?SubscriberRepository $subscriberRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subscriberCsvExportManager = self::getContainer()->get(SubscriberCsvExportManager::class);
        $this->subscriberRepository = self::getContainer()->get(SubscriberRepository::class);
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

        $response = $this->subscriberCsvExportManager->exportToCsv($filter);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            'attachment; filename=subscribers_export_',
            $response->headers->get('Content-Disposition')
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        self::assertStringContainsString('email,confirmed,blacklisted,html_email,disabled,extra_data', $content);
    }
}

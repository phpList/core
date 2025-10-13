<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

class SubscriberManagerTest extends TestCase
{
    private SubscriberRepository|MockObject $subscriberRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscriberManager $subscriberManager;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $subscriberDeletionService = $this->createMock(SubscriberDeletionService::class);

        $this->subscriberManager = new SubscriberManager(
            subscriberRepository: $this->subscriberRepository,
            entityManager: $this->entityManager,
            subscriberDeletionService: $subscriberDeletionService,
            translator: new Translator('en'),
            subscriberHistoryManager: $this->createMock(SubscriberHistoryManager::class)
        );
    }

    public function testCreateSubscriberPersistsAndReturnsProperlyInitializedEntity(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Subscriber $sub): bool {
                return $sub->getEmail() === 'foo@bar.com'
                    && $sub->isConfirmed() === true
                    && $sub->isBlacklisted() === false
                    && $sub->hasHtmlEmail() === true
                    && $sub->isDisabled() === false;
            }));

        $dto = new CreateSubscriberDto(email: 'foo@bar.com', requestConfirmation: false, htmlEmail: true);

        $result = $this->subscriberManager->createSubscriber($dto);

        $this->assertInstanceOf(Subscriber::class, $result);
        $this->assertSame('foo@bar.com', $result->getEmail());
        $this->assertTrue($result->isConfirmed());
        $this->assertFalse($result->isBlacklisted());
        $this->assertTrue($result->hasHtmlEmail());
        $this->assertFalse($result->isDisabled());
    }

    public function testCreateSubscriberPersists(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Subscriber $sub): bool {
                $sub->setUniqueId('test-unique-id-456');
                return $sub->getEmail() === 'foo@bar.com'
                    && $sub->isConfirmed() === false
                    && $sub->isBlacklisted() === false
                    && $sub->hasHtmlEmail() === true
                    && $sub->isDisabled() === false;
            }));

        $dto = new CreateSubscriberDto(email: 'foo@bar.com', requestConfirmation: true, htmlEmail: true);

        $result = $this->subscriberManager->createSubscriber($dto);

        $this->assertSame('foo@bar.com', $result->getEmail());
        $this->assertFalse($result->isConfirmed());
        $this->assertFalse($result->isBlacklisted());
        $this->assertTrue($result->hasHtmlEmail());
        $this->assertFalse($result->isDisabled());
    }

    public function testCreateSubscriberWithConfirmation(): void
    {
        $capturedSubscriber = null;
        $this->subscriberRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Subscriber $subscriber) use (&$capturedSubscriber) {
                $capturedSubscriber = $subscriber;
                $subscriber->setUniqueId('test-unique-id-123');
                return true;
            }));

        $dto = new CreateSubscriberDto(email: 'test@example.com', requestConfirmation: true, htmlEmail: true);
        $this->subscriberManager->createSubscriber($dto);

        $this->assertNotNull($capturedSubscriber);
        $this->assertEquals('test@example.com', $capturedSubscriber->getEmail());
        $this->assertTrue($capturedSubscriber->hasHtmlEmail());
        $this->assertFalse($capturedSubscriber->isConfirmed());
    }

    public function testCreateSubscriberWithoutConfirmation(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('save');

        $dto = new CreateSubscriberDto(email: 'test@example.com', requestConfirmation: false, htmlEmail: true);
        $this->subscriberManager->createSubscriber($dto);
    }

    public function testMarkAsConfirmedByUniqueIdConfirmsSubscriber(): void
    {
        $uniqueId = 'some-unique-id-789';
        $subscriber = $this->createMock(Subscriber::class);

        $this->subscriberRepository
            ->expects($this->once())
            ->method('findOneByUniqueId')
            ->with($uniqueId)
            ->willReturn($subscriber);

        $subscriber
            ->expects($this->once())
            ->method('setConfirmed')
            ->with(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->subscriberManager->markAsConfirmedByUniqueId($uniqueId);

        $this->assertSame($subscriber, $result);
    }
}

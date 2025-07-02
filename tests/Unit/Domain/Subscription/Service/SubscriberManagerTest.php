<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SubscriberManagerTest extends TestCase
{
    private SubscriberRepository|MockObject $subscriberRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private MessageBusInterface|MockObject $messageBus;
    private SubscriberDeletionService|MockObject $subscriberDeletionService;
    private SubscriberManager $subscriberManager;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->subscriberDeletionService = $this->createMock(SubscriberDeletionService::class);

        $this->subscriberManager = new SubscriberManager(
            $this->subscriberRepository,
            $this->entityManager,
            $this->messageBus,
            $this->subscriberDeletionService
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

    public function testCreateSubscriberPersistsAndSendsEmail(): void
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

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $dto = new CreateSubscriberDto(email: 'foo@bar.com', requestConfirmation: true, htmlEmail: true);

        $result = $this->subscriberManager->createSubscriber($dto);

        $this->assertSame('foo@bar.com', $result->getEmail());
        $this->assertFalse($result->isConfirmed());
        $this->assertFalse($result->isBlacklisted());
        $this->assertTrue($result->hasHtmlEmail());
        $this->assertFalse($result->isDisabled());
    }

    public function testCreateSubscriberWithConfirmationSendsConfirmationEmail(): void
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

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SubscriberConfirmationMessage $message) {
                $this->assertEquals('test@example.com', $message->getEmail());
                $this->assertEquals('test-unique-id-123', $message->getUniqueId());
                $this->assertTrue($message->hasHtmlEmail());
                return true;
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $dto = new CreateSubscriberDto(email: 'test@example.com', requestConfirmation: true, htmlEmail: true);
        $this->subscriberManager->createSubscriber($dto);

        $this->assertNotNull($capturedSubscriber);
        $this->assertEquals('test@example.com', $capturedSubscriber->getEmail());
        $this->assertTrue($capturedSubscriber->hasHtmlEmail());
        $this->assertFalse($capturedSubscriber->isConfirmed());
    }

    public function testCreateSubscriberWithoutConfirmationDoesNotSendConfirmationEmail(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('save');

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $dto = new CreateSubscriberDto(email: 'test@example.com', requestConfirmation: false, htmlEmail: true);
        $this->subscriberManager->createSubscriber($dto);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Command\ProcessQueueCommand;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ProcessQueueCommandTest extends TestCase
{
    private MessageRepository&MockObject $messageRepository;
    private MailerInterface&MockObject $mailer;
    private EntityManagerInterface&MockObject $entityManager;
    private SubscriberProvider&MockObject $subscriberProvider;
    private MessageProcessingPreparator&MockObject $messageProcessingPreparator;
    private LockInterface&MockObject $lock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriberProvider = $this->createMock(SubscriberProvider::class);
        $this->messageProcessingPreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->lock = $this->createMock(LockInterface::class);

        $lockFactory->method('createLock')
            ->with('queue_processor')
            ->willReturn($this->lock);

        $command = new ProcessQueueCommand(
            $this->messageRepository,
            $this->mailer,
            $lockFactory,
            $this->entityManager,
            $this->subscriberProvider,
            $this->messageProcessingPreparator
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithLockAlreadyAcquired(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $this->messageProcessingPreparator->expects($this->never())
            ->method('ensureSubscribersHaveUuid');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Queue is already being processed by another instance', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithNoCampaigns(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithCampaigns(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $campaign = $this->createMock(Message::class);
        $metadata = $this->createMock(MessageMetadata::class);
        $content = $this->createMock(MessageContent::class);

        $campaign->expects($this->any())
            ->method('getMetadata')
            ->willReturn($metadata);

        $campaign->expects($this->any())
            ->method('getContent')
            ->willReturn($content);

        $content->expects($this->any())
            ->method('getSubject')
            ->willReturn('Test Subject');

        $content->expects($this->any())
            ->method('getTextMessage')
            ->willReturn('Test Text Message');

        $content->expects($this->any())
            ->method('getText')
            ->willReturn('<h1>Test HTML Message</h1>');

        $metadata->expects($this->once())
            ->method('setStatus')
            ->with('sent');

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->expects($this->any())
            ->method('getEmail')
            ->willReturn('test@example.com');
        $subscriber->expects($this->any())
            ->method('getId')
            ->willReturn(1);


        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign]);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertEquals('Test Subject', $email->getSubject());
                $this->assertEquals('Test Text Message', $email->getTextBody());
                $this->assertEquals('<h1>Test HTML Message</h1>', $email->getHtmlBody());

                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertEquals('test@example.com', $toAddresses[0]->getAddress());

                $fromAddresses = $email->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertEquals('news@example.com', $fromAddresses[0]->getAddress());

                return true;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidSubscriberEmail(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $campaign = $this->createMock(Message::class);
        $metadata = $this->createMock(MessageMetadata::class);
        $content = $this->createMock(MessageContent::class);

        $campaign->expects($this->any())
            ->method('getMetadata')
            ->willReturn($metadata);

        $campaign->expects($this->any())
            ->method('getContent')
            ->willReturn($content);

        $metadata->expects($this->once())
            ->method('setStatus')
            ->with('sent');

        $invalidSubscriber = $this->createMock(Subscriber::class);
        $invalidSubscriber->expects($this->any())
            ->method('getEmail')
            ->willReturn('invalid-email');

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign]);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$invalidSubscriber]);

        $this->mailer->expects($this->never())
            ->method('send');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithMailerException(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $campaign = $this->createMock(Message::class);
        $metadata = $this->createMock(MessageMetadata::class);
        $content = $this->createMock(MessageContent::class);

        $campaign->expects($this->any())
            ->method('getMetadata')
            ->willReturn($metadata);

        $campaign->expects($this->any())
            ->method('getContent')
            ->willReturn($content);

        $content->expects($this->any())
            ->method('getSubject')
            ->willReturn('Test Subject');

        $content->expects($this->any())
            ->method('getTextMessage')
            ->willReturn('Test Text Message');

        $content->expects($this->any())
            ->method('getText')
            ->willReturn('<h1>Test HTML Message</h1>');

        $metadata->expects($this->once())
            ->method('setStatus')
            ->with('sent');

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->expects($this->any())
            ->method('getEmail')
            ->willReturn('test@example.com');
        $subscriber->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign]);

        $this->subscriberProvider->expects($this->once())
            ->method('getSubscribersForMessage')
            ->with($campaign)
            ->willReturn([$subscriber]);

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('Failed to send email'));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to send to: test@example.com', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}

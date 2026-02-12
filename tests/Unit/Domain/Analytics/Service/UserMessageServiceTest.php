<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Model\UserMessageView;
use PhpList\Core\Domain\Analytics\Service\UserMessageService;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserMessageServiceTest extends TestCase
{
    private UserMessageService $subject;
    private UserMessageRepository|MockObject $userMessageRepository;
    private SubscriberRepository|MockObject $subscriberRepository;
    private MessageRepository|MockObject $messageRepository;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->subject = new UserMessageService(
            $this->userMessageRepository,
            $this->subscriberRepository,
            $this->messageRepository,
            $this->entityManager
        );
    }

    public function testReturnsEarlyWhenSubscriberNotFound(): void
    {
        $this->subscriberRepository
            ->expects(self::once())
            ->method('findOneByUniqueId')
            ->with('sub-uid')
            ->willReturn(null);

        // Service fetches message regardless, then returns early because subscriber is null
        $this->messageRepository
            ->expects(self::once())
            ->method('find')
            ->with(123)
            ->willReturn($this->getMockBuilder(Message::class)->disableOriginalConstructor()->getMock());
        $this->userMessageRepository->expects(self::never())->method('findByUserAndMessage');
        $this->entityManager->expects(self::never())->method('persist');

        $this->subject->trackUserMessageView('sub-uid', 123, []);
    }

    public function testReturnsEarlyWhenMessageNotFound(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $this->subscriberRepository
            ->method('findOneByUniqueId')
            ->willReturn($subscriber);

        $this->messageRepository
            ->expects(self::once())
            ->method('find')
            ->with(123)
            ->willReturn(null);

        $this->userMessageRepository->expects(self::never())->method('findByUserAndMessage');
        $this->entityManager->expects(self::never())->method('persist');

        $this->subject->trackUserMessageView('sub-uid', 123, []);
    }

    public function testReturnsEarlyWhenUserMessageNotFound(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->addMethods(['incrementViews'])
            ->getMock();

        $this->subscriberRepository->method('findOneByUniqueId')->willReturn($subscriber);
        $this->messageRepository->method('find')->willReturn($message);

        $this->userMessageRepository
            ->expects(self::once())
            ->method('findByUserAndMessage')
            ->with($subscriber, $message)
            ->willReturn(null);

        $message->expects(self::never())->method('incrementViews');
        $this->entityManager->expects(self::never())->method('persist');

        $this->subject->trackUserMessageView('sub-uid', 321, []);
    }

    public function testHappyPathPersistsUserMessageViewAndMarksViewed(): void
    {
        $subscriberId = 17;
        $messageId = 42;

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getId')->willReturn($subscriberId);

        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->addMethods(['incrementViews'])
            ->getMock();

        $userMessage = $this->createMock(UserMessage::class);

        $this->subscriberRepository->method('findOneByUniqueId')->willReturn($subscriber);
        $this->messageRepository->method('find')->willReturn($message);
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn($userMessage);

        $userMessage->expects(self::once())->method('setViewedNow');
        $message->expects(self::once())->method('incrementViews');

        $metadata = [
            'client_ip' => '203.0.113.10',
            'HTTP_USER_AGENT' => '<b>UnitTester/1.0</b>',
            'HTTP_REFERER' => '<script>alert(1)</script>http://example.test/page',
        ];

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($arg) use ($subscriberId, $messageId, $metadata): bool {
                self::assertInstanceOf(UserMessageView::class, $arg);
                $view = $arg;
                self::assertSame($subscriberId, $view->getUserId());
                self::assertSame($messageId, $view->getMessageId());
                self::assertNotNull($view->getViewed());
                self::assertSame($metadata['client_ip'], $view->getIp());
                $data = unserialize((string) $view->getData());
                self::assertIsArray($data);
                self::assertSame('UnitTester/1.0', $data['HTTP_USER_AGENT']);
                self::assertSame('alert(1)http://example.test/page', $data['HTTP_REFERER']);
                return true;
            }));

        $this->subject->trackUserMessageView('any-uid', $messageId, $metadata);
    }

    public function testHandlesMissingOptionalMetadataGracefully(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getId')->willReturn(99);

        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->addMethods(['incrementViews'])
            ->getMock();

        $userMessage = $this->createMock(UserMessage::class);

        $this->subscriberRepository->method('findOneByUniqueId')->willReturn($subscriber);
        $this->messageRepository->method('find')->willReturn($message);
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn($userMessage);

        $message->expects(self::once())->method('incrementViews');
        $userMessage->expects(self::once())->method('setViewedNow');

        // No HTTP_* keys and no client_ip
        $metadata = [];

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($arg): bool {
                /** @var UserMessageView $arg */
                self::assertInstanceOf(UserMessageView::class, $arg);
                self::assertNull($arg->getIp());
                // data should serialize an empty array
                self::assertSame(serialize([]), $arg->getData());
                return true;
            }));

        $this->subject->trackUserMessageView('uid', 5, $metadata);
    }
}

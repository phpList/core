<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessageForward;
use PhpList\Core\Domain\Messaging\Service\Manager\UserMessageForwardManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserMessageForwardManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserMessageForwardManager $manager;
    private string $expectedStatus = 'queued';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manager = new UserMessageForwardManager($this->entityManager);
    }

    public function testCreatePersistsAndReturnsForwardWithExpectedFields(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $message = $this->createMock(Message::class);

        $subscriber->method('getId')->willReturn(42);
        $message->method('getId')->willReturn(7);

        $expectedFriendEmail = 'friend@example.test';

        $persisted = null;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function (UserMessageForward $fwd) use (&$persisted, $expectedFriendEmail) {
                    $persisted = $fwd;
                    return $fwd->getUserId() === 42
                        && $fwd->getMessageId() === 7
                        && $fwd->getForward() === $expectedFriendEmail
                        && $fwd->getStatus() === $this->expectedStatus
                        && $fwd->getCreatedAt() !== null;
                })
            );

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->manager->create(
            subscriber: $subscriber,
            campaign: $message,
            friendEmail: $expectedFriendEmail,
            status: $this->expectedStatus
        );

        $this->assertInstanceOf(UserMessageForward::class, $result);
        $this->assertSame($persisted, $result, 'Returned entity should be the same instance that was persisted');
        $this->assertSame(42, $result->getUserId());
        $this->assertSame(7, $result->getMessageId());
        $this->assertSame($expectedFriendEmail, $result->getForward());
        $this->assertSame($this->expectedStatus, $result->getStatus());
    }
}

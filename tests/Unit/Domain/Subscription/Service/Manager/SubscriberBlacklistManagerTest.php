<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Model\UserBlacklistData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistDataRepository;
use PhpList\Core\Domain\Subscription\Repository\UserBlacklistRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberBlacklistManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberBlacklistManagerTest extends TestCase
{
    private SubscriberRepository|MockObject $subscriberRepository;
    private UserBlacklistRepository|MockObject $userBlacklistRepository;
    private UserBlacklistDataRepository|MockObject $userBlacklistDataRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscriberBlacklistManager $manager;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->userBlacklistRepository = $this->createMock(UserBlacklistRepository::class);
        $this->userBlacklistDataRepository = $this->createMock(UserBlacklistDataRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new SubscriberBlacklistManager(
            subscriberRepository: $this->subscriberRepository,
            userBlacklistRepository: $this->userBlacklistRepository,
            blacklistDataRepository: $this->userBlacklistDataRepository,
            entityManager: $this->entityManager,
        );
    }

    public function testIsEmailBlacklistedReturnsValueFromRepository(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('isEmailBlacklisted')
            ->with('test@example.com')
            ->willReturn(true);

        $result = $this->manager->isEmailBlacklisted('test@example.com');

        $this->assertTrue($result);
    }

    public function testGetBlacklistInfoReturnsResultFromRepository(): void
    {
        $userBlacklist = $this->createMock(UserBlacklist::class);

        $this->userBlacklistRepository
            ->expects($this->once())
            ->method('findBlacklistInfoByEmail')
            ->with('foo@bar.com')
            ->willReturn($userBlacklist);

        $result = $this->manager->getBlacklistInfo('foo@bar.com');

        $this->assertSame($userBlacklist, $result);
    }

    public function testAddEmailToBlacklistDoesNotAddIfAlreadyBlacklisted(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('isEmailBlacklisted')
            ->with('already@blacklisted.com')
            ->willReturn(true);

        $this->userBlacklistRepository
            ->expects($this->once())
            ->method('findBlacklistInfoByEmail')
            ->willReturn($this->createMock(UserBlacklist::class));

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->manager->addEmailToBlacklist('already@blacklisted.com', 'reason');
    }

    public function testAddEmailToBlacklistAddsEntryAndReason(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('isEmailBlacklisted')
            ->with('new@blacklist.com')
            ->willReturn(false);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive(
                [$this->isInstanceOf(UserBlacklist::class)],
                [$this->isInstanceOf(UserBlacklistData::class)]
            );

        $this->manager->addEmailToBlacklist('new@blacklist.com', 'test reason');
    }

    public function testAddEmailToBlacklistAddsEntryWithoutReason(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('isEmailBlacklisted')
            ->with('noreason@blacklist.com')
            ->willReturn(false);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(UserBlacklist::class));

        $this->manager->addEmailToBlacklist('noreason@blacklist.com');
    }

    public function testRemoveEmailFromBlacklistRemovesAllRelatedData(): void
    {
        $blacklist = $this->createMock(UserBlacklist::class);
        $blacklistData = $this->createMock(UserBlacklistData::class);
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->onlyMethods(['setBlacklisted'])
            ->getMock();

        $this->userBlacklistRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('remove@me.com')
            ->willReturn($blacklist);

        $this->userBlacklistDataRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('remove@me.com')
            ->willReturn($blacklistData);

        $this->subscriberRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('remove@me.com')
            ->willReturn($subscriber);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive([$blacklist], [$blacklistData]);

        $subscriber->expects($this->once())->method('setBlacklisted')->with(false);

        $this->manager->removeEmailFromBlacklist('remove@me.com');
    }

    public function testGetBlacklistReasonReturnsReasonOrNull(): void
    {
        $blacklistData = $this->createMock(UserBlacklistData::class);
        $blacklistData->expects($this->once())->method('getData')->willReturn('my reason');

        $this->userBlacklistDataRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('why@blacklist.com')
            ->willReturn($blacklistData);

        $result = $this->manager->getBlacklistReason('why@blacklist.com');
        $this->assertSame('my reason', $result);
    }

    public function testGetBlacklistReasonReturnsNullIfNoData(): void
    {
        $this->userBlacklistDataRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('none@blacklist.com')
            ->willReturn(null);

        $result = $this->manager->getBlacklistReason('none@blacklist.com');
        $this->assertNull($result);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Messaging\Service\SubscriberBlacklistService;
use PhpList\Core\Domain\Subscription\Command\BlacklistEmailCommand;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\UserBlacklist;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberBlacklistManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlacklistEmailCommandTest extends TestCase
{
    private SubscriberRepository&MockObject $subscribers;
    private SubscriberBlacklistManager&MockObject $manager;
    private SubscriberBlacklistService&MockObject $service;
    private EntityManagerInterface&MockObject $entityManager;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->subscribers = $this->createMock(SubscriberRepository::class);
        $this->manager = $this->createMock(SubscriberBlacklistManager::class);
        $this->service = $this->createMock(SubscriberBlacklistService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $this->tester = new CommandTester(new BlacklistEmailCommand(
            $this->subscribers,
            $this->manager,
            $this->service,
            $this->entityManager,
            $translator,
        ));
    }

    private function subscriber(string $email): Subscriber&MockObject
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn($email);

        return $subscriber;
    }

    public function testFailsWithoutEmailOrKnownSubscriber(): void
    {
        $this->subscribers->method('findOneByUniqueId')->willReturn(null);
        $this->service->expects($this->never())->method('blacklist');

        $this->tester->execute(['--uid' => 'unknown']);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('FAIL', $this->tester->getDisplay());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->service->expects($this->never())->method('blacklist');

        $this->tester->execute(['--email' => 'not-an-email']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testRejectsInvalidDate(): void
    {
        $this->service->expects($this->never())->method('blacklist');

        $this->tester->execute(['--email' => 'a@example.com', '--date' => 'not a date at all']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testBlacklistsKnownSubscriberFoundByUid(): void
    {
        $subscriber = $this->subscriber('a@example.com');
        $this->subscribers->method('findOneByUniqueId')->with('uid1')->willReturn($subscriber);
        $this->manager->method('isEmailBlacklisted')->willReturn(false);
        $this->service->expects($this->once())->method('blacklist')
            ->with($subscriber, 'blacklisted due to spam complaints');

        $this->tester->execute(['--uid' => 'uid1']);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('OK a@example.com', $this->tester->getDisplay());
    }

    public function testBlacklistsUnknownAddressWithEntryOnly(): void
    {
        $this->subscribers->method('findOneByEmail')->willReturn(null);
        $this->manager->method('isEmailBlacklisted')->willReturn(false);
        $this->service->expects($this->never())->method('blacklist');
        $this->manager->expects($this->once())->method('addEmailToBlacklist')
            ->with('b@example.com', 'blacklisted due to spam complaints');

        $this->tester->execute(['--email' => 'b@example.com']);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('OK b@example.com', $this->tester->getDisplay());
    }

    public function testAlreadyBlacklistedOnlyEnsuresFlag(): void
    {
        $subscriber = $this->subscriber('a@example.com');
        $subscriber->expects($this->once())->method('setBlacklisted')->with(true);
        $this->subscribers->method('findOneByEmail')->willReturn($subscriber);
        $this->manager->method('isEmailBlacklisted')->willReturn(true);
        $this->service->expects($this->never())->method('blacklist');
        $this->manager->expects($this->never())->method('addEmailToBlacklist');

        $this->tester->execute(['--email' => 'a@example.com']);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('OK a@example.com', $this->tester->getDisplay());
    }

    public function testDateIsAppliedToBlacklistEntry(): void
    {
        $this->subscribers->method('findOneByEmail')->willReturn(null);
        $this->manager->method('isEmailBlacklisted')->willReturn(false);
        $entry = $this->createMock(UserBlacklist::class);
        $entry->expects($this->once())->method('setAdded')
            ->with($this->callback(fn (\DateTime $d) => $d->format('Y-m-d') === '2024-02-03'));
        $this->manager->method('getBlacklistInfo')->with('b@example.com')->willReturn($entry);

        $this->tester->execute(['--email' => 'b@example.com', '--date' => '2024-02-03']);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testFailureIsReported(): void
    {
        $this->subscribers->method('findOneByEmail')->willReturn(null);
        $this->manager->method('isEmailBlacklisted')->willReturn(false);
        $this->manager->method('addEmailToBlacklist')->willThrowException(new Exception('db down'));

        $this->tester->execute(['--email' => 'b@example.com']);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('db down', $this->tester->getDisplay());
    }
}

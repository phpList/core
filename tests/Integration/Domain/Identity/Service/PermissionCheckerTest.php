<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PhpList\Core\Domain\Identity\Model\Privileges;
use PhpList\Core\Domain\Identity\Service\PermissionChecker;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PermissionCheckerTest extends KernelTestCase
{
    private PermissionChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = self::getContainer()->get(PermissionChecker::class);
    }

    public function testServiceIsRegisteredInContainer(): void
    {
        self::assertInstanceOf(PermissionChecker::class, $this->checker);
        self::assertSame($this->checker, self::getContainer()->get(PermissionChecker::class));
    }

    public function testSuperUserCanManageAnyResource(): void
    {
        $admin = new Administrator();
        $admin->setSuperUser(true);
        $resource = $this->createMock(SubscriberList::class);
        $this->assertTrue($this->checker->canManage($admin, $resource));
    }

    public function testSuperUserCanCreateAnyResource(): void
    {
        $admin = new Administrator();
        $admin->setSuperUser(true);

        $this->assertTrue($this->checker->canCreate($admin, Subscriber::class));
        $this->assertTrue($this->checker->canCreate($admin, Message::class));
        $this->assertTrue($this->checker->canCreate($admin, Config::class));
    }

    public function testNonSuperUserCanCreateSubscriberWithSubscribersPrivilege(): void
    {
        $admin = new Administrator();
        $admin->setPrivileges((new Privileges())->grant(PrivilegeFlag::Subscribers));

        $this->assertTrue($this->checker->canCreate($admin, Subscriber::class));
        $this->assertTrue($this->checker->canCreate($admin, SubscriberList::class));
    }

    public function testNonSuperUserCannotCreateSubscriberWithoutSubscribersPrivilege(): void
    {
        $admin = new Administrator();
        $admin->setPrivileges(new Privileges());

        $this->assertFalse($this->checker->canCreate($admin, Subscriber::class));
        $this->assertFalse($this->checker->canCreate($admin, SubscriberList::class));
    }

    public function testNonSuperUserCanCreateMessageWithCampaignsPrivilege(): void
    {
        $admin = new Administrator();
        $admin->setPrivileges((new Privileges())->grant(PrivilegeFlag::Campaigns));

        $this->assertTrue($this->checker->canCreate($admin, Message::class));
    }

    public function testNonSuperUserCannotCreateMessageWithoutCampaignsPrivilege(): void
    {
        $admin = new Administrator();
        $admin->setPrivileges((new Privileges())->grant(PrivilegeFlag::Subscribers));

        $this->assertFalse($this->checker->canCreate($admin, Message::class));
    }

    public function testNonSuperUserCanCreateResourceWithNoRequiredPrivilege(): void
    {
        $admin = new Administrator();
        $admin->setPrivileges(new Privileges());

        $this->assertTrue($this->checker->canCreate($admin, Config::class));
    }
}

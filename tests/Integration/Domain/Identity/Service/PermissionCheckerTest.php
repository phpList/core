<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Identity\Model\AccessLevel;
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

    public function testAccessLevelIsAllForSuperUser(): void
    {
        $admin = (new Administrator())->setSuperUser(true);

        self::assertSame(AccessLevel::All, $this->checker->accessLevel($admin, SubscriberList::class));
    }

    public function testAccessLevelIsNoneWithoutRequiredPrivilege(): void
    {
        $admin = (new Administrator())->setPrivileges(new Privileges());

        self::assertSame(AccessLevel::None, $this->checker->accessLevel($admin, Message::class));
    }

    public function testAccessLevelIsOwnerWithRequiredPrivilege(): void
    {
        $admin = (new Administrator())->setPrivileges((new Privileges())->grant(PrivilegeFlag::Campaigns));

        self::assertSame(AccessLevel::Owner, $this->checker->accessLevel($admin, Message::class));
    }

    public function testCannotManageListWithoutPrivilegeEvenIfOwner(): void
    {
        $admin = $this->adminWithId(1, new Privileges());
        $list = (new SubscriberList())->setOwner($admin);

        self::assertFalse($this->checker->canManage($admin, $list));
    }

    public function testCanManageOwnedListButNotForeignOrOwnerlessList(): void
    {
        $admin = $this->adminWithId(1, (new Privileges())->grant(PrivilegeFlag::Subscribers));
        $other = $this->adminWithId(2, new Privileges());

        self::assertTrue($this->checker->canManage($admin, (new SubscriberList())->setOwner($admin)));
        self::assertFalse($this->checker->canManage($admin, (new SubscriberList())->setOwner($other)));
        self::assertFalse($this->checker->canManage($admin, new SubscriberList()));
    }

    private function adminWithId(int $id, Privileges $privileges): Administrator
    {
        $admin = (new Administrator())->setPrivileges($privileges);
        $property = new \ReflectionProperty(Administrator::class, 'id');
        $property->setAccessible(true);
        $property->setValue($admin, $id);

        return $admin;
    }
}

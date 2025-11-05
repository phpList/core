<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\PermissionChecker;
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
}

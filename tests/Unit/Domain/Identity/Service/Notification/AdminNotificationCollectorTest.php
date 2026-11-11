<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use PhpList\Core\Domain\Identity\Service\Notification\AdminNotificationCollector;
use PhpList\Core\Domain\Identity\Service\Notification\AdminNotificationProviderInterface;
use PHPUnit\Framework\TestCase;

class AdminNotificationCollectorTest extends TestCase
{
    public function testCollectMergesAllProviders(): void
    {
        $a = new AdminNotification('a', 'info', 'A');
        $b = new AdminNotification('b', 'error', 'B');
        $p1 = $this->createMock(AdminNotificationProviderInterface::class);
        $p1->method('getNotifications')->willReturn([$a]);
        $p2 = $this->createMock(AdminNotificationProviderInterface::class);
        $p2->method('getNotifications')->willReturn([]);
        $p3 = $this->createMock(AdminNotificationProviderInterface::class);
        $p3->method('getNotifications')->willReturn([$b]);

        $result = (new AdminNotificationCollector([$p1, $p2, $p3]))->collect($this->createMock(Administrator::class));

        self::assertSame([$a, $b], $result);
    }
}

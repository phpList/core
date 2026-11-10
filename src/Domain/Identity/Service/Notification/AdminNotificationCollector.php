<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class AdminNotificationCollector
{
    /** @param iterable<AdminNotificationProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator('phplist.admin_notification_provider')] private readonly iterable $providers,
    ) {
    }

    /** @return AdminNotification[] */
    public function collect(Administrator $administrator): array
    {
        $notifications = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getNotifications($administrator) as $notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('phplist.admin_notification_provider')]
interface AdminNotificationProviderInterface
{
    /** @return AdminNotification[] */
    public function getNotifications(Administrator $administrator): array;
}

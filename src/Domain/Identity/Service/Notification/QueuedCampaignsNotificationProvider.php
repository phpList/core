<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use DateTimeImmutable;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * superusers are told how many campaigns are waiting in the queue.
 */
class QueuedCampaignsNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getNotifications(Administrator $administrator): array
    {
        if (!$administrator->isSuperUser()) {
            return [];
        }

        $count = $this->messageRepository->countQueued(new DateTimeImmutable());
        if ($count === 0) {
            return [];
        }

        return [
            new AdminNotification(
                code: 'campaigns_in_queue',
                level: AdminNotification::LEVEL_INFO,
                message: $this->translator->trans(
                    'You have %count% campaign(s) in the queue, ready for processing',
                    ['%count%' => $count]
                ),
                context: ['count' => $count],
            ),
        ];
    }
}

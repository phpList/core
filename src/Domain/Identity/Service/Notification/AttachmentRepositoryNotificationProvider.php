<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * phplist3 (index.php): warns when the attachment repository is missing or not writable.
 * Only superusers can act on this, so only they get it.
 */
class AttachmentRepositoryNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%phplist.attachment_repository_path%')] private readonly string $attachmentRepositoryPath = '/tmp',
    ) {
    }

    public function getNotifications(Administrator $administrator): array
    {
        if (!$administrator->isSuperUser()) {
            return [];
        }

        if (is_dir($this->attachmentRepositoryPath) && is_writable($this->attachmentRepositoryPath)) {
            return [];
        }

        return [
            new AdminNotification(
                code: 'attachment_repository_not_writable',
                level: AdminNotification::LEVEL_WARNING,
                message: $this->translator->trans('The attachment repository does not exist or is not writable'),
            ),
        ];
    }
}

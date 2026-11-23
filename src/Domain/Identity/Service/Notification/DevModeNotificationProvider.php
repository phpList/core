<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * in a DEV version every email is redirected to the developer address.
 * Mirrors the app.dev_version / app.dev_email switch used by the email builders.
 */
class DevModeNotificationProvider implements AdminNotificationProviderInterface
{
    private readonly bool $devVersion;

    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.dev_version%')] string|bool $devVersion,
        #[Autowire('%app.dev_email%')] private readonly string $devEmail = '',
    ) {
        $this->devVersion = filter_var($devVersion, FILTER_VALIDATE_BOOLEAN);
    }

    public function getNotifications(Administrator $administrator): array
    {
        if (!$this->devVersion || !$administrator->isSuperUser()) {
            return [];
        }

        if ($this->devEmail === '') {
            return [
                new AdminNotification(
                    code: 'dev_mode_without_email',
                    level: AdminNotification::LEVEL_INFO,
                    message: $this->translator->trans('Running DEV version, but developer email is not set'),
                ),
            ];
        }

        return [
            new AdminNotification(
                code: 'dev_mode',
                level: AdminNotification::LEVEL_INFO,
                message: $this->translator->trans(
                    'Running DEV version. All emails will be sent to %email%',
                    ['%email%' => $this->devEmail]
                ),
                context: ['email' => $this->devEmail],
            ),
        ];
    }
}

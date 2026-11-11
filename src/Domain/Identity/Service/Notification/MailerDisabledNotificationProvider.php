<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Running in testmode, no emails will be sent". There is no TEST constant here;
 * the equivalent is a MAILER_DSN using the "null" transport, which silently discards every message.
 * Only the DSN scheme is inspected, as the rest may contain credentials.
 */
class MailerDisabledNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.mailer_dsn%')] private readonly string $mailerDsn,
    ) {
    }

    public function getNotifications(Administrator $administrator): array
    {
        if (!$administrator->isSuperUser() || !$this->usesNullTransport()) {
            return [];
        }

        return [
            new AdminNotification(
                code: 'mailer_disabled',
                level: AdminNotification::LEVEL_WARNING,
                message: $this->translator->trans(
                    'The mailer uses the "null" transport, no emails will be sent. Check MAILER_DSN.'
                ),
            ),
        ];
    }

    private function usesNullTransport(): bool
    {
        return preg_match('#^\s*null(\+[a-z0-9]+)?://#i', $this->mailerDsn) === 1;
    }
}

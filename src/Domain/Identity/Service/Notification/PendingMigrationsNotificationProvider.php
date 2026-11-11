<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Notification;

use Doctrine\Migrations\DependencyFactory;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\AdminNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Tells superusers when the database schema is behind the code, i.e. there are migrations that have not been run.
 * A missing migrations table (fresh or legacy install) counts as "everything pending".
 */
class PendingMigrationsNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getNotifications(Administrator $administrator): array
    {
        if (!$administrator->isSuperUser()) {
            return [];
        }

        try {
            $count = count($this->dependencyFactory->getMigrationStatusCalculator()->getNewMigrations());
        } catch (Throwable $e) {
            $this->logger->warning('Unable to determine pending migrations', ['exception' => $e]);

            return [];
        }

        if ($count === 0) {
            return [];
        }

        return [
            new AdminNotification(
                code: 'pending_migrations',
                level: AdminNotification::LEVEL_WARNING,
                message: $this->translator->trans(
                    '%count% database migration(s) have not been run. '
                    . 'Run "bin/console doctrine:migrations:migrate" to update the database',
                    ['%count%' => $count]
                ),
                context: ['count' => $count],
            ),
        ];
    }
}

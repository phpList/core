<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Version\Direction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Doctrine Migrations does not record a migration that skipped itself (skipIf()) in the versions table, so
 * platform specific migrations (e.g. the PostgreSQL ones on MySQL) and "schema already exists" skips would
 * stay "not migrated" forever, and be reported as pending on every run.
 * Recording them as executed once they have been skipped makes the migration status reflect reality.
 */
#[AsDoctrineListener(event: Events::onMigrationsVersionSkipped)]
class SkippedMigrationRecorder
{
    public function __construct(
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
    ) {
    }

    public function onMigrationsVersionSkipped(MigrationsVersionEventArgs $args): void
    {
        $plan = $args->getPlan();
        $result = $plan->getResult();

        if ($result === null
            || $plan->getDirection() !== Direction::UP
            || $args->getMigratorConfiguration()->isDryRun()
        ) {
            return;
        }

        $this->dependencyFactory->getMetadataStorage()->complete($result);
    }
}

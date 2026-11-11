<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use PhpList\Core\Core\Doctrine\SkippedMigrationRecorder;
use PHPUnit\Framework\TestCase;

class SkippedMigrationRecorderTest extends TestCase
{
    private function args(string $direction, bool $dryRun, bool $withResult = true): MigrationsVersionEventArgs
    {
        $version = new Version('Version1');
        $plan = new MigrationPlan($version, $this->createMock(AbstractMigration::class), $direction);
        if ($withResult) {
            $plan->markAsExecuted(new ExecutionResult($version, $direction, new DateTimeImmutable()));
        }

        return new MigrationsVersionEventArgs(
            $this->createMock(Connection::class),
            $plan,
            (new MigratorConfiguration())->setDryRun($dryRun)
        );
    }

    private function recorder(int $expectedCalls): SkippedMigrationRecorder
    {
        $storage = $this->createMock(MetadataStorage::class);
        $storage->expects(self::exactly($expectedCalls))->method('complete');
        $factory = $this->createMock(DependencyFactory::class);
        $factory->method('getMetadataStorage')->willReturn($storage);

        return new SkippedMigrationRecorder($factory);
    }

    public function testRecordsSkippedUpMigration(): void
    {
        $this->recorder(1)->onMigrationsVersionSkipped($this->args(Direction::UP, false));
    }

    public function testDoesNotRecordOnDryRun(): void
    {
        $this->recorder(0)->onMigrationsVersionSkipped($this->args(Direction::UP, true));
    }

    public function testDoesNotRecordSkippedDownMigration(): void
    {
        $this->recorder(0)->onMigrationsVersionSkipped($this->args(Direction::DOWN, false));
    }

    public function testDoesNothingWithoutResult(): void
    {
        $this->recorder(0)->onMigrationsVersionSkipped($this->args(Direction::UP, false, false));
    }
}

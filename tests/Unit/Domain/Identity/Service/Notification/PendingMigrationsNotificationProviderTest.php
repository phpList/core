<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsList;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use Doctrine\Migrations\Version\Version;
use Doctrine\Migrations\AbstractMigration;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\Notification\PendingMigrationsNotificationProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PendingMigrationsNotificationProviderTest extends TestCase
{
    private function provider(
        MigrationStatusCalculator|RuntimeException $calculator
    ): PendingMigrationsNotificationProvider {
        $factory = $this->createMock(DependencyFactory::class);
        if ($calculator instanceof RuntimeException) {
            $factory->method('getMigrationStatusCalculator')->willThrowException($calculator);
        } else {
            $factory->method('getMigrationStatusCalculator')->willReturn($calculator);
        }

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new PendingMigrationsNotificationProvider(
            $factory,
            $translator,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function calculator(int $pending): MigrationStatusCalculator
    {
        $migrations = [];
        for ($i = 1; $i <= $pending; $i++) {
            $migrations[] = new AvailableMigration(
                new Version('Version' . $i),
                $this->createMock(AbstractMigration::class)
            );
        }
        $calculator = $this->createMock(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willReturn(new AvailableMigrationsList($migrations));

        return $calculator;
    }

    private function admin(bool $super): Administrator
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('isSuperUser')->willReturn($super);

        return $admin;
    }

    public function testNoPendingMigrationsProducesNothing(): void
    {
        self::assertSame([], $this->provider($this->calculator(0))->getNotifications($this->admin(true)));
    }

    public function testPendingMigrationsProduceWarning(): void
    {
        $result = $this->provider($this->calculator(2))->getNotifications($this->admin(true));

        self::assertCount(1, $result);
        self::assertSame('pending_migrations', $result[0]->code);
        self::assertSame('warning', $result[0]->level);
        self::assertSame(['count' => 2], $result[0]->context);
    }

    public function testNonSuperUserGetsNothing(): void
    {
        self::assertSame([], $this->provider($this->calculator(2))->getNotifications($this->admin(false)));
    }

    public function testFailureIsSwallowed(): void
    {
        $provider = $this->provider(new RuntimeException('db down'));

        self::assertSame([], $provider->getNotifications($this->admin(true)));
    }
}

<?php

declare(strict_types=1);

namespace <namespace>;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class <className> extends AbstractMigration
{
    public function getDescription(): string
    {
        return '<comment>';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof <platform>, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        <up>
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof <platform>, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        <down>
    }
}

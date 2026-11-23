<?php

declare(strict_types=1);

namespace <namespace>;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * Generated migration: review before committing.
 * - It runs on fresh installs and on existing phpList3 databases, whose index names vary:
 *   use the AbstractPrefixedMigration index helpers instead of plain RENAME/DROP INDEX.
 * - Remove MySQL-8-only collations (e.g. utf8mb4_0900_ai_ci), MariaDB rejects them.
 */
final class <className> extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return '';
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

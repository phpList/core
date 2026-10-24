<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20260820120000MySqlAddMessageStatusIndex extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Add composite (status, id) index on phplist_message to support status-filtered cursor pagination.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('CREATE INDEX phplist_message_statusidx ON phplist_message (status, id)');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DROP INDEX phplist_message_statusidx ON phplist_message');
    }
}

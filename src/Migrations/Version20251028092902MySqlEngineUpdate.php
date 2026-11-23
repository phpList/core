<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20251028092902MySqlEngineUpdate extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Convert legacy (MyISAM) phpList tables to InnoDB so foreign keys can be added.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        // Legacy phpList installs often created their tables as MyISAM (phpList 3 only converts them in its
        // command line upgrade). MyISAM tables cannot take part in the InnoDB foreign keys added by the next
        // migration (MySQL error 1824: Failed to open the referenced table), so convert every phpList table
        // that is not InnoDB yet. The engine can differ per table, so each one is checked.
        $tableNames = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND ENGINE <> 'InnoDB'
              AND TABLE_NAME LIKE ?",
            [addcslashes($this->getTablePrefix(), '%_\\') . '%']
        );

        foreach ($tableNames as $tableName) {
            $this->addSql(sprintf('ALTER TABLE `%s` ENGINE=InnoDB', $tableName));
        }
    }

    public function down(Schema $schema): void
    {
        // converting back to MyISAM would break the foreign keys added afterwards, so nothing is undone
    }
}

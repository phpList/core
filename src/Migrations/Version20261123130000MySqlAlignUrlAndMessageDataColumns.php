<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * - phplist_urlcache.url: phpList 3's 3.3.4 upgrade widened it with "modify url varchar(2083)", which also
 *   made it nullable on upgraded installs. A cache row without a URL is useless, so those are removed.
 * - phplist_messagedata: utf8mb4 is moved from the data column to the whole table. A column whose charset
 *   differs from its table reports the server's default collation (utf8mb4_0900_ai_ci on MySQL 8,
 *   utf8mb4_general_ci on MariaDB), so the schema never matched the mapping.
 */
final class Version20261123130000MySqlAlignUrlAndMessageDataColumns extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Make phplist_urlcache.url NOT NULL and convert phplist_messagedata to utf8mb4.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DELETE FROM phplist_urlcache WHERE url IS NULL');
        $this->addSql('ALTER TABLE phplist_urlcache CHANGE url url VARCHAR(2083) NOT NULL');
        $this->addSql('ALTER TABLE phplist_messagedata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        // phplist_messagedata stays utf8mb4: converting back to utf8mb3 would mangle 4-byte characters
        $this->addSql('ALTER TABLE phplist_urlcache CHANGE url url VARCHAR(2083) DEFAULT NULL');
    }
}

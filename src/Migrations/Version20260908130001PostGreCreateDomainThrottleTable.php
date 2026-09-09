<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20260908130001PostGreCreateDomainThrottleTable extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Create phplist_domain_throttle table for persisted, cross-worker domain send-rate throttling.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql(
            'CREATE TABLE phplist_domain_throttle (
                domain VARCHAR(255) NOT NULL,
                window_start INT NOT NULL,
                sent_count INT NOT NULL DEFAULT 0,
                blocked_count INT NOT NULL DEFAULT 0,
                PRIMARY KEY (domain)
            )'
        );
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DROP TABLE phplist_domain_throttle');
    }
}
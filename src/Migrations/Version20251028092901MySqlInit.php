<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manual Migration
 */
final class Version20251028092901MySqlInit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema from SQL file generated from phplist3';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(
            !$platform instanceof MySQLPlatform,
            sprintf(
                'This migration is only applicable for MySQL. Current platform: %s',
                get_class($platform)
            )
        );

        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();
        $hasUserStats = array_filter(
            $tables,
            static fn (string $table): bool =>
            str_ends_with($table, 'userstats')
        );
        $this->skipIf(
            !empty($hasUserStats),
            'phpList schema already exists.'
        );

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(file_get_contents(__DIR__.'/initial_schema.sql'));
    }
}

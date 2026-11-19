<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * Manual Migration
 */
final class Version20251028092901MySqlInit extends AbstractPrefixedMigration
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

        // TODO: check the migrations against an empty database and fix what breaks
        // (base-distribution CI "Check the migrations build a schema matching the Doctrine mapping" does this):
        // - The whole dump is sent as one multi-statement query. PDO only reports an error in the first statement,
        //   so if a later CREATE TABLE fails (e.g. the table already exists) this migration is still marked as
        //   executed with a partial schema, and the next migration fails on a missing table instead.
        //   Run the statements one by one (or use CREATE TABLE IF NOT EXISTS) so failures surface here.
        // - The "schema already exists" check above uses the schema manager, which OnlyOrmTablesFilter limits to
        //   ORM tables, so leftover non-ORM tables from the dump (phplist_i18n, phplist_listattr_*) are not seen.
        // - Version20260827065637 fails on a fresh install, see the TODO there.
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(file_get_contents(__DIR__.'/initial_schema.sql'));
    }
}

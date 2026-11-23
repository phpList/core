<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * Creates the phpList3 schema on an empty MySQL database. On an existing phpList 3 database it is skipped,
 * and the migrations after it bring the legacy schema up to date.
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

        $this->skipIf(
            $this->tableExists($this->getPrefixedTableName('phplist_userstats')),
            'phpList schema already exists.'
        );

        $existingTables = $this->findExistingTables();
        $this->abortIf(
            $existingTables !== [],
            sprintf(
                'Found phpList tables (%s) but no %s table: the database looks partially initialised. '
                . 'Fix or empty it before migrating.',
                implode(', ', $existingTables),
                $this->getPrefixedTableName('phplist_userstats')
            )
        );

        // Queue the dump statement by statement: sent as one multi-statement query, PDO only reports an error
        // in the first statement, so a failing CREATE TABLE further down would go unnoticed.
        foreach ($this->splitStatements((string) file_get_contents(__DIR__ . '/initial_schema.sql')) as $statement) {
            $this->addSql($statement);
        }
    }

    /**
     * Uses information_schema rather than the schema manager: OnlyOrmTablesFilter hides tables that are not
     * mapped by the ORM (phplist_i18n, phplist_listattr_*, ...).
     */
    private function tableExists(string $tableName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        ) > 0;
    }

    /** @return string[] */
    private function findExistingTables(): array
    {
        $tableNames = [];
        foreach ($this->splitStatements((string) file_get_contents(__DIR__ . '/initial_schema.sql')) as $statement) {
            if (preg_match('/^CREATE TABLE `([^`]+)`/', $statement, $matches)) {
                $tableNames[] = $this->getPrefixedTableName($matches[1]);
            }
        }

        return array_values(array_filter($tableNames, fn (string $tableName): bool => $this->tableExists($tableName)));
    }

    /** @return string[] */
    private function splitStatements(string $sql): array
    {
        // drop "-- " comment lines; the dump has no semicolons inside statements
        $sql = (string) preg_replace('/^--.*$/m', '', $sql);

        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $statement): bool => $statement !== ''
        ));
    }
}

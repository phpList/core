<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrations only receive a Connection and a Logger from Doctrine's migration factory (no DI container access),
 * so the configured table prefix is read directly from the environment here rather than injected.
 *
 * The index helpers below exist because the MySQL migrations have to run against two kinds of databases:
 * a fresh one created from initial_schema.sql, and an existing phpList3 database. phpList3 never
 * normalised index names on upgrade, so an install carries whatever index names the version it was
 * first created with used. Every index operation on a legacy table must therefore check what is there
 * instead of assuming a name. The checks use the $schema Doctrine introspects before each migration,
 * so they reflect the database state before the SQL queued by that migration runs.
 */
abstract class AbstractPrefixedMigration extends AbstractMigration
{
    private const DEFAULT_PREFIX = 'phplist_';

    private ?bool $supportsRenameIndex = null;

    protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        parent::addSql(
            str_replace(
                self::DEFAULT_PREFIX,
                $this->getTablePrefix(),
                $sql
            ),
            $params,
            $types
        );
    }

    /**
     * Renames whichever of the candidate legacy index names is present to $newIndexName, or creates the
     * target index if none of them is. Does nothing if the target index already exists.
     *
     * @param string[] $possibleOldIndexNames
     * @param string[] $columns column definitions, prefix lengths allowed (e.g. 'url(255)')
     */
    protected function renameOrCreateIndex(
        Schema $schema,
        string $tableName,
        array $possibleOldIndexNames,
        string $newIndexName,
        array $columns,
        bool $unique = false
    ): void {
        if ($this->indexExists($schema, $tableName, $newIndexName)) {
            return;
        }

        foreach ($possibleOldIndexNames as $oldIndexName) {
            if ($this->indexExists($schema, $tableName, $oldIndexName)) {
                $this->renameIndex($tableName, $oldIndexName, $newIndexName, $columns, $unique);

                return;
            }
        }

        $this->addSql(sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique ? 'UNIQUE ' : '',
            $newIndexName,
            $tableName,
            implode(', ', $columns)
        ));
    }

    /**
     * Makes sure $indexName exists with the given columns and uniqueness, re-creating it if it differs.
     *
     * @param string[] $columns plain column names
     */
    protected function ensureIndex(
        Schema $schema,
        string $tableName,
        string $indexName,
        array $columns,
        bool $unique = false
    ): void {
        if ($this->indexExists($schema, $tableName, $indexName)) {
            $table = $schema->getTable($this->getPrefixedTableName($tableName));
            $index = $table->hasIndex($indexName)
                ? $table->getIndex($indexName)
                : $table->getIndex($this->getPrefixedTableName($indexName));

            $hasExpectedColumns = array_map('strtolower', $index->getUnquotedColumns())
                === array_map('strtolower', $columns);
            // MySQL introspection reports a length per column, null meaning "whole column"
            $hasPrefixLengths = $index->hasOption('lengths') && array_filter($index->getOption('lengths')) !== [];
            if ($hasExpectedColumns && $index->isUnique() === $unique && !$hasPrefixLengths) {
                return;
            }
            $this->dropIndexIfExists($schema, $tableName, $indexName);
        }

        $this->addSql(sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique ? 'UNIQUE ' : '',
            $indexName,
            $tableName,
            implode(', ', $columns)
        ));
    }

    protected function dropIndexIfExists(Schema $schema, string $tableName, string $indexName): void
    {
        if ($this->indexExists($schema, $tableName, $indexName)) {
            $this->addSql(sprintf('DROP INDEX `%s` ON %s', $indexName, $tableName));
        }
    }

    protected function indexExists(Schema $schema, string $tableName, string $indexName): bool
    {
        $prefixedTableName = $this->getPrefixedTableName($tableName);
        if (!$schema->hasTable($prefixedTableName)) {
            return false;
        }
        $table = $schema->getTable($prefixedTableName);

        // addSql() rewrites the default prefix in index names too, so look for both spellings
        return $table->hasIndex($indexName) || $table->hasIndex($this->getPrefixedTableName($indexName));
    }

    protected function hasPrimaryKey(Schema $schema, string $tableName): bool
    {
        $prefixedTableName = $this->getPrefixedTableName($tableName);

        return $schema->hasTable($prefixedTableName) && $schema->getTable($prefixedTableName)->getPrimaryKey() !== null;
    }

    protected function getPrefixedTableName(string $tableName): string
    {
        return str_replace(self::DEFAULT_PREFIX, $this->getTablePrefix(), $tableName);
    }

    protected function getTablePrefix(): string
    {
        $prefix = $_ENV['DATABASE_PREFIX'] ?? getenv('DATABASE_PREFIX');

        return is_string($prefix) && $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    /**
     * RENAME INDEX only changes metadata, so it is used where available. MariaDB supports it from 10.5.2 on;
     * older MariaDB servers (common on phpList 3 installs) get the index dropped and re-created instead.
     *
     * @param string[] $columns
     */
    private function renameIndex(
        string $tableName,
        string $oldIndexName,
        string $newIndexName,
        array $columns,
        bool $unique
    ): void {
        if (!$this->supportsRenameIndex()) {
            $this->addSql(sprintf(
                'ALTER TABLE %s DROP INDEX `%s`, ADD %sINDEX %s (%s)',
                $tableName,
                $oldIndexName,
                $unique ? 'UNIQUE ' : '',
                $newIndexName,
                implode(', ', $columns)
            ));

            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s RENAME INDEX `%s` TO %s', $tableName, $oldIndexName, $newIndexName));
    }

    /**
     * Asks the server instead of checking the DBAL platform class: DBAL reports MariaDB older than 10.2.7
     * as plain MySQLPlatform.
     */
    private function supportsRenameIndex(): bool
    {
        if ($this->supportsRenameIndex === null) {
            $version = (string) $this->connection->fetchOne('SELECT VERSION()');
            $isMariaDb = stripos($version, 'mariadb') !== false;
            // MariaDB behind MySQL replication reports e.g. "5.5.5-10.1.48-MariaDB"
            $number = preg_match('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+)/', $version, $matches) ? $matches[1] : '0.0.0';

            $this->supportsRenameIndex = version_compare($number, $isMariaDb ? '10.5.2' : '5.7.0', '>=');
        }

        return $this->supportsRenameIndex;
    }
}

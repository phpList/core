<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrations only receive a Connection and a Logger from Doctrine's migration factory (no DI container access),
 * so the configured table prefix is read directly from the environment here rather than injected.
 */
abstract class AbstractPrefixedMigration extends AbstractMigration
{
    private const DEFAULT_PREFIX = 'phplist_';

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
     * Legacy phpList dumps don't all carry the same set of index names (older exports predate
     * some indexes entirely), so a hardcoded RENAME INDEX can fail against a given dump. This
     * renames whichever of the candidate legacy names is actually present, or creates the target
     * index fresh if none of them are.
     */
    protected function renameOrCreateIndex(
        Schema $schema,
        string $tableName,
        array $possibleOldIndexNames,
        string $newIndexName,
        array $columns
    ): void {
        $table = $schema->getTable($this->getPrefixedTableName($tableName));

        foreach ($possibleOldIndexNames as $oldIndexName) {
            if ($table->hasIndex($oldIndexName)) {
                $this->addSql(sprintf('ALTER TABLE %s RENAME INDEX %s TO %s', $tableName, $oldIndexName, $newIndexName));

                return;
            }
        }

        if (!$table->hasIndex($newIndexName)) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $newIndexName, $tableName, implode(', ', $columns)));
        }
    }

    private function getPrefixedTableName(string $tableName): string
    {
        return str_replace(self::DEFAULT_PREFIX, $this->getTablePrefix(), $tableName);
    }

    private function getTablePrefix(): string
    {
        $prefix = $_ENV['DATABASE_PREFIX'] ?? getenv('DATABASE_PREFIX');

        return is_string($prefix) && $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

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

    private function getTablePrefix(): string
    {
        $prefix = $_ENV['DATABASE_PREFIX'] ?? getenv('DATABASE_PREFIX');

        return is_string($prefix) && $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }
}

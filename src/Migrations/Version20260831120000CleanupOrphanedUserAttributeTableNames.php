<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * phplist_user_attribute.tablename points at a dynamically created options table
 * (e.g. select/radio/checkbox attributes) named {DATABASE_PREFIX}{LIST_TABLE_PREFIX}{tablename}.
 * Older/imported data can reference a table that was since dropped or never existed,
 * which breaks lookups against it. This clears tablename for any such orphaned reference.
 */
final class Version20260831120000CleanupOrphanedUserAttributeTableNames extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Set phplist_user_attribute.tablename to NULL when the referenced dynamic table no longer exists.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $databasePrefix = $this->getEnv('DATABASE_PREFIX', 'phplist_');
        $listTablePrefix = $this->getEnv('LIST_TABLE_PREFIX', 'listattr_');
        $userAttributeTable = $databasePrefix . 'user_attribute';

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, tablename FROM %s WHERE tablename IS NOT NULL', $userAttributeTable)
        );

        foreach ($rows as $row) {
            $dynamicTableName = $databasePrefix . $listTablePrefix . $row['tablename'];

            if (!$this->tableExists($dynamicTableName)) {
                $this->connection->update(
                    $userAttributeTable,
                    ['tablename' => null],
                    ['id' => $row['id']]
                );
            }
        }
    }

    /**
     * Doesn't use the DBAL schema manager here: the 'default' connection has
     * OnlyOrmTablesFilter registered as a doctrine.dbal.schema_filter, which hides any
     * table not backed by ORM entity metadata. Dynamic list-attribute tables like
     * phplist_listattr_countries are created ad-hoc and aren't mapped entities, so
     * tablesExist() would always report them as missing. Querying information_schema
     * directly bypasses that filter.
     */
    private function tableExists(string $tableName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ? AND table_schema = DATABASE()',
            [$tableName]
        );

        return (int) $count > 0;
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('The original tablename values cannot be recovered once cleared.');
    }

    private function getEnv(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}

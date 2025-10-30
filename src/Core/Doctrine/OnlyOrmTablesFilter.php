<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;

class OnlyOrmTablesFilter
{
    /** @var string[]|null */
    private ?array $allow = null;
    /** @var string[]|null */
    private ?array $allowPrefixes = null;

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function __invoke(string|AbstractAsset $asset): bool
    {
        $name = \is_string($asset) ? $asset : $asset->getName();

        if (false !== ($pos = strrpos($name, '.'))) {
            $name = substr($name, $pos + 1);
        }
        $nameLower = strtolower($name);

        [$allow, $allowPrefixes] = $this->buildAllowOnce();

        if (\is_string($asset) || $asset instanceof Table) {
            return \in_array($nameLower, $allow, true);
        }

        // PostgreSQL sequences: allow those that belong to our ORM tables
        // (default naming: {table}_{column}_seq, so we check table_ prefix)
        if ($asset instanceof Sequence) {
            foreach ($allowPrefixes as $prefix) {
                if (str_starts_with($nameLower, $prefix)) {
                    return true;
                }
            }
            // Disallow unrelated sequences
            return false;
        }

        // Other dependent assets (indexes, FKs) are tied to allowed tables → allow
        return true;
    }

    private function buildAllowOnce(): array
    {
        if ($this->allow !== null) {
            return [$this->allow, $this->allowPrefixes];
        }

        $tables = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $m) {
            if ($t = $m->getTableName()) {
                $tables[] = strtolower($t);
            }
            // many-to-many join tables
            foreach ($m->getAssociationMappings() as $assoc) {
                if (!empty($assoc['joinTable']['name'])) {
                    $tables[] = strtolower($assoc['joinTable']['name']);
                }
            }
        }

        $tables[] = 'doctrine_migration_versions';

        $tables = array_values(array_unique($tables));
        $prefixes = array_map(static fn($t) => $t . '_', $tables);

        $this->allow = $tables;
        $this->allowPrefixes = $prefixes;

        return [$this->allow, $this->allowPrefixes];
    }
}

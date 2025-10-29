<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

class OnlyOrmTablesFilter
{
    /** @var string[]|null */
    private ?array $allow = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(string $assetName): bool
    {
        // asset names can be "schema.table" ➜ normalize
        $pos = strrpos($assetName, '.');
        if ($pos !== false) {
            $assetName = substr($assetName, $pos + 1);
        }

        // Build the whitelist lazily to avoid touching the EM during container compilation or early boot.
        if ($this->allow === null) {
            $names = [];
            foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $m) {
                // main table
                $table = $m->getTableName();
                if ($table) {
                    $names[] = $table;
                }

                // many-to-many join tables
                foreach ($m->getAssociationMappings() as $assoc) {
                    if (isset($assoc['joinTable']['name'])) {
                        $join = $assoc['joinTable']['name'];
                        if ($join) {
                            $names[] = $join;
                        }
                    }
                }
            }

            // keep the migration table visible too
            $names[] = 'doctrine_migration_versions';

            $this->allow = array_values(array_unique($names));
        }

        return in_array($assetName, $this->allow, true);
    }
}

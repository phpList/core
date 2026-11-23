<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Some phpList3 indexes cover only a prefix of a long column (e.g. url(255) on VARCHAR(2083) columns),
 * which entities declare with the index option "lengths". Only MySQL knows prefix indexes: on other
 * platforms the option is ignored when creating the index, but DBAL still compares it, so schema diffs
 * would never come out clean. This drops the option from the schema generated from the mapping on
 * platforms other than MySQL.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
class IndexPrefixLengthListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $platform = $eventArgs->getEntityManager()->getConnection()->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($eventArgs->getSchema()->getTables() as $table) {
            foreach ($table->getIndexes() as $index) {
                if ($index->isPrimary() || !$index->hasOption('lengths')) {
                    continue;
                }

                $options = $index->getOptions();
                unset($options['lengths']);

                $table->dropIndex($index->getName());
                if ($index->isUnique()) {
                    $table->addUniqueIndex($index->getColumns(), $index->getName(), $options);
                } else {
                    $table->addIndex($index->getColumns(), $index->getName(), $index->getFlags(), $options);
                }
            }
        }
    }
}

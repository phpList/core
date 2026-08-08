<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
class TablePrefixListener
{
    public function __construct(private readonly string $tablePrefix)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return;
        }

        if (!str_starts_with($metadata->getName(), 'PhpList\\Core\\Domain\\')) {
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $this->tablePrefix . $metadata->getTableName(),
        ]);
    }
}
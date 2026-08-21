<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
class TablePrefixListener
{
    /**
     * Namespace prefixes of entities that should be prefixed with the app's table prefix. Bundles that ship
     * their own entities (e.g. TatevikGr\RssFeedBundle) don't know about this convention on their own, so
     * their namespace has to be opted in here explicitly.
     */
    private const PREFIXED_NAMESPACES = [
        'PhpList\\Core\\Domain\\',
        'TatevikGr\\RssFeedBundle\\Entity\\',
    ];

    public function __construct(private readonly string $tablePrefix)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return;
        }

        $isPrefixed = false;
        foreach (self::PREFIXED_NAMESPACES as $namespace) {
            if (str_starts_with($metadata->getName(), $namespace)) {
                $isPrefixed = true;
                break;
            }
        }

        if (!$isPrefixed) {
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $this->tablePrefix . $metadata->getTableName(),
        ]);
    }
}

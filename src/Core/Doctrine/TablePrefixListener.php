<?php

declare(strict_types=1);

namespace PhpList\Core\Core\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

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

        $this->prefixSequence($metadata);
    }

    /**
     * On platforms that use sequences (PostgreSQL) the ORM derives the default sequence name
     * ({table}_{column}_seq) from the unprefixed table name and builds the id generator before this
     * event, so both have to be prefixed here as well, or inserts would use a sequence that doesn't exist.
     */
    private function prefixSequence(ClassMetadata $metadata): void
    {
        if (!$metadata->isIdGeneratorSequence() || !isset($metadata->sequenceGeneratorDefinition)) {
            return;
        }

        $definition = $metadata->sequenceGeneratorDefinition;
        if (str_starts_with($definition['sequenceName'], $this->tablePrefix)) {
            return;
        }

        $definition['sequenceName'] = $this->tablePrefix . $definition['sequenceName'];
        $metadata->setSequenceGeneratorDefinition($definition);
        $metadata->setIdGenerator(new SequenceGenerator(
            $definition['sequenceName'],
            (int) $definition['allocationSize']
        ));
    }
}

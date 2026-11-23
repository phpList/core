<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use PhpList\Core\Core\Doctrine\TablePrefixListener;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PHPUnit\Framework\TestCase;

class TablePrefixListenerTest extends TestCase
{
    private function metadata(string $className, string $tableName): ClassMetadata
    {
        $metadata = new ClassMetadata($className);
        $metadata->setPrimaryTable(['name' => $tableName]);

        return $metadata;
    }

    private function dispatch(ClassMetadata $metadata, string $prefix = 'phplist_'): void
    {
        (new TablePrefixListener($prefix))->loadClassMetadata(
            new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class))
        );
    }

    public function testPrefixesTableName(): void
    {
        $metadata = $this->metadata(Administrator::class, 'admin');

        $this->dispatch($metadata);

        $this->assertSame('phplist_admin', $metadata->getTableName());
    }

    public function testLeavesTablesOfOtherNamespacesAlone(): void
    {
        $metadata = $this->metadata('Some\\Other\\Entity', 'admin');

        $this->dispatch($metadata);

        $this->assertSame('admin', $metadata->getTableName());
    }

    public function testPrefixesDefaultSequenceAndIdGenerator(): void
    {
        $metadata = $this->metadata(Administrator::class, 'admin');
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_SEQUENCE);
        $metadata->setSequenceGeneratorDefinition(['sequenceName' => 'admin_id_seq', 'allocationSize' => 1]);
        $metadata->setIdGenerator(new SequenceGenerator('admin_id_seq', 1));

        $this->dispatch($metadata);

        $this->assertSame('phplist_admin_id_seq', $metadata->sequenceGeneratorDefinition['sequenceName']);
        $generator = $metadata->idGenerator;
        $this->assertInstanceOf(SequenceGenerator::class, $generator);
        $this->assertSame('phplist_admin_id_seq', $generator->__serialize()['sequenceName']);
    }

    public function testDoesNotPrefixSequenceTwice(): void
    {
        $metadata = $this->metadata(Administrator::class, 'admin');
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_SEQUENCE);
        $metadata->setSequenceGeneratorDefinition(['sequenceName' => 'phplist_admin_id_seq', 'allocationSize' => 1]);

        $this->dispatch($metadata);

        $this->assertSame('phplist_admin_id_seq', $metadata->sequenceGeneratorDefinition['sequenceName']);
    }

    public function testIgnoresIdentityGenerators(): void
    {
        $metadata = $this->metadata(Administrator::class, 'admin');
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);

        $this->dispatch($metadata);

        $this->assertNull($metadata->sequenceGeneratorDefinition);
    }
}

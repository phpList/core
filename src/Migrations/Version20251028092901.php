<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manual Migration
 */
final class Version20251028092901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @SuppressWarnings(PHPMD.ShortMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(
            !$platform instanceof MySQLPlatform,
            sprintf(
                'This migration is only applicable for MySQL. Current platform: %s',
                get_class($platform)
            )
        );
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(file_get_contents(__DIR__.'/initial_schema.sql'));
    }
}

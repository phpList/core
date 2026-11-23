<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * PostgreSQL counterpart of the mapping changes Version20260827065637 made on MySQL only.
 * PostgreSQL is only supported for new installs, so no legacy data has to be accounted for.
 */
final class Version20261123120001PostGreAlignSchema extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Align the PostgreSQL schema with the mapping (message.processed, NOT NULL columns, indexes).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DELETE FROM phplist_admintoken WHERE adminid IS NULL');
        $this->addSql('ALTER TABLE phplist_admintoken ALTER adminid SET NOT NULL');
        $this->addSql('CREATE INDEX phplist_linktrack_latestclickindex ON phplist_linktrack (latestclick)');
        $this->addSql('ALTER TABLE phplist_message ADD processed INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE phplist_message ALTER astext DROP DEFAULT');
        $this->addSql('ALTER TABLE phplist_message ALTER ashtml DROP DEFAULT');
        $this->addSql('ALTER TABLE phplist_message ALTER aspdf DROP DEFAULT');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandhtml DROP DEFAULT');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandpdf DROP DEFAULT');
        $this->addSql('CREATE INDEX phplist_message_sentidx ON phplist_message (sent)');
        $this->addSql("UPDATE phplist_user_user SET uniqid = MD5(id::text || '-' || random()::text || clock_timestamp()::text) WHERE uniqid IS NULL");
        $this->addSql('ALTER TABLE phplist_user_user ALTER uniqid SET NOT NULL');
        $this->addSql('ALTER TABLE phplist_list ALTER description DROP NOT NULL');
        $this->addSql('ALTER TABLE phplist_list ALTER category DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql("UPDATE phplist_list SET description = COALESCE(description, ''), category = COALESCE(category, '')");
        $this->addSql('ALTER TABLE phplist_list ALTER description SET NOT NULL');
        $this->addSql('ALTER TABLE phplist_list ALTER category SET NOT NULL');
        $this->addSql('ALTER TABLE phplist_user_user ALTER uniqid DROP NOT NULL');
        $this->addSql('DROP INDEX phplist_message_sentidx');
        $this->addSql('ALTER TABLE phplist_message ALTER astext SET DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_message ALTER ashtml SET DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_message ALTER aspdf SET DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandhtml SET DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandpdf SET DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_message DROP processed');
        $this->addSql('DROP INDEX phplist_linktrack_latestclickindex');
        $this->addSql('ALTER TABLE phplist_admintoken ALTER adminid DROP NOT NULL');
    }
}

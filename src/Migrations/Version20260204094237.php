<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
* ⚠️ Wizard warning:
* Doctrine will `helpfully` remove url(255) prefixes and add collations 5.7 can’t read.
* Review the SQL unless you enjoy debugging key length errors at 2 AM.
*
* Ex: phplist_linktrack_forward phplist_linktrack_forward_urlindex (but there are more)
*/
final class Version20260204094237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('ALTER TABLE phplist_message ALTER astext TYPE INT USING astext::integer');
        $this->addSql('ALTER TABLE phplist_message ALTER ashtml TYPE INT USING ashtml::integer');
        $this->addSql('ALTER TABLE phplist_message ALTER aspdf TYPE INT USING aspdf::integer');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandhtml TYPE INT USING astextandhtml::integer');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandpdf TYPE INT USING astextandpdf::integer');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('ALTER TABLE phplist_message ALTER astext TYPE BOOLEAN USING (astext::integer <> 0)');
        $this->addSql('ALTER TABLE phplist_message ALTER ashtml TYPE BOOLEAN USING (ashtml::integer <> 0)');
        $this->addSql('ALTER TABLE phplist_message ALTER aspdf TYPE BOOLEAN USING (aspdf::integer <> 0)');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandhtml TYPE BOOLEAN USING (astextandhtml::integer <> 0)');
        $this->addSql('ALTER TABLE phplist_message ALTER astextandpdf TYPE BOOLEAN USING (astextandpdf::integer <> 0)');
    }
}

<?php

declare(strict_types=1);

namespace <namespace>;

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
final class <className> extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof <platform>, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        <up>
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof <platform>, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        <down>
    }
}

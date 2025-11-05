<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251103SeedInitialAdmin extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed initial admin user';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql(<<<'SQL'
    INSERT INTO phplist_admin (id, created, modified, loginname, namelc, email, password, passwordchanged, disabled, superuser, privileges)
    VALUES (1, NOW(), NOW(), 'admin', 'admin', 'admin@example.com', :hash, CURRENT_DATE, FALSE, TRUE, :privileges)
    ON CONFLICT (id) DO UPDATE
    SET 
        modified = EXCLUDED.modified,
        privileges = EXCLUDED.privileges
    SQL, [
        'hash' => hash('sha256', 'password'),
        'privileges' => 'a:4:{s:11:"subscribers";b:1;s:9:"campaigns";b:1;s:10:"statistics";b:1;s:8:"settings";b:1;}',
        ]);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof PostgreSQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DELETE FROM phplist_admin WHERE id = 1');
    }
}

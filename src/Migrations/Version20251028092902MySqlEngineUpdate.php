<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

final class Version20251028092902MySqlEngineUpdate extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $engine = $this->connection->fetchOne("
            SELECT ENGINE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'phplist_user_user'
        ");

        if ($engine !== 'InnoDB') {
            // legacy phpList installs created these tables as MyISAM, which cannot be referenced by
            // the InnoDB foreign keys added below (MySQL error 1824: Failed to open the referenced table)
            $this->addSql('ALTER TABLE phplist_admin ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_admin_attribute ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_adminattribute ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_admintoken ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_list ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_listmessage ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_listuser ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_message ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_subscribepage ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_template ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_templateimage ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_attribute ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_blacklist ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_blacklist_data ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_user ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_user_attribute ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_user_user_history ENGINE=InnoDB');
            $this->addSql('ALTER TABLE phplist_usermessage ENGINE=InnoDB');
        }
    }

    public function down(Schema $schema): void
    {

    }
}

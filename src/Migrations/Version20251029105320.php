<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029105320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

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
        $this->addSql('ALTER TABLE phplist_admin CHANGE modified modified DATETIME NOT NULL, CHANGE superuser superuser TINYINT(1) NOT NULL, CHANGE disabled disabled TINYINT(1) NOT NULL, CHANGE privileges privileges LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690D3B10C48 FOREIGN KEY (adminattributeid) REFERENCES phplist_adminattribute (id)');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_58E07690D3B10C48 ON phplist_admin_attribute (adminattributeid)');
        $this->addSql('CREATE INDEX IDX_58E07690B8ED4D93 ON phplist_admin_attribute (adminid)');
        $this->addSql('ALTER TABLE phplist_admin_login CHANGE active active TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE phplist_admin_login ADD CONSTRAINT FK_5FCE0842B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_5FCE0842B8ED4D93 ON phplist_admin_login (adminid)');
        $this->addSql('ALTER TABLE phplist_admin_password_request CHANGE id_key id_key INT UNSIGNED AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE phplist_admin_password_request ADD CONSTRAINT FK_DC146F3B880E0D76 FOREIGN KEY (`admin`) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_DC146F3B880E0D76 ON phplist_admin_password_request (`admin`)');
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT DEFAULT NULL, CHANGE value value VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE phplist_admintoken ADD CONSTRAINT FK_CB15D477B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_CB15D477B8ED4D93 ON phplist_admintoken (adminid)');
        $this->addSql('ALTER TABLE phplist_attachment CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX statusidx ON phplist_bounce');
        $this->addSql('ALTER TABLE phplist_bounce CHANGE header header LONGTEXT DEFAULT NULL, CHANGE data data LONGBLOB DEFAULT NULL, CHANGE status status VARCHAR(20) DEFAULT NULL, CHANGE comment comment LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX statusidx ON phplist_bounce (status)');
        $this->addSql('ALTER TABLE phplist_bounceregex CHANGE regexhash regexhash VARCHAR(32) DEFAULT NULL, CHANGE comment comment LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_config CHANGE editable editable TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE phplist_eventlog CHANGE entry entry LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX lanorigidx ON phplist_i18n');
        $this->addSql('DROP INDEX lanorigunq ON phplist_i18n');
        $this->addSql('ALTER TABLE phplist_i18n CHANGE original original VARCHAR(255) NOT NULL, CHANGE translation translation LONGTEXT NOT NULL, ADD PRIMARY KEY (lan, original)');
        $this->addSql('CREATE UNIQUE INDEX lanorigunq ON phplist_i18n (lan, original)');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE latestclick latestclick DATETIME NOT NULL');
        $this->addSql('DROP INDEX urlindex ON phplist_linktrack_forward');
        $this->addSql('ALTER TABLE phplist_linktrack_forward CHANGE url url VARCHAR(255) DEFAULT NULL, CHANGE urlhash urlhash VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX urlindex ON phplist_linktrack_forward (url)');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick CHANGE data data LONGTEXT DEFAULT NULL, ADD PRIMARY KEY (linkid, userid, messageid)');
        $this->addSql('ALTER TABLE phplist_list CHANGE description description VARCHAR(255) NOT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE active active TINYINT(1) NOT NULL, CHANGE category category VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE phplist_list ADD CONSTRAINT FK_A4CE8621CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_A4CE8621CF60E67C ON phplist_list (owner)');
        $this->addSql('ALTER TABLE phplist_listmessage CHANGE modified modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A31478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id)');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A8E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id)');
        $this->addSql('CREATE INDEX IDX_83B22D7A31478478 ON phplist_listmessage (messageid)');
        $this->addSql('CREATE INDEX IDX_83B22D7A8E44C1EF ON phplist_listmessage (listid)');
        $this->addSql('DROP INDEX userlistenteredidx ON phplist_listuser');
        $this->addSql('ALTER TABLE phplist_listuser CHANGE modified modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E411F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id)');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E4118E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX userlistenteredidx ON phplist_listuser (userid, entered, listid)');
        $this->addSql('ALTER TABLE phplist_message CHANGE subject subject VARCHAR(255) DEFAULT \'(no subject)\' NOT NULL, CHANGE message message LONGTEXT DEFAULT NULL, CHANGE textmessage textmessage LONGTEXT DEFAULT NULL, CHANGE footer footer LONGTEXT DEFAULT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE userselection userselection LONGTEXT DEFAULT NULL, CHANGE htmlformatted htmlformatted TINYINT(1) NOT NULL, CHANGE processed processed TINYINT(1) DEFAULT 0 NOT NULL, CHANGE astext astext TINYINT(1) DEFAULT 0 NOT NULL, CHANGE ashtml ashtml TINYINT(1) DEFAULT 0 NOT NULL, CHANGE astextandhtml astextandhtml TINYINT(1) DEFAULT 0 NOT NULL, CHANGE aspdf aspdf TINYINT(1) DEFAULT 0 NOT NULL, CHANGE astextandpdf astextandpdf TINYINT(1) DEFAULT 0 NOT NULL, CHANGE viewed viewed INT DEFAULT 0 NOT NULL, CHANGE bouncecount bouncecount INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCDCF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCD97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C5D81FCDCF60E67C ON phplist_message (owner)');
        $this->addSql('CREATE INDEX IDX_C5D81FCD97601F83 ON phplist_message (template)');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_sendprocess CHANGE modified modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE active active TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage ADD CONSTRAINT FK_5BAC7737CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_5BAC7737CF60E67C ON phplist_subscribepage (owner)');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE data data LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_templateimage CHANGE template template INT NOT NULL');
        $this->addSql('ALTER TABLE phplist_templateimage ADD CONSTRAINT FK_30A85BA97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id)');
        $this->addSql('DROP INDEX urlindex ON phplist_urlcache');
        $this->addSql('ALTER TABLE phplist_urlcache CHANGE url url VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX urlindex ON phplist_urlcache (url)');
        $this->addSql('DROP INDEX email ON phplist_user_blacklist');
        $this->addSql('ALTER TABLE phplist_user_blacklist ADD PRIMARY KEY (email)');
        $this->addSql('DROP INDEX email ON phplist_user_blacklist_data');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE email email VARCHAR(255) NOT NULL, CHANGE data data LONGTEXT DEFAULT NULL, ADD PRIMARY KEY (email)');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data ADD CONSTRAINT FK_6D67150CE7927C74 FOREIGN KEY (email) REFERENCES phplist_user_blacklist (email) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_user_user CHANGE confirmed confirmed TINYINT(1) NOT NULL, CHANGE blacklisted blacklisted TINYINT(1) NOT NULL, CHANGE optedin optedin TINYINT(1) NOT NULL, CHANGE bouncecount bouncecount INT NOT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE uuid uuid VARCHAR(36) NOT NULL, CHANGE htmlemail htmlemail TINYINT(1) NOT NULL, CHANGE passwordchanged passwordchanged DATETIME DEFAULT NULL, CHANGE disabled disabled TINYINT(1) NOT NULL, CHANGE extradata extradata LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_attribute CHANGE value value LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E310878C45AB5 FOREIGN KEY (attributeid) REFERENCES phplist_user_attribute (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E3108F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_user_user_history CHANGE detail detail LONGTEXT DEFAULT NULL, CHANGE systeminfo systeminfo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_history ADD CONSTRAINT FK_6DBB605CF132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F469F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F46931478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(
            !$platform instanceof MySQLPlatform,
            sprintf(
                'This migration is only applicable for MySQL. Current platform: %s',
                get_class($platform)
            )
        );

        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE phplist_admin CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE disabled disabled TINYINT(1) DEFAULT 0, CHANGE superuser superuser TINYINT(1) DEFAULT 0, CHANGE privileges privileges TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_admin_attribute DROP FOREIGN KEY FK_58E07690D3B10C48');
        $this->addSql('ALTER TABLE phplist_admin_attribute DROP FOREIGN KEY FK_58E07690B8ED4D93');
        $this->addSql('DROP INDEX IDX_58E07690D3B10C48 ON phplist_admin_attribute');
        $this->addSql('DROP INDEX IDX_58E07690B8ED4D93 ON phplist_admin_attribute');
        $this->addSql('ALTER TABLE phplist_admin_login DROP FOREIGN KEY FK_5FCE0842B8ED4D93');
        $this->addSql('DROP INDEX IDX_5FCE0842B8ED4D93 ON phplist_admin_login');
        $this->addSql('ALTER TABLE phplist_admin_login CHANGE active active TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE phplist_admin_password_request DROP FOREIGN KEY FK_DC146F3B880E0D76');
        $this->addSql('DROP INDEX IDX_DC146F3B880E0D76 ON phplist_admin_password_request');
        $this->addSql('ALTER TABLE phplist_admin_password_request CHANGE id_key id_key INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE phplist_admintoken DROP FOREIGN KEY FK_CB15D477B8ED4D93');
        $this->addSql('DROP INDEX IDX_CB15D477B8ED4D93 ON phplist_admintoken');
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT NOT NULL, CHANGE value value VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_attachment CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX statusidx ON phplist_bounce');
        $this->addSql('ALTER TABLE phplist_bounce CHANGE header header TEXT DEFAULT NULL, CHANGE data data MEDIUMBLOB DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE comment comment TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX statusidx ON phplist_bounce (status(20))');
        $this->addSql('ALTER TABLE phplist_bounceregex CHANGE regexhash regexhash CHAR(32) DEFAULT NULL, CHANGE comment comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_config CHANGE editable editable TINYINT(1) DEFAULT 1');
        $this->addSql('ALTER TABLE phplist_eventlog CHANGE entry entry TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_i18n DROP INDEX primary, ADD INDEX lanorigidx (lan, original(200))');
        $this->addSql('DROP INDEX lanorigunq ON phplist_i18n');
        $this->addSql('ALTER TABLE phplist_i18n CHANGE original original TEXT NOT NULL, CHANGE translation translation TEXT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX lanorigunq ON phplist_i18n (lan, original(200))');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE latestclick latestclick DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX urlindex ON phplist_linktrack_forward');
        $this->addSql('ALTER TABLE phplist_linktrack_forward CHANGE url url VARCHAR(2083) DEFAULT NULL, CHANGE urlhash urlhash CHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX urlindex ON phplist_linktrack_forward (url(255))');
        $this->addSql('DROP INDEX `primary` ON phplist_linktrack_userclick');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_list DROP FOREIGN KEY FK_A4CE8621CF60E67C');
        $this->addSql('DROP INDEX IDX_A4CE8621CF60E67C ON phplist_list');
        $this->addSql('ALTER TABLE phplist_list CHANGE description description TEXT DEFAULT NULL, CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE active active TINYINT(1) DEFAULT NULL, CHANGE category category VARCHAR(255) DEFAULT \'\'');
        $this->addSql('ALTER TABLE phplist_listmessage DROP FOREIGN KEY FK_83B22D7A31478478');
        $this->addSql('ALTER TABLE phplist_listmessage DROP FOREIGN KEY FK_83B22D7A8E44C1EF');
        $this->addSql('DROP INDEX IDX_83B22D7A31478478 ON phplist_listmessage');
        $this->addSql('DROP INDEX IDX_83B22D7A8E44C1EF ON phplist_listmessage');
        $this->addSql('ALTER TABLE phplist_listmessage CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE phplist_listuser DROP FOREIGN KEY FK_F467E411F132696E');
        $this->addSql('ALTER TABLE phplist_listuser DROP FOREIGN KEY FK_F467E4118E44C1EF');
        $this->addSql('DROP INDEX userlistenteredidx ON phplist_listuser');
        $this->addSql('ALTER TABLE phplist_listuser CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX userlistenteredidx ON phplist_listuser (userid, listid, entered)');
        $this->addSql('ALTER TABLE phplist_message DROP FOREIGN KEY FK_C5D81FCDCF60E67C');
        $this->addSql('ALTER TABLE phplist_message DROP FOREIGN KEY FK_C5D81FCD97601F83');
        $this->addSql('DROP INDEX IDX_C5D81FCDCF60E67C ON phplist_message');
        $this->addSql('DROP INDEX IDX_C5D81FCD97601F83 ON phplist_message');
        $this->addSql('ALTER TABLE phplist_message CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE htmlformatted htmlformatted TINYINT(1) DEFAULT 0, CHANGE astext astext INT DEFAULT 0, CHANGE ashtml ashtml INT DEFAULT 0, CHANGE aspdf aspdf INT DEFAULT 0, CHANGE astextandhtml astextandhtml INT DEFAULT 0, CHANGE astextandpdf astextandpdf INT DEFAULT 0, CHANGE processed processed INT UNSIGNED DEFAULT 0, CHANGE viewed viewed INT DEFAULT 0, CHANGE bouncecount bouncecount INT DEFAULT 0, CHANGE subject subject VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'(no subject)\' NOT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE message message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE textmessage textmessage LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE footer footer TEXT DEFAULT NULL, CHANGE userselection userselection TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE phplist_sendprocess CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage DROP FOREIGN KEY FK_5BAC7737CF60E67C');
        $this->addSql('DROP INDEX IDX_5BAC7737CF60E67C ON phplist_subscribepage');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE active active TINYINT(1) DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_templateimage DROP FOREIGN KEY FK_30A85BA97601F83');
        $this->addSql('ALTER TABLE phplist_templateimage CHANGE template template INT DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX urlindex ON phplist_urlcache');
        $this->addSql('ALTER TABLE phplist_urlcache CHANGE url url VARCHAR(2083) NOT NULL');
        $this->addSql('CREATE INDEX urlindex ON phplist_urlcache (url(255))');
        $this->addSql('ALTER TABLE phplist_user_blacklist DROP INDEX primary, ADD UNIQUE INDEX email (email)');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data DROP INDEX primary, ADD UNIQUE INDEX email (email)');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data DROP FOREIGN KEY FK_6D67150CE7927C74');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE email email VARCHAR(150) NOT NULL, CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE confirmed confirmed TINYINT(1) DEFAULT 0, CHANGE blacklisted blacklisted TINYINT(1) DEFAULT 0, CHANGE bouncecount bouncecount INT DEFAULT 0, CHANGE htmlemail htmlemail TINYINT(1) DEFAULT 0, CHANGE disabled disabled TINYINT(1) DEFAULT 0, CHANGE extradata extradata TEXT DEFAULT NULL, CHANGE optedin optedin TINYINT(1) DEFAULT 0, CHANGE uuid uuid VARCHAR(36) DEFAULT \'\', CHANGE passwordchanged passwordchanged DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP FOREIGN KEY FK_E24E310878C45AB5');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP FOREIGN KEY FK_E24E3108F132696E');
        $this->addSql('ALTER TABLE phplist_user_user_attribute CHANGE value value TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_history DROP FOREIGN KEY FK_6DBB605CF132696E');
        $this->addSql('ALTER TABLE phplist_user_user_history CHANGE detail detail TEXT DEFAULT NULL, CHANGE systeminfo systeminfo TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_usermessage DROP FOREIGN KEY FK_7F30F469F132696E');
        $this->addSql('ALTER TABLE phplist_usermessage DROP FOREIGN KEY FK_7F30F46931478478');
    }
}

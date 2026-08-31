<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
* ⚠️ Wizard warning:
* Doctrine will `helpfully` remove url(255) prefixes and add collations 5.7 can’t read.
* Review the SQL unless you enjoy debugging key length errors at 2 AM.
*
* Ex: phplist_linktrack_forward phplist_linktrack_forward_urlindex (but there are more)
*/
final class Version20260827065637 extends AbstractPrefixedMigration
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

        $this->addSql('DROP INDEX loginname ON phplist_admin');
        $this->addSql('DROP INDEX phplist_admin_loginnameidx ON phplist_admin');
        $this->addSql('DELETE FROM phplist_admin WHERE loginname IS NULL');
        $this->addSql('ALTER TABLE phplist_admin CHANGE loginname loginname VARCHAR(66) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE modifiedby modifiedby VARCHAR(66) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX phplist_admin_loginnameidx ON phplist_admin (loginname)');
        $this->addSql('DELETE FROM phplist_adminattribute WHERE name IS NULL');
        $this->addSql('ALTER TABLE phplist_adminattribute CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('DELETE FROM phplist_admintoken WHERE adminid IS NULL');
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT NOT NULL');
        $this->addSql('ALTER TABLE phplist_config CHANGE item item VARCHAR(35) NOT NULL');
        $this->addSql('DROP INDEX messageid ON phplist_linktrack');
        $this->addSql('DROP INDEX phplist_linktrack_miduidurlindex ON phplist_linktrack');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE forward forward VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX phplist_linktrack_latestclickindex ON phplist_linktrack (latestclick)');
        $this->addSql('CREATE UNIQUE INDEX phplist_linktrack_miduidurlindex ON phplist_linktrack (messageid, userid, url)');
//        $this->addSql('UPDATE phplist_list SET name = COALESCE(name, \'\')');
        $this->addSql('ALTER TABLE phplist_list CHANGE name name VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE category category VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX phplist_message_statusidx ON phplist_message');
        $this->addSql('UPDATE phplist_message SET subject = COALESCE(subject, \'(no subject)\'), fromfield = COALESCE(fromfield, \'\'), tofield = COALESCE(tofield, \'\'), replyto = COALESCE(replyto, \'\'), processed = COALESCE(processed, 0), astext = COALESCE(astext, 0), ashtml = COALESCE(ashtml, 0), astextandhtml = COALESCE(astextandhtml, 0), aspdf = COALESCE(aspdf, 0), astextandpdf = COALESCE(astextandpdf, 0)');
        $this->addSql('ALTER TABLE phplist_message CHANGE subject subject VARCHAR(255) DEFAULT \'(no subject)\' NOT NULL, CHANGE fromfield fromfield VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE tofield tofield VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE replyto replyto VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE message message LONGTEXT DEFAULT NULL, CHANGE textmessage textmessage LONGTEXT DEFAULT NULL, CHANGE processed processed INT UNSIGNED DEFAULT 0 NOT NULL, CHANGE astext astext INT NOT NULL, CHANGE ashtml ashtml INT NOT NULL, CHANGE astextandhtml astextandhtml INT NOT NULL, CHANGE aspdf aspdf INT NOT NULL, CHANGE astextandpdf astextandpdf INT NOT NULL');
        $this->addSql('CREATE INDEX phplist_message_sentidx ON phplist_message (sent)');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql('UPDATE phplist_subscribepage SET title = COALESCE(title, \'\')');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql('UPDATE phplist_template SET title = COALESCE(title, \'\')');
        $this->addSql('ALTER TABLE phplist_template CHANGE title title VARCHAR(255) NOT NULL');
//        $this->addSql('UPDATE phplist_user_attribute SET name = COALESCE(name, \'\')');
        $this->addSql('ALTER TABLE phplist_user_attribute CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX email_2 ON phplist_user_blacklist_data');
//        $this->addSql('UPDATE phplist_user_blacklist_data SET name = LEFT(COALESCE(name, \'\'), 25)');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE name name VARCHAR(25) NOT NULL');
        $this->addSql('DROP INDEX message_lookup ON phplist_user_message_bounce');
        $this->addSql('DROP INDEX emailidx ON phplist_user_user');
//        $this->addSql('UPDATE phplist_user_user SET email = COALESCE(email, \'\'), uniqid = COALESCE(uniqid, \'\')');
        $this->addSql('ALTER TABLE phplist_user_user CHANGE email email VARCHAR(255) NOT NULL, CHANGE uniqid uniqid VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX userattid ON phplist_user_user_attribute');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('DROP INDEX phplist_admin_loginnameidx ON phplist_admin');
        $this->addSql('ALTER TABLE phplist_admin CHANGE loginname loginname VARCHAR(66) DEFAULT \'\', CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE modifiedby modifiedby VARCHAR(66) DEFAULT \'\'');
        $this->addSql('CREATE UNIQUE INDEX loginname ON phplist_admin (loginname)');
        $this->addSql('CREATE INDEX phplist_admin_loginnameidx ON phplist_admin (loginname)');
        $this->addSql('ALTER TABLE phplist_adminattribute CHANGE name name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_config CHANGE item item VARCHAR(35) DEFAULT \'\' NOT NULL');
        $this->addSql('DROP INDEX phplist_linktrack_latestclickindex ON phplist_linktrack');
        $this->addSql('DROP INDEX phplist_linktrack_miduidurlindex ON phplist_linktrack');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE forward forward TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX messageid ON phplist_linktrack (messageid, userid, url)');
        $this->addSql('CREATE INDEX phplist_linktrack_miduidurlindex ON phplist_linktrack (messageid, userid, url)');
        $this->addSql('ALTER TABLE phplist_list CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE category category VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX phplist_message_sentidx ON phplist_message');
        $this->addSql('ALTER TABLE phplist_message CHANGE astext astext INT DEFAULT 0 NOT NULL, CHANGE ashtml ashtml INT DEFAULT 0 NOT NULL, CHANGE aspdf aspdf INT DEFAULT 0 NOT NULL, CHANGE astextandhtml astextandhtml INT DEFAULT 0 NOT NULL, CHANGE astextandpdf astextandpdf INT DEFAULT 0 NOT NULL, CHANGE processed processed INT DEFAULT 0, CHANGE subject subject VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'(no subject)\' NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE message message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, CHANGE textmessage textmessage LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, CHANGE fromfield fromfield VARCHAR(255) DEFAULT NULL, CHANGE tofield tofield VARCHAR(255) DEFAULT NULL, CHANGE replyto replyto VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX phplist_message_statusidx ON phplist_message (status, id)');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE name name VARCHAR(100) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE title title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE name name VARCHAR(100) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE phplist_template CHANGE title title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_attribute CHANGE name name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE name name VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX email_2 ON phplist_user_blacklist_data (email)');
        $this->addSql('CREATE INDEX message_lookup ON phplist_user_message_bounce (message)');
        $this->addSql('ALTER TABLE phplist_user_user CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE uniqid uniqid VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX emailidx ON phplist_user_user (email)');
        $this->addSql('CREATE INDEX userattid ON phplist_user_user_attribute (attributeid, userid)');
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * Second pass of aligning the phpList 3 MySQL schema with the phpList 4 mapping (NOT NULL columns,
 * unique login names, removal of redundant legacy indexes).
 */
final class Version20260827065637 extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Tighten phpList 3 columns to the mapping and drop redundant legacy indexes.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        // Index names below only exist on some phpList 3 databases (they come from older phpList versions),
        // so every drop is conditional and every index this migration owns is checked before it is created.

        // admin: legacy installs can still carry the original `loginname` unique index next to loginnameidx
        $this->dropIndexIfExists($schema, 'phplist_admin', 'loginname');
        $this->addSql('DELETE FROM phplist_admin WHERE loginname IS NULL');
        $this->addSql("UPDATE phplist_admin SET email = '' WHERE email IS NULL");
        $this->addSql('ALTER TABLE phplist_admin CHANGE loginname loginname VARCHAR(66) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE modifiedby modifiedby VARCHAR(66) DEFAULT NULL');
        $this->ensureIndex($schema, 'phplist_admin', 'phplist_admin_loginnameidx', ['loginname'], true);

        // adminattribute: remove nameless attributes together with their values (admin_attribute has a FK now)
        $this->addSql('DELETE t FROM phplist_admin_attribute t INNER JOIN phplist_adminattribute p ON t.adminattributeid = p.id WHERE p.name IS NULL');
        $this->addSql('DELETE FROM phplist_adminattribute WHERE name IS NULL');
        $this->addSql('ALTER TABLE phplist_adminattribute CHANGE name name VARCHAR(255) NOT NULL');
        // MariaDB < 10.2 and MySQL 5.6 refuse to change the nullability of a column used by a foreign key
        // (error 1832), so the key added by Version20251028092902MySqlUpdate is dropped and re-added around it
        $this->addSql('DELETE FROM phplist_admintoken WHERE adminid IS NULL');
        $adminTokenTable = $this->getPrefixedTableName('phplist_admintoken');
        $hasAdminTokenForeignKey = $schema->hasTable($adminTokenTable)
            && $schema->getTable($adminTokenTable)->hasForeignKey('FK_CB15D477B8ED4D93');
        if ($hasAdminTokenForeignKey) {
            $this->addSql('ALTER TABLE phplist_admintoken DROP FOREIGN KEY FK_CB15D477B8ED4D93');
        }
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT NOT NULL');
        if ($hasAdminTokenForeignKey) {
            $this->addSql('ALTER TABLE phplist_admintoken ADD CONSTRAINT FK_CB15D477B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) ON DELETE CASCADE');
        }
        $this->addSql('ALTER TABLE phplist_config CHANGE item item VARCHAR(35) NOT NULL');

        // linktrack: `messageid` is the unnamed unique (messageid, userid, url) index of old installs
        $this->dropIndexIfExists($schema, 'phplist_linktrack', 'messageid');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE forward forward VARCHAR(255) DEFAULT NULL');
        $this->ensureIndex($schema, 'phplist_linktrack', 'phplist_linktrack_latestclickindex', ['latestclick']);
        $this->ensureIndex($schema, 'phplist_linktrack', 'phplist_linktrack_miduidurlindex', ['messageid', 'userid', 'url'], true);

        $this->addSql("UPDATE phplist_list SET name = '' WHERE name IS NULL");
        $this->addSql('ALTER TABLE phplist_list CHANGE name name VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE category category VARCHAR(255) DEFAULT NULL');

        // message: phplist_message_statusidx (added by Version20260820120000) is kept, it is mapped on Message
        $this->addSql('UPDATE phplist_message SET subject = COALESCE(subject, \'(no subject)\'), fromfield = COALESCE(fromfield, \'\'), tofield = COALESCE(tofield, \'\'), replyto = COALESCE(replyto, \'\'), processed = COALESCE(processed, 0), astext = COALESCE(astext, 0), ashtml = COALESCE(ashtml, 0), astextandhtml = COALESCE(astextandhtml, 0), aspdf = COALESCE(aspdf, 0), astextandpdf = COALESCE(astextandpdf, 0)');
        $this->addSql('ALTER TABLE phplist_message CHANGE subject subject VARCHAR(255) DEFAULT \'(no subject)\' NOT NULL, CHANGE fromfield fromfield VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE tofield tofield VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE replyto replyto VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE message message LONGTEXT DEFAULT NULL, CHANGE textmessage textmessage LONGTEXT DEFAULT NULL, CHANGE processed processed INT UNSIGNED DEFAULT 0 NOT NULL, CHANGE astext astext INT NOT NULL, CHANGE ashtml ashtml INT NOT NULL, CHANGE astextandhtml astextandhtml INT NOT NULL, CHANGE aspdf aspdf INT NOT NULL, CHANGE astextandpdf astextandpdf INT NOT NULL');
        $this->ensureIndex($schema, 'phplist_message', 'phplist_message_sentidx', ['sent']);

        $this->addSql('ALTER TABLE phplist_messagedata CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql("UPDATE phplist_subscribepage SET title = '' WHERE title IS NULL");
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql("UPDATE phplist_template SET title = '' WHERE title IS NULL");
        $this->addSql('ALTER TABLE phplist_template CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql("UPDATE phplist_user_attribute SET name = '' WHERE name IS NULL");
        $this->addSql('ALTER TABLE phplist_user_attribute CHANGE name name VARCHAR(255) NOT NULL');

        // user_blacklist_data: email_2 is a duplicate unique email index of old installs; old installs also
        // had a longer, nullable name column
        $this->dropIndexIfExists($schema, 'phplist_user_blacklist_data', 'email_2');
        $this->addSql("UPDATE phplist_user_blacklist_data SET name = LEFT(COALESCE(name, ''), 25) WHERE name IS NULL OR CHAR_LENGTH(name) > 25");
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE name name VARCHAR(25) NOT NULL');

        $this->dropIndexIfExists($schema, 'phplist_user_message_bounce', 'message_lookup');

        // user_user: emailidx duplicates the unique email index; subscribers without uniqid get one
        // (same format as phpList 3's getUniqid(): 32 hex characters)
        $this->dropIndexIfExists($schema, 'phplist_user_user', 'emailidx');
        $this->addSql("UPDATE phplist_user_user SET uniqid = MD5(CONCAT(id, '-', UUID())) WHERE uniqid IS NULL OR uniqid = ''");
        $this->addSql('ALTER TABLE phplist_user_user CHANGE email email VARCHAR(255) NOT NULL, CHANGE uniqid uniqid VARCHAR(255) NOT NULL');

        // user_user_attribute: userattid duplicates the (attributeid, userid) primary key
        $this->dropIndexIfExists($schema, 'phplist_user_user_attribute', 'userattid');
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

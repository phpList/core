<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030081120PostgreSqlPlatform extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostgreSql platform migration according to current Entity state';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(
            !$platform instanceof PostgreSqlPlatform,
            sprintf(
                'This migration is only applicable for PostgreSql. Current platform: %s',
                get_class($platform)
            )
        );
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE phplist_admin (id INT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, loginname VARCHAR(66) NOT NULL, namelc VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, modifiedby VARCHAR(66) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, passwordchanged DATE DEFAULT NULL, disabled BOOLEAN NOT NULL, superuser BOOLEAN NOT NULL, privileges TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX phplist_admin_loginnameidx ON phplist_admin (loginname)');
        $this->addSql('CREATE TABLE phplist_admin_attribute (adminattributeid INT NOT NULL, adminid INT NOT NULL, value VARCHAR(255) DEFAULT NULL, PRIMARY KEY(adminattributeid, adminid))');
        $this->addSql('CREATE INDEX IDX_58E07690D3B10C48 ON phplist_admin_attribute (adminattributeid)');
        $this->addSql('CREATE INDEX IDX_58E07690B8ED4D93 ON phplist_admin_attribute (adminid)');
        $this->addSql('CREATE TABLE phplist_admin_login (id INT NOT NULL, adminid INT NOT NULL, moment BIGINT NOT NULL, remote_ip4 VARCHAR(32) NOT NULL, remote_ip6 VARCHAR(50) NOT NULL, sessionid VARCHAR(50) NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5FCE0842B8ED4D93 ON phplist_admin_login (adminid)');
        $this->addSql('CREATE TABLE phplist_admin_password_request (id_key INT NOT NULL, admin INT DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, key_value VARCHAR(32) NOT NULL, PRIMARY KEY(id_key))');
        $this->addSql('CREATE INDEX IDX_DC146F3B880E0D76 ON phplist_admin_password_request (admin)');
        $this->addSql('CREATE TABLE phplist_adminattribute (id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(30) DEFAULT NULL, listorder INT DEFAULT NULL, default_value VARCHAR(255) DEFAULT NULL, required BOOLEAN DEFAULT NULL, tablename VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE phplist_admintoken (id INT NOT NULL, adminid INT DEFAULT NULL, entered INT NOT NULL, expires TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CB15D477B8ED4D93 ON phplist_admintoken (adminid)');
        $this->addSql('CREATE TABLE phplist_attachment (id INT NOT NULL, filename VARCHAR(255) DEFAULT NULL, remotefile VARCHAR(255) DEFAULT NULL, mimetype VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, size INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE phplist_bounce (id INT NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, header TEXT DEFAULT NULL, data BYTEA DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, comment TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_bounce_dateindex ON phplist_bounce (date)');
        $this->addSql('CREATE INDEX phplist_bounce_statusidx ON phplist_bounce (status)');
        $this->addSql('CREATE TABLE phplist_bounceregex (id INT NOT NULL, regex VARCHAR(2083) DEFAULT NULL, regexhash VARCHAR(32) DEFAULT NULL, action VARCHAR(255) DEFAULT NULL, listorder INT DEFAULT 0, admin INT DEFAULT NULL, comment TEXT DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, count INT DEFAULT 0, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX phplist_bounceregex_regex ON phplist_bounceregex (regexhash)');
        $this->addSql('CREATE TABLE phplist_bounceregex_bounce (regex INT NOT NULL, bounce INT NOT NULL, PRIMARY KEY(regex, bounce))');
        $this->addSql('CREATE TABLE phplist_config (item VARCHAR(35) NOT NULL, value TEXT DEFAULT NULL, editable BOOLEAN DEFAULT true NOT NULL, type VARCHAR(25) DEFAULT NULL, PRIMARY KEY(item))');
        $this->addSql('CREATE TABLE phplist_eventlog (id INT NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, page VARCHAR(100) DEFAULT NULL, entry TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_eventlog_enteredidx ON phplist_eventlog (entered)');
        $this->addSql('CREATE INDEX phplist_eventlog_pageidx ON phplist_eventlog (page)');
        $this->addSql('CREATE TABLE phplist_i18n (lan VARCHAR(10) NOT NULL, original VARCHAR(255) NOT NULL, translation TEXT NOT NULL, PRIMARY KEY(lan, original))');
        $this->addSql('CREATE UNIQUE INDEX phplist_i18n_lanorigunq ON phplist_i18n (lan, original)');
        $this->addSql('CREATE TABLE phplist_linktrack (linkid INT NOT NULL, messageid INT NOT NULL, userid INT NOT NULL, url VARCHAR(255) DEFAULT NULL, forward VARCHAR(255) DEFAULT NULL, firstclick TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, latestclick TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, clicked INT DEFAULT 0, PRIMARY KEY(linkid))');
        $this->addSql('CREATE INDEX phplist_linktrack_midindex ON phplist_linktrack (messageid)');
        $this->addSql('CREATE INDEX phplist_linktrack_miduidindex ON phplist_linktrack (messageid, userid)');
        $this->addSql('CREATE INDEX phplist_linktrack_uidindex ON phplist_linktrack (userid)');
        $this->addSql('CREATE INDEX phplist_linktrack_urlindex ON phplist_linktrack (url)');
        $this->addSql('CREATE UNIQUE INDEX phplist_linktrack_miduidurlindex ON phplist_linktrack (messageid, userid, url)');
        $this->addSql('CREATE TABLE phplist_linktrack_forward (id INT NOT NULL, url VARCHAR(255) DEFAULT NULL, urlhash VARCHAR(32) DEFAULT NULL, uuid VARCHAR(36) DEFAULT \'\', personalise BOOLEAN DEFAULT false, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_linktrack_forward_urlindex ON phplist_linktrack_forward (url)');
        $this->addSql('CREATE INDEX phplist_linktrack_forward_uuididx ON phplist_linktrack_forward (uuid)');
        $this->addSql('CREATE UNIQUE INDEX phplist_linktrack_forward_urlunique ON phplist_linktrack_forward (urlhash)');
        $this->addSql('CREATE TABLE phplist_linktrack_ml (messageid INT NOT NULL, forwardid INT NOT NULL, firstclick TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, latestclick TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, total INT DEFAULT 0, clicked INT DEFAULT 0, htmlclicked INT DEFAULT 0, textclicked INT DEFAULT 0, PRIMARY KEY(messageid, forwardid))');
        $this->addSql('CREATE INDEX phplist_linktrack_ml_fwdindex ON phplist_linktrack_ml (forwardid)');
        $this->addSql('CREATE INDEX phplist_linktrack_ml_midindex ON phplist_linktrack_ml (messageid)');
        $this->addSql('CREATE TABLE phplist_linktrack_uml_click (id INT NOT NULL, messageid INT NOT NULL, userid INT NOT NULL, forwardid INT DEFAULT NULL, firstclick TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, latestclick TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, clicked INT DEFAULT 0, htmlclicked INT DEFAULT 0, textclicked INT DEFAULT 0, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_linktrack_uml_click_midindex ON phplist_linktrack_uml_click (messageid)');
        $this->addSql('CREATE INDEX phplist_linktrack_uml_click_miduidindex ON phplist_linktrack_uml_click (messageid, userid)');
        $this->addSql('CREATE INDEX phplist_linktrack_uml_click_uidindex ON phplist_linktrack_uml_click (userid)');
        $this->addSql('CREATE UNIQUE INDEX phplist_linktrack_uml_click_miduidfwdid ON phplist_linktrack_uml_click (messageid, userid, forwardid)');
        $this->addSql('CREATE TABLE phplist_linktrack_userclick (linkid INT NOT NULL, userid INT NOT NULL, messageid INT NOT NULL, name VARCHAR(255) DEFAULT NULL, data TEXT DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(linkid, userid, messageid))');
        $this->addSql('CREATE INDEX phplist_linktrack_userclick_linkindex ON phplist_linktrack_userclick (linkid)');
        $this->addSql('CREATE INDEX phplist_linktrack_userclick_linkuserindex ON phplist_linktrack_userclick (linkid, userid)');
        $this->addSql('CREATE INDEX phplist_linktrack_userclick_linkusermessageindex ON phplist_linktrack_userclick (linkid, userid, messageid)');
        $this->addSql('CREATE INDEX phplist_linktrack_userclick_midindex ON phplist_linktrack_userclick (messageid)');
        $this->addSql('CREATE INDEX phplist_linktrack_userclick_uidindex ON phplist_linktrack_userclick (userid)');
        $this->addSql('CREATE TABLE phplist_list (id INT NOT NULL, owner INT DEFAULT NULL, name VARCHAR(255) NOT NULL, rssfeed VARCHAR(255) DEFAULT NULL, description VARCHAR(255) NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, listorder INT DEFAULT NULL, prefix VARCHAR(10) DEFAULT NULL, active BOOLEAN NOT NULL, category VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A4CE8621CF60E67C ON phplist_list (owner)');
        $this->addSql('CREATE INDEX phplist_list_nameidx ON phplist_list (name)');
        $this->addSql('CREATE INDEX phplist_list_listorderidx ON phplist_list (listorder)');
        $this->addSql('CREATE TABLE phplist_listmessage (id INT NOT NULL, messageid INT NOT NULL, listid INT NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_83B22D7A31478478 ON phplist_listmessage (messageid)');
        $this->addSql('CREATE INDEX IDX_83B22D7A8E44C1EF ON phplist_listmessage (listid)');
        $this->addSql('CREATE INDEX phplist_listmessage_listmessageidx ON phplist_listmessage (listid, messageid)');
        $this->addSql('CREATE UNIQUE INDEX phplist_listmessage_messageid ON phplist_listmessage (messageid, listid)');
        $this->addSql('CREATE TABLE phplist_listuser (userid INT NOT NULL, listid INT NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(userid, listid))');
        $this->addSql('CREATE INDEX phplist_listuser_userenteredidx ON phplist_listuser (userid, entered)');
        $this->addSql('CREATE INDEX phplist_listuser_userlistenteredidx ON phplist_listuser (userid, entered, listid)');
        $this->addSql('CREATE INDEX phplist_listuser_useridx ON phplist_listuser (userid)');
        $this->addSql('CREATE INDEX phplist_listuser_listidx ON phplist_listuser (listid)');
        $this->addSql('CREATE TABLE phplist_message (id INT NOT NULL, owner INT DEFAULT NULL, template INT DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, uuid VARCHAR(36) DEFAULT \'\', htmlformatted BOOLEAN NOT NULL, sendformat VARCHAR(20) DEFAULT NULL, astext BOOLEAN DEFAULT false NOT NULL, ashtml BOOLEAN DEFAULT false NOT NULL, aspdf BOOLEAN DEFAULT false NOT NULL, astextandhtml BOOLEAN DEFAULT false NOT NULL, astextandpdf BOOLEAN DEFAULT false NOT NULL, repeatinterval INT DEFAULT 0, repeatuntil TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, requeueinterval INT DEFAULT 0, requeueuntil TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, embargo TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, processed BOOLEAN DEFAULT false NOT NULL, viewed INT DEFAULT 0 NOT NULL, bouncecount INT DEFAULT 0 NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sendstart TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, subject VARCHAR(255) DEFAULT \'(no subject)\' NOT NULL, message TEXT DEFAULT NULL, textmessage TEXT DEFAULT NULL, footer TEXT DEFAULT NULL, fromfield VARCHAR(255) DEFAULT \'\' NOT NULL, tofield VARCHAR(255) DEFAULT \'\' NOT NULL, replyto VARCHAR(255) DEFAULT \'\' NOT NULL, userselection TEXT DEFAULT NULL, rsstemplate VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C5D81FCDCF60E67C ON phplist_message (owner)');
        $this->addSql('CREATE INDEX IDX_C5D81FCD97601F83 ON phplist_message (template)');
        $this->addSql('CREATE INDEX phplist_message_uuididx ON phplist_message (uuid)');
        $this->addSql('CREATE TABLE phplist_message_attachment (id INT NOT NULL, messageid INT NOT NULL, attachmentid INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_message_attachment_messageattidx ON phplist_message_attachment (messageid, attachmentid)');
        $this->addSql('CREATE INDEX phplist_message_attachment_messageidx ON phplist_message_attachment (messageid)');
        $this->addSql('CREATE TABLE phplist_messagedata (name VARCHAR(100) NOT NULL, id INT NOT NULL, data TEXT  DEFAULT NULL, PRIMARY KEY(name, id))');
        $this->addSql('CREATE TABLE phplist_sendprocess (id INT NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, alive INT DEFAULT 1, ipaddress VARCHAR(50) DEFAULT NULL, page VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE phplist_subscribepage (id INT NOT NULL, owner INT DEFAULT NULL, title VARCHAR(255) NOT NULL, active BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5BAC7737CF60E67C ON phplist_subscribepage (owner)');
        $this->addSql('CREATE TABLE phplist_subscribepage_data (id INT NOT NULL, name VARCHAR(100) NOT NULL, data TEXT DEFAULT NULL, PRIMARY KEY(id, name))');
        $this->addSql('CREATE TABLE phplist_template (id INT NOT NULL, title VARCHAR(255) NOT NULL, template BYTEA DEFAULT NULL, template_text BYTEA DEFAULT NULL, listorder INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX phplist_template_title ON phplist_template (title)');
        $this->addSql('CREATE TABLE phplist_templateimage (id INT NOT NULL, template INT NOT NULL, mimetype VARCHAR(100) DEFAULT NULL, filename VARCHAR(100) DEFAULT NULL, data BYTEA DEFAULT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_templateimage_templateidx ON phplist_templateimage (template)');
        $this->addSql('CREATE TABLE phplist_urlcache (id INT NOT NULL, url VARCHAR(255) NOT NULL, lastmodified INT DEFAULT NULL, added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, content BYTEA DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_urlcache_urlindex ON phplist_urlcache (url)');
        $this->addSql('CREATE TABLE phplist_user_attribute (id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(30) DEFAULT NULL, listorder INT DEFAULT NULL, default_value VARCHAR(255) DEFAULT NULL, required BOOLEAN DEFAULT NULL, tablename VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_attribute_idnameindex ON phplist_user_attribute (id, name)');
        $this->addSql('CREATE INDEX phplist_user_attribute_nameindex ON phplist_user_attribute (name)');
        $this->addSql('CREATE TABLE phplist_user_blacklist (email VARCHAR(255) NOT NULL, added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(email))');
        $this->addSql('CREATE INDEX phplist_user_blacklist_emailidx ON phplist_user_blacklist (email)');
        $this->addSql('CREATE TABLE phplist_user_blacklist_data (email VARCHAR(255) NOT NULL, name VARCHAR(25) NOT NULL, data TEXT DEFAULT NULL, PRIMARY KEY(email))');
        $this->addSql('CREATE INDEX phplist_user_blacklist_data_emailidx ON phplist_user_blacklist_data (email)');
        $this->addSql('CREATE INDEX phplist_user_blacklist_data_emailnameidx ON phplist_user_blacklist_data (email, name)');
        $this->addSql('CREATE TABLE phplist_user_message_bounce (id INT NOT NULL, "user" INT NOT NULL, message INT NOT NULL, bounce INT NOT NULL, time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_message_bounce_bounceidx ON phplist_user_message_bounce (bounce)');
        $this->addSql('CREATE INDEX phplist_user_message_bounce_msgidx ON phplist_user_message_bounce (message)');
        $this->addSql('CREATE INDEX phplist_user_message_bounce_umbindex ON phplist_user_message_bounce ("user", message, bounce)');
        $this->addSql('CREATE INDEX phplist_user_message_bounce_useridx ON phplist_user_message_bounce ("user")');
        $this->addSql('CREATE TABLE phplist_user_message_forward (id INT NOT NULL, "user" INT NOT NULL, message INT NOT NULL, forward VARCHAR(255) DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_message_forward_messageidx ON phplist_user_message_forward (message)');
        $this->addSql('CREATE INDEX phplist_user_message_forward_useridx ON phplist_user_message_forward ("user")');
        $this->addSql('CREATE INDEX phplist_user_message_forward_usermessageidx ON phplist_user_message_forward ("user", message)');
        $this->addSql('CREATE TABLE phplist_user_message_view (id INT NOT NULL, messageid INT NOT NULL, userid INT NOT NULL, viewed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, data TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_message_view_msgidx ON phplist_user_message_view (messageid)');
        $this->addSql('CREATE INDEX phplist_user_message_view_useridx ON phplist_user_message_view (userid)');
        $this->addSql('CREATE INDEX phplist_user_message_view_usermsgidx ON phplist_user_message_view (userid, messageid)');
        $this->addSql('CREATE TABLE phplist_user_user (id INT NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, email VARCHAR(255) NOT NULL, confirmed BOOLEAN NOT NULL, blacklisted BOOLEAN NOT NULL, bouncecount INT NOT NULL, uniqid VARCHAR(255) DEFAULT NULL, htmlemail BOOLEAN NOT NULL, disabled BOOLEAN NOT NULL, extradata TEXT DEFAULT NULL, optedin BOOLEAN NOT NULL, uuid VARCHAR(36) NOT NULL, subscribepage INT DEFAULT NULL, rssfrequency VARCHAR(100) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, passwordchanged TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, foreignkey VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_user_idxuniqid ON phplist_user_user (uniqid)');
        $this->addSql('CREATE INDEX phplist_user_user_enteredindex ON phplist_user_user (entered)');
        $this->addSql('CREATE INDEX phplist_user_user_confidx ON phplist_user_user (confirmed)');
        $this->addSql('CREATE INDEX phplist_user_user_blidx ON phplist_user_user (blacklisted)');
        $this->addSql('CREATE INDEX phplist_user_user_optidx ON phplist_user_user (optedin)');
        $this->addSql('CREATE INDEX phplist_user_user_uuididx ON phplist_user_user (uuid)');
        $this->addSql('CREATE INDEX phplist_user_user_foreignkey ON phplist_user_user (foreignkey)');
        $this->addSql('CREATE UNIQUE INDEX phplist_user_user_email ON phplist_user_user (email)');
        $this->addSql('CREATE TABLE phplist_user_user_attribute (attributeid INT NOT NULL, userid INT NOT NULL, value TEXT DEFAULT NULL, PRIMARY KEY(attributeid, userid))');
        $this->addSql('CREATE INDEX phplist_user_user_attribute_attindex ON phplist_user_user_attribute (attributeid)');
        $this->addSql('CREATE INDEX phplist_user_user_attribute_attuserid ON phplist_user_user_attribute (userid, attributeid)');
        $this->addSql('CREATE INDEX phplist_user_user_attribute_userindex ON phplist_user_user_attribute (userid)');
        $this->addSql('CREATE TABLE phplist_user_user_history (id INT NOT NULL, userid INT NOT NULL, ip VARCHAR(255) DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, summary VARCHAR(255) DEFAULT NULL, detail TEXT DEFAULT NULL, systeminfo TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_user_user_history_dateidx ON phplist_user_user_history (date)');
        $this->addSql('CREATE INDEX phplist_user_user_history_userididx ON phplist_user_user_history (userid)');
        $this->addSql('CREATE TABLE phplist_usermessage (userid INT NOT NULL, messageid INT NOT NULL, entered TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, viewed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, PRIMARY KEY(userid, messageid))');
        $this->addSql('CREATE INDEX phplist_usermessage_enteredindex ON phplist_usermessage (entered)');
        $this->addSql('CREATE INDEX phplist_usermessage_messageidindex ON phplist_usermessage (messageid)');
        $this->addSql('CREATE INDEX phplist_usermessage_statusidx ON phplist_usermessage (status)');
        $this->addSql('CREATE INDEX phplist_usermessage_useridindex ON phplist_usermessage (userid)');
        $this->addSql('CREATE INDEX phplist_usermessage_viewedidx ON phplist_usermessage (viewed)');
        $this->addSql('CREATE TABLE phplist_userstats (id INT NOT NULL, unixdate INT DEFAULT NULL, item VARCHAR(255) DEFAULT NULL, listid INT DEFAULT 0, value INT DEFAULT 0, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX phplist_userstats_dateindex ON phplist_userstats (unixdate)');
        $this->addSql('CREATE INDEX phplist_userstats_itemindex ON phplist_userstats (item)');
        $this->addSql('CREATE INDEX phplist_userstats_listdateindex ON phplist_userstats (listid, unixdate)');
        $this->addSql('CREATE INDEX phplist_userstats_listindex ON phplist_userstats (listid)');
        $this->addSql('CREATE UNIQUE INDEX phplist_userstats_entry ON phplist_userstats (unixdate, item, listid)');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690D3B10C48 FOREIGN KEY (adminattributeid) REFERENCES phplist_adminattribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_admin_login ADD CONSTRAINT FK_5FCE0842B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_admin_password_request ADD CONSTRAINT FK_DC146F3B880E0D76 FOREIGN KEY (admin) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_admintoken ADD CONSTRAINT FK_CB15D477B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_list ADD CONSTRAINT FK_A4CE8621CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A31478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A8E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E411F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E4118E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCDCF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCD97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_subscribepage ADD CONSTRAINT FK_5BAC7737CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_templateimage ADD CONSTRAINT FK_30A85BA97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data ADD CONSTRAINT FK_6D67150CE7927C74 FOREIGN KEY (email) REFERENCES phplist_user_blacklist (email) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E310878C45AB5 FOREIGN KEY (attributeid) REFERENCES phplist_user_attribute (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E3108F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_user_user_history ADD CONSTRAINT FK_6DBB605CF132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F469F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F46931478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(
            !$platform instanceof PostgreSqlPlatform,
            sprintf(
                'This migration is only applicable for PostgreSql. Current platform: %s',
                get_class($platform)
            )
        );
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE phplist_admin_attribute DROP CONSTRAINT FK_58E07690D3B10C48');
        $this->addSql('ALTER TABLE phplist_admin_attribute DROP CONSTRAINT FK_58E07690B8ED4D93');
        $this->addSql('ALTER TABLE phplist_admin_login DROP CONSTRAINT FK_5FCE0842B8ED4D93');
        $this->addSql('ALTER TABLE phplist_admin_password_request DROP CONSTRAINT FK_DC146F3B880E0D76');
        $this->addSql('ALTER TABLE phplist_admintoken DROP CONSTRAINT FK_CB15D477B8ED4D93');
        $this->addSql('ALTER TABLE phplist_list DROP CONSTRAINT FK_A4CE8621CF60E67C');
        $this->addSql('ALTER TABLE phplist_listmessage DROP CONSTRAINT FK_83B22D7A31478478');
        $this->addSql('ALTER TABLE phplist_listmessage DROP CONSTRAINT FK_83B22D7A8E44C1EF');
        $this->addSql('ALTER TABLE phplist_listuser DROP CONSTRAINT FK_F467E411F132696E');
        $this->addSql('ALTER TABLE phplist_listuser DROP CONSTRAINT FK_F467E4118E44C1EF');
        $this->addSql('ALTER TABLE phplist_message DROP CONSTRAINT FK_C5D81FCDCF60E67C');
        $this->addSql('ALTER TABLE phplist_message DROP CONSTRAINT FK_C5D81FCD97601F83');
        $this->addSql('ALTER TABLE phplist_subscribepage DROP CONSTRAINT FK_5BAC7737CF60E67C');
        $this->addSql('ALTER TABLE phplist_templateimage DROP CONSTRAINT FK_30A85BA97601F83');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data DROP CONSTRAINT FK_6D67150CE7927C74');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP CONSTRAINT FK_E24E310878C45AB5');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP CONSTRAINT FK_E24E3108F132696E');
        $this->addSql('ALTER TABLE phplist_user_user_history DROP CONSTRAINT FK_6DBB605CF132696E');
        $this->addSql('ALTER TABLE phplist_usermessage DROP CONSTRAINT FK_7F30F469F132696E');
        $this->addSql('ALTER TABLE phplist_usermessage DROP CONSTRAINT FK_7F30F46931478478');
        $this->addSql('DROP TABLE phplist_admin');
        $this->addSql('DROP TABLE phplist_admin_attribute');
        $this->addSql('DROP TABLE phplist_admin_login');
        $this->addSql('DROP TABLE phplist_admin_password_request');
        $this->addSql('DROP TABLE phplist_adminattribute');
        $this->addSql('DROP TABLE phplist_admintoken');
        $this->addSql('DROP TABLE phplist_attachment');
        $this->addSql('DROP TABLE phplist_bounce');
        $this->addSql('DROP TABLE phplist_bounceregex');
        $this->addSql('DROP TABLE phplist_bounceregex_bounce');
        $this->addSql('DROP TABLE phplist_config');
        $this->addSql('DROP TABLE phplist_eventlog');
        $this->addSql('DROP TABLE phplist_i18n');
        $this->addSql('DROP TABLE phplist_linktrack');
        $this->addSql('DROP TABLE phplist_linktrack_forward');
        $this->addSql('DROP TABLE phplist_linktrack_ml');
        $this->addSql('DROP TABLE phplist_linktrack_uml_click');
        $this->addSql('DROP TABLE phplist_linktrack_userclick');
        $this->addSql('DROP TABLE phplist_list');
        $this->addSql('DROP TABLE phplist_listmessage');
        $this->addSql('DROP TABLE phplist_listuser');
        $this->addSql('DROP TABLE phplist_message');
        $this->addSql('DROP TABLE phplist_message_attachment');
        $this->addSql('DROP TABLE phplist_messagedata');
        $this->addSql('DROP TABLE phplist_sendprocess');
        $this->addSql('DROP TABLE phplist_subscribepage');
        $this->addSql('DROP TABLE phplist_subscribepage_data');
        $this->addSql('DROP TABLE phplist_template');
        $this->addSql('DROP TABLE phplist_templateimage');
        $this->addSql('DROP TABLE phplist_urlcache');
        $this->addSql('DROP TABLE phplist_user_attribute');
        $this->addSql('DROP TABLE phplist_user_blacklist');
        $this->addSql('DROP TABLE phplist_user_blacklist_data');
        $this->addSql('DROP TABLE phplist_user_message_bounce');
        $this->addSql('DROP TABLE phplist_user_message_forward');
        $this->addSql('DROP TABLE phplist_user_message_view');
        $this->addSql('DROP TABLE phplist_user_user');
        $this->addSql('DROP TABLE phplist_user_user_attribute');
        $this->addSql('DROP TABLE phplist_user_user_history');
        $this->addSql('DROP TABLE phplist_usermessage');
        $this->addSql('DROP TABLE phplist_userstats');
    }
}

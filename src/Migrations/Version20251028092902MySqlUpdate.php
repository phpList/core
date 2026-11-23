<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;

/**
 * Brings the phpList3 MySQL schema (fresh from initial_schema.sql, or an existing phpList 3 database)
 * in line with the phpList 4 mapping: column types, index names, primary keys and foreign keys.
 *
 * Index names on existing phpList 3 databases depend on the version the install was created with, so all
 * index changes go through the introspecting helpers of AbstractPrefixedMigration. NULLs in legacy columns
 * that become NOT NULL are backfilled first, otherwise MySQL's strict mode rejects the ALTER.
 */
final class Version20251028092902MySqlUpdate extends AbstractPrefixedMigration
{
    public function getDescription(): string
    {
        return 'Align the phpList 3 MySQL schema with the phpList 4 mapping (types, indexes, keys).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        // admin
        $this->addSql('UPDATE phplist_admin SET created = COALESCE(created, modified, NOW()) WHERE created IS NULL');
        $this->addSql('UPDATE phplist_admin SET superuser = COALESCE(superuser, 0), disabled = COALESCE(disabled, 0) WHERE superuser IS NULL OR disabled IS NULL');
        $this->addSql('ALTER TABLE phplist_admin CHANGE created created DATETIME NOT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE superuser superuser TINYINT(1) NOT NULL, CHANGE disabled disabled TINYINT(1) NOT NULL, CHANGE privileges privileges LONGTEXT DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_admin', ['loginnameidx', 'loginname'], 'phplist_admin_loginnameidx', ['loginname'], true);

        // admin_attribute
        $this->addSql('DELETE t FROM phplist_admin_attribute t LEFT JOIN phplist_adminattribute p ON t.adminattributeid = p.id WHERE p.id IS NULL');
        $this->addSql('DELETE t FROM phplist_admin_attribute t LEFT JOIN phplist_admin p ON t.adminid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690D3B10C48 FOREIGN KEY (adminattributeid) REFERENCES phplist_adminattribute (id)');
        $this->addSql('ALTER TABLE phplist_admin_attribute ADD CONSTRAINT FK_58E07690B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_58E07690D3B10C48 ON phplist_admin_attribute (adminattributeid)');
        $this->addSql('CREATE INDEX IDX_58E07690B8ED4D93 ON phplist_admin_attribute (adminid)');

        // admin_login
        $this->addSql('UPDATE phplist_admin_login SET active = 0 WHERE active IS NULL');
        $this->addSql('ALTER TABLE phplist_admin_login CHANGE active active TINYINT(1) NOT NULL');
        $this->addSql('DELETE t FROM phplist_admin_login t LEFT JOIN phplist_admin p ON t.adminid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_admin_login ADD CONSTRAINT FK_5FCE0842B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_5FCE0842B8ED4D93 ON phplist_admin_login (adminid)');

        // admin_password_request
        $this->addSql('ALTER TABLE phplist_admin_password_request CHANGE id_key id_key INT UNSIGNED AUTO_INCREMENT NOT NULL');
        $this->addSql('UPDATE phplist_admin_password_request t LEFT JOIN phplist_admin p ON t.admin = p.id SET t.admin = NULL WHERE t.admin IS NOT NULL AND p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_admin_password_request ADD CONSTRAINT FK_DC146F3B880E0D76 FOREIGN KEY (`admin`) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_DC146F3B880E0D76 ON phplist_admin_password_request (`admin`)');

        // admintoken: a token without a value is useless
        $this->addSql('DELETE FROM phplist_admintoken WHERE value IS NULL');
        $this->addSql('ALTER TABLE phplist_admintoken CHANGE adminid adminid INT DEFAULT NULL, CHANGE value value VARCHAR(255) NOT NULL');
        $this->addSql('UPDATE phplist_admintoken t LEFT JOIN phplist_admin p ON t.adminid = p.id SET t.adminid = NULL WHERE t.adminid IS NOT NULL AND p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_admintoken ADD CONSTRAINT FK_CB15D477B8ED4D93 FOREIGN KEY (adminid) REFERENCES phplist_admin (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_CB15D477B8ED4D93 ON phplist_admintoken (adminid)');

        // attachment
        $this->addSql('ALTER TABLE phplist_attachment CHANGE description description LONGTEXT DEFAULT NULL');

        // bounce: the legacy status index is on a 20 character prefix, the new one on the whole column
        $this->dropIndexIfExists($schema, 'phplist_bounce', 'statusidx');
        $this->renameOrCreateIndex($schema, 'phplist_bounce', [], 'phplist_bounce_statusidx', ['status']);
        $this->addSql('ALTER TABLE phplist_bounce CHANGE header header LONGTEXT DEFAULT NULL, CHANGE data data LONGBLOB DEFAULT NULL, CHANGE comment comment LONGTEXT DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_bounce', ['dateindex'], 'phplist_bounce_dateindex', ['date']);

        // bounceregex
        $this->addSql('ALTER TABLE phplist_bounceregex CHANGE regexhash regexhash VARCHAR(32) DEFAULT NULL, CHANGE comment comment LONGTEXT DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_bounceregex', ['regex'], 'phplist_bounceregex_regex', ['regexhash'], true);

        // config
        $this->addSql('UPDATE phplist_config SET editable = 1 WHERE editable IS NULL');
        $this->addSql('ALTER TABLE phplist_config CHANGE editable editable TINYINT(1) DEFAULT 1 NOT NULL');

        // eventlog
        $this->addSql('ALTER TABLE phplist_eventlog CHANGE entry entry LONGTEXT DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_eventlog', ['enteredidx'], 'phplist_eventlog_enteredidx', ['entered']);
        $this->renameOrCreateIndex($schema, 'phplist_eventlog', ['pageidx'], 'phplist_eventlog_pageidx', ['page']);

        // linktrack (legacy click tracking, rows without a click get their first click or the epoch)
        $this->addSql("UPDATE phplist_linktrack SET latestclick = COALESCE(firstclick, '1970-01-01 00:00:01') WHERE latestclick IS NULL");
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE latestclick latestclick DATETIME NOT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_linktrack', ['midindex'], 'phplist_linktrack_midindex', ['messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack', ['miduidindex'], 'phplist_linktrack_miduidindex', ['messageid', 'userid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack', ['uidindex'], 'phplist_linktrack_uidindex', ['userid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack', ['urlindex'], 'phplist_linktrack_urlindex', ['url']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack', ['miduidurlindex', 'messageid'], 'phplist_linktrack_miduidurlindex', ['messageid', 'userid', 'url'], true);

        // linktrack_forward
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_forward', ['urlindex'], 'phplist_linktrack_forward_urlindex', ['url(255)']);
        $this->addSql('ALTER TABLE phplist_linktrack_forward CHANGE urlhash urlhash VARCHAR(32) DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_forward', ['uuididx'], 'phplist_linktrack_forward_uuididx', ['uuid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_forward', ['urlunique'], 'phplist_linktrack_forward_urlunique', ['urlhash'], true);

        // linktrack_ml
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_ml', ['fwdindex'], 'phplist_linktrack_ml_fwdindex', ['forwardid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_ml', ['midindex'], 'phplist_linktrack_ml_midindex', ['messageid']);

        // linktrack_uml_click
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_uml_click', ['midindex'], 'phplist_linktrack_uml_click_midindex', ['messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_uml_click', ['miduidindex'], 'phplist_linktrack_uml_click_miduidindex', ['messageid', 'userid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_uml_click', ['uidindex'], 'phplist_linktrack_uml_click_uidindex', ['userid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_uml_click', ['miduidfwdid'], 'phplist_linktrack_uml_click_miduidfwdid', ['messageid', 'userid', 'forwardid'], true);

        // linktrack_userclick has no key in phpList 3; duplicate (linkid, userid, messageid) rows are
        // reduced to one each so the new primary key can be added
        $this->addSql('ALTER TABLE phplist_linktrack_userclick CHANGE data data LONGTEXT DEFAULT NULL');
        if (!$this->hasPrimaryKey($schema, 'phplist_linktrack_userclick')) {
            $this->addUserClickPrimaryKey();
        }
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_userclick', ['linkindex'], 'phplist_linktrack_userclick_linkindex', ['linkid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_userclick', ['linkuserindex'], 'phplist_linktrack_userclick_linkuserindex', ['linkid', 'userid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_userclick', ['linkusermessageindex'], 'phplist_linktrack_userclick_linkusermessageindex', ['linkid', 'userid', 'messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_userclick', ['midindex'], 'phplist_linktrack_userclick_midindex', ['messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_linktrack_userclick', ['uidindex'], 'phplist_linktrack_userclick_uidindex', ['userid']);

        // list
        $this->addSql("UPDATE phplist_list SET description = COALESCE(description, ''), active = COALESCE(active, 0), category = COALESCE(category, '') WHERE description IS NULL OR active IS NULL OR category IS NULL");
        $this->addSql('ALTER TABLE phplist_list CHANGE description description VARCHAR(255) NOT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE active active TINYINT(1) NOT NULL, CHANGE category category VARCHAR(255) NOT NULL');
        $this->addSql('UPDATE phplist_list t LEFT JOIN phplist_admin p ON t.owner = p.id SET t.owner = NULL WHERE t.owner IS NOT NULL AND p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_list ADD CONSTRAINT FK_A4CE8621CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_A4CE8621CF60E67C ON phplist_list (owner)');
        $this->renameOrCreateIndex($schema, 'phplist_list', ['nameidx'], 'phplist_list_nameidx', ['name']);
        $this->renameOrCreateIndex($schema, 'phplist_list', ['listorderidx'], 'phplist_list_listorderidx', ['listorder']);

        // listmessage
        $this->addSql('ALTER TABLE phplist_listmessage CHANGE modified modified DATETIME NOT NULL');
        $this->addSql('DELETE t FROM phplist_listmessage t LEFT JOIN phplist_message p ON t.messageid = p.id WHERE p.id IS NULL');
        $this->addSql('DELETE t FROM phplist_listmessage t LEFT JOIN phplist_list p ON t.listid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A31478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id)');
        $this->addSql('ALTER TABLE phplist_listmessage ADD CONSTRAINT FK_83B22D7A8E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id)');
        $this->addSql('CREATE INDEX IDX_83B22D7A31478478 ON phplist_listmessage (messageid)');
        $this->addSql('CREATE INDEX IDX_83B22D7A8E44C1EF ON phplist_listmessage (listid)');
        $this->renameOrCreateIndex($schema, 'phplist_listmessage', ['listmessageidx'], 'phplist_listmessage_listmessageidx', ['listid', 'messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_listmessage', ['messageid'], 'phplist_listmessage_messageid', ['messageid', 'listid'], true);

        // listuser: userlistenteredidx changes its column order, so it is re-created
        $this->dropIndexIfExists($schema, 'phplist_listuser', 'userlistenteredidx');
        $this->addSql('ALTER TABLE phplist_listuser CHANGE modified modified DATETIME NOT NULL');
        $this->addSql('DELETE t FROM phplist_listuser t LEFT JOIN phplist_user_user p ON t.userid = p.id WHERE p.id IS NULL');
        $this->addSql('DELETE t FROM phplist_listuser t LEFT JOIN phplist_list p ON t.listid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E411F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id)');
        $this->addSql('ALTER TABLE phplist_listuser ADD CONSTRAINT FK_F467E4118E44C1EF FOREIGN KEY (listid) REFERENCES phplist_list (id) ON DELETE CASCADE');
        $this->renameOrCreateIndex($schema, 'phplist_listuser', [], 'phplist_listuser_userlistenteredidx', ['userid', 'entered', 'listid']);
        $this->renameOrCreateIndex($schema, 'phplist_listuser', ['userenteredidx'], 'phplist_listuser_userenteredidx', ['userid', 'entered']);
        $this->renameOrCreateIndex($schema, 'phplist_listuser', ['useridx'], 'phplist_listuser_useridx', ['userid']);
        $this->renameOrCreateIndex($schema, 'phplist_listuser', ['listidx'], 'phplist_listuser_listidx', ['listid']);

        // message
        $this->addSql('UPDATE phplist_message SET htmlformatted = COALESCE(htmlformatted, 0), astext = COALESCE(astext, 0), ashtml = COALESCE(ashtml, 0), astextandhtml = COALESCE(astextandhtml, 0), aspdf = COALESCE(aspdf, 0), astextandpdf = COALESCE(astextandpdf, 0), viewed = COALESCE(viewed, 0), bouncecount = COALESCE(bouncecount, 0) WHERE htmlformatted IS NULL OR astext IS NULL OR ashtml IS NULL OR astextandhtml IS NULL OR aspdf IS NULL OR astextandpdf IS NULL OR viewed IS NULL OR bouncecount IS NULL');
        $this->addSql('ALTER TABLE phplist_message CHANGE footer footer LONGTEXT DEFAULT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE userselection userselection LONGTEXT DEFAULT NULL, CHANGE htmlformatted htmlformatted TINYINT(1) NOT NULL, CHANGE astext astext INT DEFAULT 0 NOT NULL, CHANGE ashtml ashtml INT DEFAULT 0 NOT NULL, CHANGE astextandhtml astextandhtml INT DEFAULT 0 NOT NULL, CHANGE aspdf aspdf INT DEFAULT 0 NOT NULL, CHANGE astextandpdf astextandpdf INT DEFAULT 0 NOT NULL, CHANGE viewed viewed INT DEFAULT 0 NOT NULL, CHANGE bouncecount bouncecount INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE phplist_message t LEFT JOIN phplist_admin p ON t.owner = p.id SET t.owner = NULL WHERE t.owner IS NOT NULL AND p.id IS NULL');
        $this->addSql('UPDATE phplist_message t LEFT JOIN phplist_template p ON t.template = p.id SET t.template = NULL WHERE t.template IS NOT NULL AND p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCDCF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('ALTER TABLE phplist_message ADD CONSTRAINT FK_C5D81FCD97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C5D81FCDCF60E67C ON phplist_message (owner)');
        $this->addSql('CREATE INDEX IDX_C5D81FCD97601F83 ON phplist_message (template)');
        $this->renameOrCreateIndex($schema, 'phplist_message', ['uuididx'], 'phplist_message_uuididx', ['uuid']);

        // message_attachment
        $this->renameOrCreateIndex($schema, 'phplist_message_attachment', ['messageattidx'], 'phplist_message_attachment_messageattidx', ['messageid', 'attachmentid']);
        $this->renameOrCreateIndex($schema, 'phplist_message_attachment', ['messageidx'], 'phplist_message_attachment_messageidx', ['messageid']);

        // messagedata, sendprocess
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_sendprocess CHANGE modified modified DATETIME NOT NULL');

        // subscribepage
        $this->addSql('UPDATE phplist_subscribepage SET active = 0 WHERE active IS NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE active active TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE phplist_subscribepage t LEFT JOIN phplist_admin p ON t.owner = p.id SET t.owner = NULL WHERE t.owner IS NOT NULL AND p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage ADD CONSTRAINT FK_5BAC7737CF60E67C FOREIGN KEY (owner) REFERENCES phplist_admin (id)');
        $this->addSql('CREATE INDEX IDX_5BAC7737CF60E67C ON phplist_subscribepage (owner)');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE data data LONGTEXT DEFAULT NULL');

        // template, templateimage
        $this->renameOrCreateIndex($schema, 'phplist_template', ['title'], 'phplist_template_title', ['title'], true);
        $this->addSql('ALTER TABLE phplist_templateimage CHANGE template template INT NOT NULL');
        $this->addSql('DELETE t FROM phplist_templateimage t LEFT JOIN phplist_template p ON t.template = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_templateimage ADD CONSTRAINT FK_30A85BA97601F83 FOREIGN KEY (template) REFERENCES phplist_template (id)');
        $this->renameOrCreateIndex($schema, 'phplist_templateimage', ['templateidx'], 'phplist_templateimage_templateidx', ['template']);

        // urlcache, user_attribute
        $this->renameOrCreateIndex($schema, 'phplist_urlcache', ['urlindex'], 'phplist_urlcache_urlindex', ['url(255)']);
        $this->renameOrCreateIndex($schema, 'phplist_user_attribute', ['idnameindex'], 'phplist_user_attribute_idnameindex', ['id', 'name']);
        $this->renameOrCreateIndex($schema, 'phplist_user_attribute', ['nameindex'], 'phplist_user_attribute_nameindex', ['name']);

        // user_blacklist: the unique email index becomes the primary key
        $this->dropIndexIfExists($schema, 'phplist_user_blacklist', 'email');
        if (!$this->hasPrimaryKey($schema, 'phplist_user_blacklist')) {
            $this->addSql('ALTER TABLE phplist_user_blacklist ADD PRIMARY KEY (email)');
        }
        $this->renameOrCreateIndex($schema, 'phplist_user_blacklist', ['emailidx'], 'phplist_user_blacklist_emailidx', ['email']);

        // user_blacklist_data: same, plus a foreign key to user_blacklist
        $this->dropIndexIfExists($schema, 'phplist_user_blacklist_data', 'email');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE email email VARCHAR(255) NOT NULL, CHANGE data data LONGTEXT DEFAULT NULL');
        if (!$this->hasPrimaryKey($schema, 'phplist_user_blacklist_data')) {
            $this->addSql('ALTER TABLE phplist_user_blacklist_data ADD PRIMARY KEY (email)');
        }
        $this->addSql('DELETE t FROM phplist_user_blacklist_data t LEFT JOIN phplist_user_blacklist p ON t.email = p.email WHERE p.email IS NULL');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data ADD CONSTRAINT FK_6D67150CE7927C74 FOREIGN KEY (email) REFERENCES phplist_user_blacklist (email) ON DELETE CASCADE');
        $this->renameOrCreateIndex($schema, 'phplist_user_blacklist_data', ['emailidx'], 'phplist_user_blacklist_data_emailidx', ['email']);
        $this->renameOrCreateIndex($schema, 'phplist_user_blacklist_data', ['emailnameidx'], 'phplist_user_blacklist_data_emailnameidx', ['email', 'name']);

        // user_message_bounce, user_message_forward, user_message_view
        $this->renameOrCreateIndex($schema, 'phplist_user_message_bounce', ['bounceidx'], 'phplist_user_message_bounce_bounceidx', ['bounce']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_bounce', ['msgidx'], 'phplist_user_message_bounce_msgidx', ['message']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_bounce', ['umbindex'], 'phplist_user_message_bounce_umbindex', ['`user`', 'message', 'bounce']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_bounce', ['useridx'], 'phplist_user_message_bounce_useridx', ['`user`']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_forward', ['messageidx'], 'phplist_user_message_forward_messageidx', ['message']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_forward', ['useridx'], 'phplist_user_message_forward_useridx', ['`user`']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_forward', ['usermessageidx'], 'phplist_user_message_forward_usermessageidx', ['`user`', 'message']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_view', ['msgidx'], 'phplist_user_message_view_msgidx', ['messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_view', ['useridx'], 'phplist_user_message_view_useridx', ['userid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_message_view', ['usermsgidx'], 'phplist_user_message_view_usermsgidx', ['userid', 'messageid']);

        // user_user
        $this->addSql('UPDATE phplist_user_user SET confirmed = COALESCE(confirmed, 0), blacklisted = COALESCE(blacklisted, 0), optedin = COALESCE(optedin, 0), bouncecount = COALESCE(bouncecount, 0), htmlemail = COALESCE(htmlemail, 0), disabled = COALESCE(disabled, 0) WHERE confirmed IS NULL OR blacklisted IS NULL OR optedin IS NULL OR bouncecount IS NULL OR htmlemail IS NULL OR disabled IS NULL');
        $this->addSql("UPDATE phplist_user_user SET uuid = UUID() WHERE uuid IS NULL OR uuid = ''");
        $this->addSql('ALTER TABLE phplist_user_user CHANGE confirmed confirmed TINYINT(1) NOT NULL, CHANGE blacklisted blacklisted TINYINT(1) NOT NULL, CHANGE optedin optedin TINYINT(1) NOT NULL, CHANGE bouncecount bouncecount INT NOT NULL, CHANGE modified modified DATETIME NOT NULL, CHANGE uuid uuid VARCHAR(36) NOT NULL, CHANGE htmlemail htmlemail TINYINT(1) NOT NULL, CHANGE passwordchanged passwordchanged DATETIME DEFAULT NULL, CHANGE disabled disabled TINYINT(1) NOT NULL, CHANGE extradata extradata LONGTEXT DEFAULT NULL');
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['idxuniqid', 'idx_phplist_user_user_uniqid'], 'phplist_user_user_idxuniqid', ['uniqid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['enteredindex'], 'phplist_user_user_enteredindex', ['entered']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['confidx'], 'phplist_user_user_confidx', ['confirmed']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['blidx'], 'phplist_user_user_blidx', ['blacklisted']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['optidx'], 'phplist_user_user_optidx', ['optedin']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['uuididx'], 'phplist_user_user_uuididx', ['uuid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['foreignkey'], 'phplist_user_user_foreignkey', ['foreignkey']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user', ['email'], 'phplist_user_user_email', ['email'], true);

        // user_user_attribute
        $this->addSql('ALTER TABLE phplist_user_user_attribute CHANGE value value LONGTEXT DEFAULT NULL');
        $this->addSql('DELETE t FROM phplist_user_user_attribute t LEFT JOIN phplist_user_attribute p ON t.attributeid = p.id WHERE p.id IS NULL');
        $this->addSql('DELETE t FROM phplist_user_user_attribute t LEFT JOIN phplist_user_user p ON t.userid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E310878C45AB5 FOREIGN KEY (attributeid) REFERENCES phplist_user_attribute (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_user_user_attribute ADD CONSTRAINT FK_E24E3108F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->renameOrCreateIndex($schema, 'phplist_user_user_attribute', ['attindex'], 'phplist_user_user_attribute_attindex', ['attributeid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user_attribute', ['attuserid'], 'phplist_user_user_attribute_attuserid', ['userid', 'attributeid']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user_attribute', ['userindex'], 'phplist_user_user_attribute_userindex', ['userid']);

        // user_user_history
        $this->addSql('ALTER TABLE phplist_user_user_history CHANGE detail detail LONGTEXT DEFAULT NULL, CHANGE systeminfo systeminfo LONGTEXT DEFAULT NULL');
        $this->addSql('DELETE t FROM phplist_user_user_history t LEFT JOIN phplist_user_user p ON t.userid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_user_user_history ADD CONSTRAINT FK_6DBB605CF132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->renameOrCreateIndex($schema, 'phplist_user_user_history', ['dateidx'], 'phplist_user_user_history_dateidx', ['date']);
        $this->renameOrCreateIndex($schema, 'phplist_user_user_history', ['userididx'], 'phplist_user_user_history_userididx', ['userid']);

        // usermessage
        $this->addSql('DELETE t FROM phplist_usermessage t LEFT JOIN phplist_user_user p ON t.userid = p.id WHERE p.id IS NULL');
        $this->addSql('DELETE t FROM phplist_usermessage t LEFT JOIN phplist_message p ON t.messageid = p.id WHERE p.id IS NULL');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F469F132696E FOREIGN KEY (userid) REFERENCES phplist_user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phplist_usermessage ADD CONSTRAINT FK_7F30F46931478478 FOREIGN KEY (messageid) REFERENCES phplist_message (id) ON DELETE CASCADE');
        $this->renameOrCreateIndex($schema, 'phplist_usermessage', ['enteredindex'], 'phplist_usermessage_enteredindex', ['entered']);
        $this->renameOrCreateIndex($schema, 'phplist_usermessage', ['messageidindex'], 'phplist_usermessage_messageidindex', ['messageid']);
        $this->renameOrCreateIndex($schema, 'phplist_usermessage', ['statusidx'], 'phplist_usermessage_statusidx', ['status']);
        $this->renameOrCreateIndex($schema, 'phplist_usermessage', ['useridindex'], 'phplist_usermessage_useridindex', ['userid']);
        $this->renameOrCreateIndex($schema, 'phplist_usermessage', ['viewedidx'], 'phplist_usermessage_viewedidx', ['viewed']);

        // userstats
        $this->renameOrCreateIndex($schema, 'phplist_userstats', ['dateindex'], 'phplist_userstats_dateindex', ['unixdate']);
        $this->renameOrCreateIndex($schema, 'phplist_userstats', ['itemindex'], 'phplist_userstats_itemindex', ['item']);
        $this->renameOrCreateIndex($schema, 'phplist_userstats', ['listdateindex'], 'phplist_userstats_listdateindex', ['listid', 'unixdate']);
        $this->renameOrCreateIndex($schema, 'phplist_userstats', ['listindex'], 'phplist_userstats_listindex', ['listid']);
        $this->renameOrCreateIndex($schema, 'phplist_userstats', ['entry'], 'phplist_userstats_entry', ['unixdate', 'item', 'listid'], true);
    }

    private function addUserClickPrimaryKey(): void
    {
        $duplicates = (int) $this->connection->fetchOne(sprintf(
            'SELECT COUNT(*) FROM (SELECT 1 FROM %s GROUP BY linkid, userid, messageid HAVING COUNT(*) > 1) d',
            $this->getPrefixedTableName('phplist_linktrack_userclick')
        ));
        if ($duplicates === 0) {
            $this->addSql('ALTER TABLE phplist_linktrack_userclick ADD PRIMARY KEY (linkid, userid, messageid)');

            return;
        }

        // MySQL has no row identifier to tell duplicates apart, so copy the rows into a table that already
        // has the key (INSERT IGNORE keeps the first row of each duplicate group) and swap it in
        $this->addSql('CREATE TABLE phplist_linktrack_userclick_dedup LIKE phplist_linktrack_userclick');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick_dedup ADD PRIMARY KEY (linkid, userid, messageid)');
        $this->addSql('INSERT IGNORE INTO phplist_linktrack_userclick_dedup SELECT * FROM phplist_linktrack_userclick');
        $this->addSql('DROP TABLE phplist_linktrack_userclick');
        $this->addSql('RENAME TABLE phplist_linktrack_userclick_dedup TO phplist_linktrack_userclick');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $this->skipIf(!$platform instanceof MySQLPlatform, sprintf(
            'Unsupported platform for this migration: %s',
            get_class($platform)
        ));

        $this->addSql('ALTER TABLE phplist_admin CHANGE created created DATETIME DEFAULT NULL, CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE disabled disabled TINYINT(1) DEFAULT 0, CHANGE superuser superuser TINYINT(1) DEFAULT 0, CHANGE privileges privileges TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_admin RENAME INDEX phplist_admin_loginnameidx TO loginnameidx');
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
        $this->addSql('ALTER TABLE phplist_bounce DROP INDEX phplist_bounce_statusidx, ADD INDEX statusidx (status(20))');
        $this->addSql('ALTER TABLE phplist_bounce CHANGE header header TEXT DEFAULT NULL, CHANGE data data MEDIUMBLOB DEFAULT NULL, CHANGE comment comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_bounce RENAME INDEX phplist_bounce_dateindex TO dateindex');
        $this->addSql('ALTER TABLE phplist_bounceregex CHANGE regexhash regexhash CHAR(32) DEFAULT NULL, CHANGE comment comment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_bounceregex RENAME INDEX phplist_bounceregex_regex TO regex');
        $this->addSql('ALTER TABLE phplist_config CHANGE editable editable TINYINT(1) DEFAULT 1');
        $this->addSql('ALTER TABLE phplist_eventlog CHANGE entry entry TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX phplist_eventlog_enteredidx TO enteredidx');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX phplist_eventlog_pageidx TO pageidx');
        $this->addSql('ALTER TABLE phplist_linktrack CHANGE latestclick latestclick DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_miduidurlindex TO miduidurlindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_urlindex TO urlindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_miduidindex TO miduidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_forward DROP INDEX phplist_linktrack_forward_urlindex, ADD INDEX urlindex (url(255))');
        $this->addSql('ALTER TABLE phplist_linktrack_forward CHANGE urlhash urlhash CHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX phplist_linktrack_forward_urlunique TO urlunique');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX phplist_linktrack_forward_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX phplist_linktrack_ml_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX phplist_linktrack_ml_fwdindex TO fwdindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_miduidfwdid TO miduidfwdid');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_miduidindex TO miduidindex');
        $this->addSql('DROP INDEX `primary` ON phplist_linktrack_userclick');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkindex TO linkindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkuserindex TO linkuserindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkusermessageindex TO linkusermessageindex');
        $this->addSql('ALTER TABLE phplist_list DROP FOREIGN KEY FK_A4CE8621CF60E67C');
        $this->addSql('DROP INDEX IDX_A4CE8621CF60E67C ON phplist_list');
        $this->addSql('ALTER TABLE phplist_list CHANGE description description TEXT DEFAULT NULL, CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE active active TINYINT(1) DEFAULT NULL, CHANGE category category VARCHAR(255) DEFAULT \'\'');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX phplist_list_nameidx TO nameidx');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX phplist_list_listorderidx TO listorderidx');
        $this->addSql('ALTER TABLE phplist_listmessage DROP FOREIGN KEY FK_83B22D7A31478478');
        $this->addSql('ALTER TABLE phplist_listmessage DROP FOREIGN KEY FK_83B22D7A8E44C1EF');
        $this->addSql('DROP INDEX IDX_83B22D7A31478478 ON phplist_listmessage');
        $this->addSql('DROP INDEX IDX_83B22D7A8E44C1EF ON phplist_listmessage');
        $this->addSql('ALTER TABLE phplist_listmessage CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX phplist_listmessage_messageid TO messageid');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX phplist_listmessage_listmessageidx TO listmessageidx');
        $this->addSql('ALTER TABLE phplist_listuser DROP FOREIGN KEY FK_F467E411F132696E');
        $this->addSql('ALTER TABLE phplist_listuser DROP FOREIGN KEY FK_F467E4118E44C1EF');
        $this->addSql('DROP INDEX phplist_listuser_userlistenteredidx ON phplist_listuser');
        $this->addSql('ALTER TABLE phplist_listuser CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX userlistenteredidx ON phplist_listuser (userid, listid, entered)');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_userenteredidx TO userenteredidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_useridx TO useridx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_listidx TO listidx');
        $this->addSql('ALTER TABLE phplist_message DROP FOREIGN KEY FK_C5D81FCDCF60E67C');
        $this->addSql('ALTER TABLE phplist_message DROP FOREIGN KEY FK_C5D81FCD97601F83');
        $this->addSql('DROP INDEX IDX_C5D81FCDCF60E67C ON phplist_message');
        $this->addSql('DROP INDEX IDX_C5D81FCD97601F83 ON phplist_message');
        $this->addSql('ALTER TABLE phplist_message CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE htmlformatted htmlformatted TINYINT(1) DEFAULT 0, CHANGE astext astext INT DEFAULT 0, CHANGE ashtml ashtml INT DEFAULT 0, CHANGE aspdf aspdf INT DEFAULT 0, CHANGE astextandhtml astextandhtml INT DEFAULT 0, CHANGE astextandpdf astextandpdf INT DEFAULT 0, CHANGE viewed viewed INT DEFAULT 0, CHANGE bouncecount bouncecount INT DEFAULT 0, CHANGE footer footer TEXT DEFAULT NULL, CHANGE userselection userselection TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_message RENAME INDEX phplist_message_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX phplist_message_attachment_messageidx TO messageidx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX phplist_message_attachment_messageattidx TO messageattidx');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_sendprocess CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE phplist_subscribepage DROP FOREIGN KEY FK_5BAC7737CF60E67C');
        $this->addSql('DROP INDEX IDX_5BAC7737CF60E67C ON phplist_subscribepage');
        $this->addSql('ALTER TABLE phplist_subscribepage CHANGE active active TINYINT(1) DEFAULT 0');
        $this->addSql('ALTER TABLE phplist_subscribepage_data CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_template RENAME INDEX phplist_template_title TO title');
        $this->addSql('ALTER TABLE phplist_templateimage DROP FOREIGN KEY FK_30A85BA97601F83');
        $this->addSql('ALTER TABLE phplist_templateimage CHANGE template template INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE phplist_templateimage RENAME INDEX phplist_templateimage_templateidx TO templateidx');
        $this->addSql('ALTER TABLE phplist_urlcache DROP INDEX phplist_urlcache_urlindex, ADD INDEX urlindex (url(255))');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX phplist_user_attribute_nameindex TO nameindex');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX phplist_user_attribute_idnameindex TO idnameindex');
        $this->addSql('ALTER TABLE phplist_user_blacklist DROP INDEX `primary`, ADD UNIQUE INDEX email (email)');
        $this->addSql('ALTER TABLE phplist_user_blacklist RENAME INDEX phplist_user_blacklist_emailidx TO emailidx');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data DROP INDEX `primary`, ADD UNIQUE INDEX email (email)');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data DROP FOREIGN KEY FK_6D67150CE7927C74');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data CHANGE email email VARCHAR(150) NOT NULL, CHANGE data data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data RENAME INDEX phplist_user_blacklist_data_emailidx TO emailidx');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data RENAME INDEX phplist_user_blacklist_data_emailnameidx TO emailnameidx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX phplist_user_message_bounce_umbindex TO umbindex');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX phplist_user_message_bounce_useridx TO useridx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX phplist_user_message_bounce_msgidx TO msgidx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX phplist_user_message_bounce_bounceidx TO bounceidx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX phplist_user_message_forward_usermessageidx TO usermessageidx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX phplist_user_message_forward_useridx TO useridx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX phplist_user_message_forward_messageidx TO messageidx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX phplist_user_message_view_usermsgidx TO usermsgidx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX phplist_user_message_view_msgidx TO msgidx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX phplist_user_message_view_useridx TO useridx');
        $this->addSql('ALTER TABLE phplist_user_user CHANGE modified modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE confirmed confirmed TINYINT(1) DEFAULT 0, CHANGE blacklisted blacklisted TINYINT(1) DEFAULT 0, CHANGE bouncecount bouncecount INT DEFAULT 0, CHANGE htmlemail htmlemail TINYINT(1) DEFAULT 0, CHANGE disabled disabled TINYINT(1) DEFAULT 0, CHANGE extradata extradata TEXT DEFAULT NULL, CHANGE optedin optedin TINYINT(1) DEFAULT 0, CHANGE uuid uuid VARCHAR(36) DEFAULT \'\', CHANGE passwordchanged passwordchanged DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_email TO email');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_foreignkey TO foreignkey');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_idxuniqid TO idxuniqid');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_enteredindex TO enteredindex');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_confidx TO confidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_blidx TO blidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_optidx TO optidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP FOREIGN KEY FK_E24E310878C45AB5');
        $this->addSql('ALTER TABLE phplist_user_user_attribute DROP FOREIGN KEY FK_E24E3108F132696E');
        $this->addSql('ALTER TABLE phplist_user_user_attribute CHANGE value value TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_userindex TO userindex');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_attindex TO attindex');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_attuserid TO attuserid');
        $this->addSql('ALTER TABLE phplist_user_user_history DROP FOREIGN KEY FK_6DBB605CF132696E');
        $this->addSql('ALTER TABLE phplist_user_user_history CHANGE detail detail TEXT DEFAULT NULL, CHANGE systeminfo systeminfo TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX phplist_user_user_history_userididx TO userididx');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX phplist_user_user_history_dateidx TO dateidx');
        $this->addSql('ALTER TABLE phplist_usermessage DROP FOREIGN KEY FK_7F30F469F132696E');
        $this->addSql('ALTER TABLE phplist_usermessage DROP FOREIGN KEY FK_7F30F46931478478');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX phplist_usermessage_messageidindex TO messageidindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX phplist_usermessage_useridindex TO useridindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX phplist_usermessage_enteredindex TO enteredindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX phplist_usermessage_statusidx TO statusidx');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX phplist_usermessage_viewedidx TO viewedidx');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX phplist_userstats_entry TO entry');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX phplist_userstats_dateindex TO dateindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX phplist_userstats_itemindex TO itemindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX phplist_userstats_listindex TO listindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX phplist_userstats_listdateindex TO listdateindex');
    }
}

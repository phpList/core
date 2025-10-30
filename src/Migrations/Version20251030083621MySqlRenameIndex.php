<?php

declare(strict_types=1);

namespace PhpList\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030083621MySqlRenameIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename indexes as postgresql does not support duplicate index names';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE phplist_admin RENAME INDEX loginnameidx TO phplist_admin_loginnameidx');
        $this->addSql('ALTER TABLE phplist_bounce RENAME INDEX dateindex TO phplist_bounce_dateindex');
        $this->addSql('ALTER TABLE phplist_bounce RENAME INDEX statusidx TO phplist_bounce_statusidx');
        $this->addSql('ALTER TABLE phplist_bounceregex RENAME INDEX regex TO phplist_bounceregex_regex');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX enteredidx TO phplist_eventlog_enteredidx');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX pageidx TO phplist_eventlog_pageidx');
        $this->addSql('ALTER TABLE phplist_i18n RENAME INDEX lanorigunq TO phplist_i18n_lanorigunq');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX midindex TO phplist_linktrack_midindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX miduidindex TO phplist_linktrack_miduidindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX uidindex TO phplist_linktrack_uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX urlindex TO phplist_linktrack_urlindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX miduidurlindex TO phplist_linktrack_miduidurlindex');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX urlindex TO phplist_linktrack_forward_urlindex');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX uuididx TO phplist_linktrack_forward_uuididx');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX urlunique TO phplist_linktrack_forward_urlunique');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX fwdindex TO phplist_linktrack_ml_fwdindex');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX midindex TO phplist_linktrack_ml_midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX midindex TO phplist_linktrack_uml_click_midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX miduidindex TO phplist_linktrack_uml_click_miduidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX uidindex TO phplist_linktrack_uml_click_uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX miduidfwdid TO phplist_linktrack_uml_click_miduidfwdid');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX linkindex TO phplist_linktrack_userclick_linkindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX linkuserindex TO phplist_linktrack_userclick_linkuserindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX linkusermessageindex TO phplist_linktrack_userclick_linkusermessageindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX midindex TO phplist_linktrack_userclick_midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX uidindex TO phplist_linktrack_userclick_uidindex');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX nameidx TO phplist_list_nameidx');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX listorderidx TO phplist_list_listorderidx');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX listmessageidx TO phplist_listmessage_listmessageidx');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX messageid TO phplist_listmessage_messageid');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX userenteredidx TO phplist_listuser_userenteredidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX userlistenteredidx TO phplist_listuser_userlistenteredidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX useridx TO phplist_listuser_useridx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX listidx TO phplist_listuser_listidx');
        $this->addSql('ALTER TABLE phplist_message RENAME INDEX uuididx TO phplist_message_uuididx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX messageattidx TO phplist_message_attachment_messageattidx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX messageidx TO phplist_message_attachment_messageidx');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL');
        $this->addSql('ALTER TABLE phplist_template RENAME INDEX title TO phplist_template_title');
        $this->addSql('ALTER TABLE phplist_templateimage RENAME INDEX templateidx TO phplist_templateimage_templateidx');
        $this->addSql('ALTER TABLE phplist_urlcache RENAME INDEX urlindex TO phplist_urlcache_urlindex');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX idnameindex TO phplist_user_attribute_idnameindex');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX nameindex TO phplist_user_attribute_nameindex');
        $this->addSql('ALTER TABLE phplist_user_blacklist RENAME INDEX emailidx TO phplist_user_blacklist_emailidx');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data RENAME INDEX emailidx TO phplist_user_blacklist_data_emailidx');
        $this->addSql('ALTER TABLE phplist_user_blacklist_data RENAME INDEX emailnameidx TO phplist_user_blacklist_data_emailnameidx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX bounceidx TO phplist_user_message_bounce_bounceidx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX msgidx TO phplist_user_message_bounce_msgidx');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX umbindex TO phplist_user_message_bounce_umbindex');
        $this->addSql('ALTER TABLE phplist_user_message_bounce RENAME INDEX useridx TO phplist_user_message_bounce_useridx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX messageidx TO phplist_user_message_forward_messageidx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX useridx TO phplist_user_message_forward_useridx');
        $this->addSql('ALTER TABLE phplist_user_message_forward RENAME INDEX usermessageidx TO phplist_user_message_forward_usermessageidx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX msgidx TO phplist_user_message_view_msgidx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX useridx TO phplist_user_message_view_useridx');
        $this->addSql('ALTER TABLE phplist_user_message_view RENAME INDEX usermsgidx TO phplist_user_message_view_usermsgidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX idxuniqid TO phplist_user_user_idxuniqid');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX enteredindex TO phplist_user_user_enteredindex');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX confidx TO phplist_user_user_confidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX blidx TO phplist_user_user_blidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX optidx TO phplist_user_user_optidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX uuididx TO phplist_user_user_uuididx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX foreignkey TO phplist_user_user_foreignkey');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX email TO phplist_user_user_email');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX attindex TO phplist_user_user_attribute_attindex');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX attuserid TO phplist_user_user_attribute_attuserid');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX userindex TO phplist_user_user_attribute_userindex');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX dateidx TO phplist_user_user_history_dateidx');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX userididx TO phplist_user_user_history_userididx');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX enteredindex TO phplist_usermessage_enteredindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX messageidindex TO phplist_usermessage_messageidindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX statusidx TO phplist_usermessage_statusidx');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX useridindex TO phplist_usermessage_useridindex');
        $this->addSql('ALTER TABLE phplist_usermessage RENAME INDEX viewedidx TO phplist_usermessage_viewedidx');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX dateindex TO phplist_userstats_dateindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX itemindex TO phplist_userstats_itemindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX listdateindex TO phplist_userstats_listdateindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX listindex TO phplist_userstats_listindex');
        $this->addSql('ALTER TABLE phplist_userstats RENAME INDEX entry TO phplist_userstats_entry');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE phplist_admin RENAME INDEX phplist_admin_loginnameidx TO loginnameidx');
        $this->addSql('ALTER TABLE phplist_bounce RENAME INDEX phplist_bounce_dateindex TO dateindex');
        $this->addSql('ALTER TABLE phplist_bounce RENAME INDEX phplist_bounce_statusidx TO statusidx');
        $this->addSql('ALTER TABLE phplist_bounceregex RENAME INDEX phplist_bounceregex_regex TO regex');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX phplist_eventlog_enteredidx TO enteredidx');
        $this->addSql('ALTER TABLE phplist_eventlog RENAME INDEX phplist_eventlog_pageidx TO pageidx');
        $this->addSql('ALTER TABLE phplist_i18n RENAME INDEX phplist_i18n_lanorigunq TO lanorigunq');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_miduidurlindex TO miduidurlindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_urlindex TO urlindex');
        $this->addSql('ALTER TABLE phplist_linktrack RENAME INDEX phplist_linktrack_miduidindex TO miduidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX phplist_linktrack_forward_urlunique TO urlunique');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX phplist_linktrack_forward_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_linktrack_forward RENAME INDEX phplist_linktrack_forward_urlindex TO urlindex');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX phplist_linktrack_ml_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_ml RENAME INDEX phplist_linktrack_ml_fwdindex TO fwdindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_miduidfwdid TO miduidfwdid');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_uml_click RENAME INDEX phplist_linktrack_uml_click_miduidindex TO miduidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkindex TO linkindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_uidindex TO uidindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_midindex TO midindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkuserindex TO linkuserindex');
        $this->addSql('ALTER TABLE phplist_linktrack_userclick RENAME INDEX phplist_linktrack_userclick_linkusermessageindex TO linkusermessageindex');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX phplist_list_nameidx TO nameidx');
        $this->addSql('ALTER TABLE phplist_list RENAME INDEX phplist_list_listorderidx TO listorderidx');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX phplist_listmessage_messageid TO messageid');
        $this->addSql('ALTER TABLE phplist_listmessage RENAME INDEX phplist_listmessage_listmessageidx TO listmessageidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_userenteredidx TO userenteredidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_useridx TO useridx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_listidx TO listidx');
        $this->addSql('ALTER TABLE phplist_listuser RENAME INDEX phplist_listuser_userlistenteredidx TO userlistenteredidx');
        $this->addSql('ALTER TABLE phplist_message RENAME INDEX phplist_message_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX phplist_message_attachment_messageidx TO messageidx');
        $this->addSql('ALTER TABLE phplist_message_attachment RENAME INDEX phplist_message_attachment_messageattidx TO messageattidx');
        $this->addSql('ALTER TABLE phplist_messagedata CHANGE data data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE phplist_template RENAME INDEX phplist_template_title TO title');
        $this->addSql('ALTER TABLE phplist_templateimage RENAME INDEX phplist_templateimage_templateidx TO templateidx');
        $this->addSql('ALTER TABLE phplist_urlcache RENAME INDEX phplist_urlcache_urlindex TO urlindex');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX phplist_user_attribute_nameindex TO nameindex');
        $this->addSql('ALTER TABLE phplist_user_attribute RENAME INDEX phplist_user_attribute_idnameindex TO idnameindex');
        $this->addSql('ALTER TABLE phplist_user_blacklist RENAME INDEX phplist_user_blacklist_emailidx TO emailidx');
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
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_email TO email');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_foreignkey TO foreignkey');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_idxuniqid TO idxuniqid');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_enteredindex TO enteredindex');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_confidx TO confidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_blidx TO blidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_optidx TO optidx');
        $this->addSql('ALTER TABLE phplist_user_user RENAME INDEX phplist_user_user_uuididx TO uuididx');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_userindex TO userindex');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_attindex TO attindex');
        $this->addSql('ALTER TABLE phplist_user_user_attribute RENAME INDEX phplist_user_user_attribute_attuserid TO attuserid');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX phplist_user_user_history_userididx TO userididx');
        $this->addSql('ALTER TABLE phplist_user_user_history RENAME INDEX phplist_user_user_history_dateidx TO dateidx');
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

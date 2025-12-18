<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

enum ConfigOption: string
{
    case MaintenanceMode = 'maintenancemode';
    case SubscribeMessage = 'subscribemessage';
    case SubscribeEmailSubject = 'subscribesubject';
    case UnsubscribeUrl = 'unsubscribeurl';
    case ConfirmationUrl = 'confirmationurl';
    case PreferencesUrl = 'preferencesurl';
    case SubscribeUrl = 'subscribeurl';
    case Domain = 'domain';
    case Website = 'website';
    case MessageFromAddress = 'message_from_address';
    case MessageFromName = 'message_from_name';
    case MessageReplyToAddress = 'message_replyto_address';
    case SystemMessageTemplate = 'systemmessagetemplate';
    case AlwaysAddGoogleTracking = 'always_add_googletracking';
    case AdminAddress = 'admin_address';
    case DefaultMessageTemplate = 'defaultmessagetemplate';
    case MessageFooter = 'messagefooter';
    case ForwardFooter = 'forwardfooter';
    case NotifyStartDefault = 'notifystart_default';
    case NotifyEndDefault = 'notifyend_default';
    case WordWrap = 'wordwrap';
    case RemoteUrlAppend = 'remoteurl_append';
    case OrganisationLogo = 'organisation_logo';
    case PoweredByImage = 'PoweredByImage';
    case PoweredByText = 'PoweredByText';
    case UploadImageRoot = 'uploadimageroot';
    case PageRoot = 'pageroot';
}

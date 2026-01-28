<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

enum ConfigOption: string
{
    case MaintenanceMode = 'maintenancemode';
    case SubscribeMessage = 'subscribemessage';
    case SubscribeEmailSubject = 'subscribesubject';
    case UnsubscribeUrl = 'unsubscribeurl';
    case BlacklistUrl = 'blacklisturl';
    case ForwardUrl = 'forwardurl';
    case ConfirmationUrl = 'confirmationurl';
    case PreferencesUrl = 'preferencesurl';
    case SubscribeUrl = 'subscribeurl';
    // todo: check where is this defined
    case Domain = 'domain';
    case Website = 'website';
    case MessageFromAddress = 'message_from_address';
    case MessageFromName = 'message_from_name';
    case MessageReplyToAddress = 'message_replyto_address';
    case SystemMessageTemplate = 'systemmessagetemplate';
    case AlwaysAddGoogleTracking = 'always_add_googletracking';
    case AdminAddress = 'admin_address';
    case AdminAddresses = 'admin_addresses';
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
    case OrganisationName = 'organisation_name';
    case VCardUrl = 'vcardurl';
    case HtmlEmailStyle = 'html_email_style';
    case AlwaysSendTextDomains = 'alwayssendtextto';
    case ReportAddress = 'report_address';
    case SendAdminCopies = 'send_admin_copies';
}

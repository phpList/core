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
}

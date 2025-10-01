<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

enum ConfigOption: string
{
    case MaintenanceMode = 'maintenancemode';
    case SendSubscribeMessage = 'subscribemessage';
}

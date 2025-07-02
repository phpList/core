<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

enum PrivilegeFlag: string
{
    case Subscribers = 'subscribers';
    case Campaigns   = 'campaigns';
    case Statistics  = 'statistics';
    case Settings    = 'settings';
}

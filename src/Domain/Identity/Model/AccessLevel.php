<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

enum AccessLevel: string
{
    case All = 'all';
    case Owner = 'owner';
    case None = 'none';
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Model;

enum SearchOperation: string
{
    case Index = 'index';
    case Delete = 'delete';
}

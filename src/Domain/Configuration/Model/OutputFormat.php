<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

enum OutputFormat: string
{
    case Html = 'html';
    case Text = 'text';
}

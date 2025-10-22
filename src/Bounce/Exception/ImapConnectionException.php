<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Exception;

use RuntimeException;
use Throwable;

class ImapConnectionException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Cannot connect to IMAP server', 0, $previous);
    }
}

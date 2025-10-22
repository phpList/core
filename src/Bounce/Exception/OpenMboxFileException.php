<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Exception;

use RuntimeException;
use Throwable;

class OpenMboxFileException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Cannot open mbox file', 0, $previous);
    }
}

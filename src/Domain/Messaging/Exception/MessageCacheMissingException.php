<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class MessageCacheMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Message cache is missing or expired');
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class MessageNotReceivedException extends RuntimeException
{
    public function __construct() {
        parent::__construct('Cannot forward: user has not received this message');
    }
}

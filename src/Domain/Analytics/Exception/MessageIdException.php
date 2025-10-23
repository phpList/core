<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Exception;

use LogicException;

class MessageIdException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Message must have an ID');
    }
}

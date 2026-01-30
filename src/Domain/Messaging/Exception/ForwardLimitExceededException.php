<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class ForwardLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Forward limit of messages has been reached');
    }
}

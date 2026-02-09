<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class EmailBlacklistedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email address is blacklisted.');
    }
}

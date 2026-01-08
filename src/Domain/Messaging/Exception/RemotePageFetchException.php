<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class RemotePageFetchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Failed to fetch URL for subscriber');
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Exception;

use RuntimeException;

class AdminAttributeCreationException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

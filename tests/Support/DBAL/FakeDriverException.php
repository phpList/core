<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Support\DBAL;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Exception;
use Throwable;

/**
 * Lightweight test double for Doctrine DBAL driver exceptions.
 * Allows constructing DBAL higher-level exceptions (e.g., TableExistsException)
 * without relying on a real driver implementation.
 */
class FakeDriverException extends Exception implements DriverException
{
    private ?string $sqlState;

    public function __construct(
        string $message = '',
        ?string $sqlState = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sqlState = $sqlState;
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}

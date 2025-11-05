<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use LogicException;

class InvalidDtoTypeException extends LogicException
{
    public function __construct(string $type)
    {
        parent::__construct('Invalid dto type: ' . $type);
    }
}

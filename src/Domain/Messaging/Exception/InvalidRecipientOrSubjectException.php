<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class InvalidRecipientOrSubjectException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid recipient or subject.');
    }
}

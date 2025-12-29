<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use LogicException;

class DevEmailNotConfiguredException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Sending email in dev mode, but dev email is not configured.');
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

use RuntimeException;

class AttachmentException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Failed to add attachment to email message.');
    }
}

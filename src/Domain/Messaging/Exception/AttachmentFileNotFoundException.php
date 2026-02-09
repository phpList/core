<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Exception;

class AttachmentFileNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Attachment file not available');
    }
}

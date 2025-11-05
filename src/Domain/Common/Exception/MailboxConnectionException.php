<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Exception;

use RuntimeException;
use Throwable;

class MailboxConnectionException extends RuntimeException
{
    public function __construct(string $mailbox, ?string $message = null, ?Throwable $previous = null)
    {
        if ($message === null) {
            $message = sprintf(
                'Cannot open mailbox "%s": %s',
                $mailbox,
                imap_last_error() ?: 'unknown error'
            );
        }
        parent::__construct($message, 0, $previous);
    }
}

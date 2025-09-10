<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Message;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Submitted = 'submitted';
    case InProcess = 'inprocess';
    case Sent = 'sent';
    case Suspended = 'suspended';
    case Requeued = 'requeued';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Sent, self::Suspended => true,
            default => false,
        };
    }
}

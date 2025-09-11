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

    /**
     * Allowed transitions for each state
     *
     * @return MessageStatus[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft, self::Suspended => [self::Submitted],
            self::Submitted => [self::Prepared, self::InProcess],
            self::Prepared => [self::InProcess],
            self::InProcess => [self::Sent, self::Suspended],
            self::Requeued => [self::InProcess, self::Suspended],
            self::Sent => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}

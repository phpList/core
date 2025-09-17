<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Message;

enum UserMessageStatus: string
{
    case Todo = 'todo';
    case Active = 'active';
    case Sent = 'sent';
    case NotSent = 'not sent';
    case InvalidEmailAddress = 'invalid email address';
    case UnconfirmedUser = 'unconfirmed user';
    case Excluded = 'excluded';
}

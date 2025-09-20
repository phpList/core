<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

enum BounceStatus: string
{
    case UnidentifiedMessage = 'bounced unidentified message';
    case BouncedList = 'bounced list message %d';
    case DuplicateBounce = 'duplicate bounce for %d';
    case SystemMessage = 'bounced system message';
    case Unknown = 'unidentified bounce';

    public function format(int $userId): string
    {
        return sprintf($this->value, $userId);
    }
}

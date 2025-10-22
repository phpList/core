<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

class CampaignProcessorMessage
{
    private int $messageId;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }
}

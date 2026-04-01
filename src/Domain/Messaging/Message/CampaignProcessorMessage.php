<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

class CampaignProcessorMessage implements CampaignProcessorMessageInterface
{
    private int $messageId;
    private array $listIds;

    public function __construct(int $messageId, ?array $listIds = [])
    {
        $this->messageId = $messageId;
        $this->listIds = $listIds ?? [];
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getListIds(): array
    {
        return $this->listIds;
    }
}

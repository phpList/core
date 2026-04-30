<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message\CampaignProcessor;

class TestCampaignProcessorMessage implements CampaignProcessorMessageInterface
{
    private int $messageId;
    private array $listIds;
    private array $subscriberEmails;

    public function __construct(int $messageId, ?array $listIds = [], ?array $subscriberEmails = [])
    {
        $this->messageId = $messageId;
        $this->listIds = $listIds ?? [];
        $this->subscriberEmails = $subscriberEmails ?? [];
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getListIds(): array
    {
        return $this->listIds;
    }

    public function getSubscriberEmails(): array
    {
        return $this->subscriberEmails;
    }
}

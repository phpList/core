<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message\CampaignProcessor;

interface CampaignProcessorMessageInterface
{
    public function getMessageId(): int;
    public function getListIds(): array;
}

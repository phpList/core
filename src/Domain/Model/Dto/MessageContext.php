<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Dto;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\Message;

class MessageContext
{
    public function __construct(public Administrator $user, public ?Message $existing = null)
    {
    }

    public function getOwner(): Administrator
    {
        return $this->user;
    }

    public function getExisting(): ?Message
    {
        return $this->existing;
    }
}

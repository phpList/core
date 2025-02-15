<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

#[ORM\Entity]
#[ORM\Table(name: "phplist_message_attachment")]
#[ORM\Index(name: "messageattidx", columns: ["messageid", "attachmentid"])]
#[ORM\Index(name: "messageidx", columns: ["messageid"])]
class MessageAttachment implements Identity
{
    use IdentityTrait;

    #[ORM\Column(name: "messageid", type: "integer")]
    private int $messageId;

    #[ORM\Column(name: "attachmentid", type: "integer")]
    private int $attachmentId;

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getAttachmentId(): int
    {
        return $this->attachmentId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function setAttachmentId(int $attachmentId): self
    {
        $this->attachmentId = $attachmentId;
        return $this;
    }
}

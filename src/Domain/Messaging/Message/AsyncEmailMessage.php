<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

use Symfony\Component\Mime\Email;

/**
 * Message class for asynchronous email processing
 */
class AsyncEmailMessage
{
    private Email $email;
    private array $cc;
    private array $bcc;
    private array $replyTo;
    private array $attachments;

    public function __construct(
        Email $email,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $attachments = []
    ) {
        $this->email = $email;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->replyTo = $replyTo;
        $this->attachments = $attachments;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }
}

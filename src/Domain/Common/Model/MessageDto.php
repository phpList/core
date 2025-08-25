<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

use DateTimeImmutable;

class MessageDto
{
    public function __construct(
        private string $uid,
        private string $messageId,
        private string $subject,
        private string $from,
        private array $to,
        private ?string $cc = null,
        private ?string $bcc = null,
        private DateTimeImmutable $date,
        private string $bodyText = '',
        private string $bodyHtml = '',
        private array $attachments = []
    ) {}

    public function getUid(): string { return $this->uid; }
    public function getMessageId(): string { return $this->messageId; }
    public function getSubject(): string { return $this->subject; }
    public function getFrom(): string { return $this->from; }
    public function getTo(): array { return $this->to; }
    public function getCc(): ?string { return $this->cc; }
    public function getBcc(): ?string { return $this->bcc; }
    public function getDate(): DateTimeImmutable { return $this->date; }
    public function getBodyText(): string { return $this->bodyText; }
    public function getBodyHtml(): string { return $this->bodyHtml; }
    public function getAttachments(): array { return $this->attachments; }
}

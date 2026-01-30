<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

use DateTimeInterface;

class MessageForwardDto
{
    /**
     * @param string[] $emails
     */
    public function __construct(
        private readonly array $emails,
        private readonly string $uid,
        private readonly DateTimeInterface $cutoff,
        private readonly string $fromName,
        private readonly string $fromEmail,
        private readonly ?string $note = null
    ) {
    }

    /**
     * @return string[]
     */
    public function getEmails(): array
    {
        return $this->emails;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getCutoff(): DateTimeInterface
    {
        return $this->cutoff;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}

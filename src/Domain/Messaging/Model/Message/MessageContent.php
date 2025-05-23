<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Message;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageContent implements EmbeddableInterface
{
    #[ORM\Column(type: 'string', length: 255, nullable: false, options: ['default' => '(no subject)'])]
    private string $subject;

    #[ORM\Column(name:'message', type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(name: 'textmessage', type: 'text', nullable: true)]
    private ?string $textMessage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $footer = null;

    public function __construct(
        string $subject,
        ?string $text = null,
        ?string $textMessage = null,
        ?string $footer = null
    ) {
        $this->subject = $subject;
        $this->text = $text;
        $this->textMessage = $textMessage;
        $this->footer = $footer;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getTextMessage(): ?string
    {
        return $this->textMessage;
    }

    public function setTextMessage(?string $textMessage): self
    {
        $this->textMessage = $textMessage;
        return $this;
    }

    public function getFooter(): ?string
    {
        return $this->footer;
    }

    public function setFooter(?string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }
}

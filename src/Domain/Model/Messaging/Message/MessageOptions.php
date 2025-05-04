<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageOptions implements EmbeddableInterface
{
    #[ORM\Column(name: 'fromfield', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    private string $fromField;

    #[ORM\Column(name: 'tofield', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    private string $toField;

    #[ORM\Column(name: 'replyto', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    private string $replyTo;

    #[ORM\Column(name: 'userselection', type: 'text', nullable: true)]
    private ?string $userSelection;

    #[ORM\Column(name: 'rsstemplate', type: 'string', length: 100, nullable: true)]
    private ?string $rssTemplate;

    public function __construct(
        string $fromField = '',
        string $toField = '',
        string $replyTo = '',
        ?string $userSelection = null,
        ?string $rssTemplate = null
    ) {
        $this->fromField = $fromField;
        $this->toField = $toField;
        $this->replyTo = $replyTo;
        $this->userSelection = $userSelection;
        $this->rssTemplate = $rssTemplate;
    }

    public function getFromField(): string
    {
        return $this->fromField;
    }

    public function getToField(): string
    {
        return $this->toField;
    }

    public function getReplyTo(): string
    {
        return $this->replyTo;
    }

    public function getUserSelection(): ?string
    {
        return $this->userSelection;
    }

    public function getRssTemplate(): ?string
    {
        return $this->rssTemplate;
    }

    public function setFromField(string $fromField): self
    {
        $this->fromField = $fromField;
        return $this;
    }

    public function setToField(string $toField): self
    {
        $this->toField = $toField;
        return $this;
    }

    public function setReplyTo(string $replyTo): self
    {
        $this->replyTo = $replyTo;
        return $this;
    }

    public function setUserSelection(?string $userSelection): self
    {
        $this->userSelection = $userSelection;
        return $this;
    }

    public function setRssTemplate(?string $rssTemplate): self
    {
        $this->rssTemplate = $rssTemplate;
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Model;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;

#[ORM\Entity(repositoryClass: LinkTrackUmlClickRepository::class)]
#[ORM\Table(name: 'phplist_linktrack_uml_click')]
#[ORM\UniqueConstraint(name: 'miduidfwdid', columns: ['messageid', 'userid', 'forwardid'])]
#[ORM\Index(name: 'midindex', columns: ['messageid'])]
#[ORM\Index(name: 'miduidindex', columns: ['messageid', 'userid'])]
#[ORM\Index(name: 'uidindex', columns: ['userid'])]
class LinkTrackUmlClick implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'userid', type: 'integer')]
    private int $userId;

    #[ORM\Column(name: 'forwardid', type: 'integer', nullable: true)]
    private ?int $forwardId = null;

    #[ORM\Column(name: 'firstclick', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $firstClick = null;

    #[ORM\Column(name: 'latestclick', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $latestClick = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $clicked = 0;

    #[ORM\Column(name: 'htmlclicked', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $htmlClicked = 0;

    #[ORM\Column(name: 'textclicked', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $textClicked = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getForwardId(): ?int
    {
        return $this->forwardId;
    }

    public function setForwardId(?int $forwardId): self
    {
        $this->forwardId = $forwardId;
        return $this;
    }

    public function getFirstClick(): ?DateTimeInterface
    {
        return $this->firstClick;
    }

    public function setFirstClick(?DateTimeInterface $firstClick): self
    {
        $this->firstClick = $firstClick;
        return $this;
    }

    public function getLatestClick(): ?DateTimeInterface
    {
        return $this->latestClick;
    }

    public function setLatestClick(?DateTimeInterface $latestClick): self
    {
        $this->latestClick = $latestClick;
        return $this;
    }

    public function getClicked(): ?int
    {
        return $this->clicked;
    }

    public function setClicked(?int $clicked): self
    {
        $this->clicked = $clicked;
        return $this;
    }

    public function getHtmlClicked(): ?int
    {
        return $this->htmlClicked;
    }

    public function setHtmlClicked(?int $htmlClicked): self
    {
        $this->htmlClicked = $htmlClicked;
        return $this;
    }

    public function getTextClicked(): ?int
    {
        return $this->textClicked;
    }

    public function setTextClicked(?int $textClicked): self
    {
        $this->textClicked = $textClicked;
        return $this;
    }
}

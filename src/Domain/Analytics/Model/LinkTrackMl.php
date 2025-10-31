<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Model;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;

#[ORM\Entity(repositoryClass: LinkTrackMlRepository::class)]
#[ORM\Table(name: 'phplist_linktrack_ml')]
#[ORM\Index(name: 'phplist_linktrack_ml_fwdindex', columns: ['forwardid'])]
#[ORM\Index(name: 'phplist_linktrack_ml_midindex', columns: ['messageid'])]
class LinkTrackMl implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Id]
    #[ORM\Column(name: 'forwardid', type: 'integer')]
    private int $forwardId;

    #[ORM\Column(name: 'firstclick', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $firstClick = null;

    #[ORM\Column(name:'latestclick', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $latestClick = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $total = 0;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $clicked = 0;

    #[ORM\Column(name: 'htmlclicked', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $htmlClicked = 0;

    #[ORM\Column(name: 'textclicked', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $textClicked = 0;

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getForwardId(): int
    {
        return $this->forwardId;
    }

    public function setForwardId(int $forwardId): self
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

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): self
    {
        $this->total = $total;
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

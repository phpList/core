<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Analytics;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Repository\Analytics\LinkTrackRepository;

#[ORM\Entity(repositoryClass: LinkTrackRepository::class)]
#[ORM\Table(name: 'phplist_linktrack')]
#[ORM\UniqueConstraint(name: 'miduidurlindex', columns: ['messageid', 'userid', 'url'])]
#[ORM\Index(name: 'midindex', columns: ['messageid'])]
#[ORM\Index(name: 'miduidindex', columns: ['messageid', 'userid'])]
#[ORM\Index(name: 'uidindex', columns: ['userid'])]
#[ORM\Index(name: 'urlindex', columns: ['url'])]
class LinkTrack implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name:'linkid', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'messageid', type: 'integer')]
    private int $messageId;

    #[ORM\Column(name: 'userid', type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $forward = null;

    #[ORM\Column(name: 'firstclick', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $firstClick = null;

    #[ORM\Column(name: 'latestclick', type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?DateTimeInterface $latestClick = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private int $clicked = 0;

    public function getId(): int
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getForward(): ?string
    {
        return $this->forward;
    }

    public function setForward(?string $forward): self
    {
        $this->forward = $forward;
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

    public function getClicked(): int
    {
        return $this->clicked;
    }

    public function setClicked(int $clicked): self
    {
        $this->clicked = $clicked;
        return $this;
    }
}

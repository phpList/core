<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;

#[ORM\Entity(repositoryClass: LinkTrackForwardRepository::class)]
#[ORM\Table(name: 'phplist_linktrack_forward')]
#[ORM\UniqueConstraint(name: 'urlunique', columns: ['urlhash'])]
#[ORM\Index(name: 'urlindex', columns: ['url'])]
#[ORM\Index(name: 'uuididx', columns: ['uuid'])]
class LinkTrackForward implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 2083, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'urlhash', type: 'string', length: 32, nullable: true)]
    private ?string $urlHash = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true, options: ['default' => ''])]
    private ?string $uuid = '';

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    private bool $personalise = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUrlHash(): ?string
    {
        return $this->urlHash;
    }

    public function setUrlHash(?string $urlHash): self
    {
        $this->urlHash = $urlHash;
        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function isPersonalise(): bool
    {
        return $this->personalise;
    }

    public function setPersonalise(bool $personalise): self
    {
        $this->personalise = $personalise;
        return $this;
    }
}

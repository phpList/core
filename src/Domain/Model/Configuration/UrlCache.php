<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Configuration;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

#[ORM\Entity]
#[ORM\Table(name: 'phplist_urlcache')]
#[ORM\Index(name: 'urlindex', columns: ['url'])]
class UrlCache implements DomainModel, Identity
{
    use IdentityTrait;

    #[ORM\Column(name: 'url', type: 'string', length: 2083)]
    private string $url;

    #[ORM\Column(name: 'lastmodified', type: 'integer', nullable: true)]
    private ?int $lastModified = null;

    #[ORM\Column(name: 'added', type: 'datetime', nullable: true)]
    private ?DateTime $added = null;

    #[ORM\Column(name: 'content', type: 'blob', nullable: true)]
    private ?string $content = null;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function getAdded(): ?DateTime
    {
        return $this->added;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function setLastModified(?int $lastModified): self
    {
        $this->lastModified = $lastModified;
        return $this;
    }

    public function setAdded(?DateTime $added): self
    {
        $this->added = $added;
        return $this;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Configuration;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Repository\Configuration\I18nRepository;

#[ORM\Entity(repositoryClass: I18nRepository::class)]
#[ORM\Table(name: "phplist_i18n")]
#[ORM\UniqueConstraint(name: "lanorigunq", columns: ["lan", "original"])]
#[ORM\Index(name: "lanorigidx", columns: ["lan", "original"])]
class I18n implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 10)]
    private string $lan;

    #[ORM\Id]
    #[ORM\Column(type: "text")]
    private string $original;

    #[ORM\Column(type: "text")]
    private string $translation;

    public function getLan(): string
    {
        return $this->lan;
    }

    public function setLan(string $lan): self
    {
        $this->lan = $lan;
        return $this;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function setOriginal(string $original): self
    {
        $this->original = $original;
        return $this;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }

    public function setTranslation(string $translation): self
    {
        $this->translation = $translation;
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Configuration\Repository\I18nRepository;

/**
 * @deprecated
 *
 * Symfony\Contracts\Translation will be used instead.
 */
#[ORM\Entity(repositoryClass: I18nRepository::class)]
#[ORM\Table(name: 'phplist_i18n')]
#[ORM\UniqueConstraint(name: 'phplist_i18n_lanorigunq', columns: ['lan', 'original'])]
#[ORM\Index(name: 'phplist_i18n_lanorigidx', columns: ['lan', 'original'])]
class I18n implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 10)]
    private string $lan;

    // Defined as string with length due to MySQL limitation:
    // TEXT columns can't be indexed without a prefix length, which Doctrine doesn't support.
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $original;

    #[ORM\Column(type: 'text')]
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

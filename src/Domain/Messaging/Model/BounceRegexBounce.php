<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexBounceRepository;

#[ORM\Entity(repositoryClass: BounceRegexBounceRepository::class)]
#[ORM\Table(name: 'phplist_bounceregex_bounce')]
class BounceRegexBounce implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'regex', type: 'integer')]
    private int $regexId;

    #[ORM\Id]
    #[ORM\Column(name: 'bounce', type: 'integer')]
    private int $bounceId;

    public function __construct(int $regexId, int $bounceId)
    {
        $this->regexId = $regexId;
        $this->bounceId = $bounceId;
    }

    public function getRegexId(): int
    {
        return $this->regexId;
    }

    public function setRegexId(int $regexId): self
    {
        $this->regexId = $regexId;
        return $this;
    }

    public function getBounceId(): int
    {
        return $this->bounceId;
    }

    public function setBounceId(int $bounceId): self
    {
        $this->bounceId = $bounceId;
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Repository\Messaging\BounceRegexBounceRepository;

#[ORM\Entity(repositoryClass: BounceRegexBounceRepository::class)]
#[ORM\Table(name: 'phplist_bounceregex_bounce')]
class BounceRegexBounce implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $regex;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $bounce;

    public function __construct(int $regex, int $bounce)
    {
        $this->regex = $regex;
        $this->bounce = $bounce;
    }

    public function getRegex(): int
    {
        return $this->regex;
    }

    public function setRegex(int $regex): self
    {
        $this->regex = $regex;
        return $this;
    }

    public function getBounce(): int
    {
        return $this->bounce;
    }

    public function setBounce(int $bounce): self
    {
        $this->bounce = $bounce;
        return $this;
    }
}

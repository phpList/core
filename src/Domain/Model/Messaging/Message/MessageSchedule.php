<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageSchedule implements EmbeddableInterface
{
    #[ORM\Column(name: 'repeatinterval', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $repeatInterval;

    #[ORM\Column(name: 'repeatuntil', type: 'datetime', nullable: true)]
    private ?DateTime $repeatUntil;

    #[ORM\Column(name: 'requeueinterval', type: 'integer', nullable: true, options: ['default' => 0])]
    private ?int $requeueInterval;

    #[ORM\Column(name: 'requeueuntil', type: 'datetime', nullable: true)]
    private ?DateTime $requeueUntil;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $embargo;

    public function __construct(
        ?int $repeatInterval,
        ?DateTime $repeatUntil,
        ?int $requeueInterval,
        ?DateTime $requeueUntil,
        ?DateTime $embargo
    ) {
        $this->repeatInterval = $repeatInterval;
        $this->repeatUntil = $repeatUntil;
        $this->requeueInterval = $requeueInterval;
        $this->requeueUntil = $requeueUntil;
        $this->embargo = $embargo;
    }

    public function getRepeatInterval(): ?int
    {
        return $this->repeatInterval;
    }

    public function getRepeatUntil(): ?DateTime
    {
        return $this->repeatUntil;
    }

    public function getRequeueInterval(): ?int
    {
        return $this->requeueInterval;
    }

    public function getRequeueUntil(): ?DateTime
    {
        return $this->requeueUntil;
    }

    public function getEmbargo(): ?DateTime
    {
        return $this->embargo;
    }

    public function setRepeatInterval(?int $repeatInterval): self
    {
        $this->repeatInterval = $repeatInterval;
        return $this;
    }

    public function setRepeatUntil(?DateTime $repeatUntil): self
    {
        $this->repeatUntil = $repeatUntil;
        return $this;
    }

    public function setRequeueInterval(?int $requeueInterval): self
    {
        $this->requeueInterval = $requeueInterval;
        return $this;
    }

    public function setRequeueUntil(?DateTime $requeueUntil): self
    {
        $this->requeueUntil = $requeueUntil;
        return $this;
    }

    public function setEmbargo(?DateTime $embargo): self
    {
        $this->embargo = $embargo;
        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class MessageSchedule
{

    #[ORM\Column(name: "repeatinterval", type: "integer", nullable: true, options: ["default" => 0])]
    private ?int $repeatInterval;

    #[ORM\Column(name: "repeatuntil", type: "datetime", nullable: true)]
    private ?DateTime $repeatUntil;

    #[ORM\Column(name: "requeueinterval", type: "integer", nullable: true, options: ["default" => 0])]
    private ?int $requeueInterval;

    #[ORM\Column(name: "requeueuntil", type: "datetime", nullable: true)]
    private ?DateTime $requeueUntil;

    public function __construct(
        ?int $repeatInterval,
        ?DateTime $repeatUntil,
        ?int $requeueInterval,
        ?DateTime $requeueUntil
    ) {
        $this->repeatInterval = $repeatInterval;
        $this->repeatUntil = $repeatUntil;
        $this->requeueInterval = $requeueInterval;
        $this->requeueUntil = $requeueUntil;
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
}


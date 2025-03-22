<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Message;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class MessageMetadata
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $processed;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $viewed;

    #[ORM\Column(name: 'bouncecount', type: 'integer', options: ['default' => 0])]
    private int $bounceCount;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $entered;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $sent;

    public function __construct(
        ?string $status = null,
        int $processed = 0,
        int $viewed = 0,
        int $bounceCount = 0,
        ?DateTime $entered = null,
        ?DateTime $sent = null,
    ) {
        $this->status = $status;
        $this->processed = $processed;
        $this->viewed = $viewed;
        $this->bounceCount = $bounceCount;
        $this->entered = $entered ?? new DateTime();
        $this->sent = $sent;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function setProcessed(int $processed): self
    {
        $this->processed = $processed;
        return $this;
    }

    public function getViewed(): int
    {
        return $this->viewed;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function getEntered(): ?DateTime
    {
        return $this->entered;
    }

    public function getSent(): ?DateTime
    {
        return $this->sent;
    }
}

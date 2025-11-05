<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Message;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Interfaces\EmbeddableInterface;

#[ORM\Embeddable]
class MessageMetadata implements EmbeddableInterface
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'boolean', options: ['unsigned' => true, 'default' => false])]
    private bool $processed = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $viewed = 0;

    #[ORM\Column(name: 'bouncecount', type: 'integer', options: ['default' => 0])]
    private int $bounceCount;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $entered;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $sent;

    #[ORM\Column(name: 'sendstart', type: 'datetime', nullable: true)]
    private ?DateTime $sendStart;

    public function __construct(
        ?MessageStatus $status = null,
        int $bounceCount = 0,
        ?DateTime $entered = null,
        ?DateTime $sent = null,
        ?DateTime $sendStart = null,
    ) {
        $this->status = $status->value ?? null;
        $this->processed = false;
        $this->viewed = 0;
        $this->bounceCount = $bounceCount;
        $this->entered = $entered ?? new DateTime();
        $this->sent = $sent;
        $this->sendStart = $sendStart;
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function getStatus(): ?MessageStatus
    {
        return MessageStatus::from($this->status);
    }

    public function setStatus(MessageStatus $status): self
    {
        if (!$this->getStatus()->canTransitionTo($status)) {
            throw new InvalidArgumentException('Invalid status transition');
        }
        $this->status = $status->value;

        return $this;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): self
    {
        $this->processed = $processed;
        return $this;
    }

    public function setViews(int $viewed): self
    {
        $this->viewed = $viewed;
        return $this;
    }

    public function getViews(): int
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

    public function setBounceCount(int $bounceCount): self
    {
        $this->bounceCount = $bounceCount;
        return $this;
    }

    public function setEntered(?DateTime $entered): self
    {
        $this->entered = $entered;
        return $this;
    }

    public function setSent(?DateTime $sent): self
    {
        $this->sent = $sent;
        return $this;
    }

    public function getSendStart(): ?DateTime
    {
        return $this->sendStart;
    }

    public function setSendStart(?DateTime $sendStart): self
    {
        $this->sendStart = $sendStart;
        return $this;
    }
}

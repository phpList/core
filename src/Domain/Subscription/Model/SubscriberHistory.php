<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

#[ORM\Entity(repositoryClass: SubscriberHistoryRepository::class)]
#[ORM\Table(name: 'phplist_user_user_history')]
#[ORM\Index(name: 'dateidx', columns: ['date'])]
#[ORM\Index(name: 'userididx', columns: ['userid'])]
class SubscriberHistory implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(name: 'ip', type: 'string', length: 255, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'date', type: 'datetime', nullable: true)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(name: 'summary', type: 'string', length: 255, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(name: 'detail', type: 'text', nullable: true)]
    private ?string $detail = null;

    #[ORM\Column(name: 'systeminfo', type: 'text', nullable: true)]
    private ?string $systemInfo = null;

    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getSystemInfo(): ?string
    {
        return $this->systemInfo;
    }

    public function setUser(Subscriber $subscriber): self
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;
        return $this;
    }

    public function setSystemInfo(?string $systemInfo): self
    {
        $this->systemInfo = $systemInfo;
        return $this;
    }
}

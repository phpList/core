<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Identity\Repository\AdminLoginRepository;

#[ORM\Entity(repositoryClass: AdminLoginRepository::class)]
#[ORM\Table(name: 'phplist_admin_login')]
#[ORM\HasLifecycleCallbacks]
class AdminLogin implements DomainModel, Identity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Administrator::class)]
    #[ORM\JoinColumn(name: 'adminid', referencedColumnName: 'id', nullable: false)]
    private Administrator $administrator;

    #[ORM\Column(name: 'moment', type: 'bigint')]
    private int $moment;

    #[ORM\Column(name: 'remote_ip4', type: 'string', length: 32)]
    private string $remoteIp4;

    #[ORM\Column(name: 'remote_ip6', type: 'string', length: 50)]
    private string $remoteIp6;

    #[ORM\Column(name: 'sessionid', type: 'string', length: 50)]
    private string $sessionId;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false)]
    private bool $active = false;

    public function __construct(
        Administrator $administrator,
        DateTimeInterface $createdAt,
        string $remoteIp4,
        string $remoteIp6,
        string $sessionId,
    ) {
        $this->administrator = $administrator;
        $this->moment = $createdAt->getTimestamp();
        $this->remoteIp4 = $remoteIp4;
        $this->remoteIp6 = $remoteIp6;
        $this->sessionId = $sessionId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getAdministrator(): Administrator
    {
        return $this->administrator;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return (new DateTimeImmutable())->setTimestamp($this->moment);
    }

    public function getRemoteIp4(): string
    {
        return $this->remoteIp4;
    }

    public function getRemoteIp6(): string
    {
        return $this->remoteIp6;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}

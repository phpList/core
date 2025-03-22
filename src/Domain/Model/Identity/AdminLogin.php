<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Identity;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;
use PhpList\Core\Domain\Repository\Identity\AdminLoginRepository;

#[ORM\Entity(repositoryClass: AdminLoginRepository::class)]
#[ORM\Table(name: 'phplist_admin_login')]
#[ORM\HasLifecycleCallbacks]
class AdminLogin implements DomainModel, Identity
{
    use IdentityTrait;

    #[ORM\Column(name: 'adminid', type: 'integer', options: ['unsigned' => true])]
    private int $adminId;

    #[ORM\Column(name: 'moment', type: 'bigint')]
    private int $moment;

    #[ORM\Column(name: 'remote_ip4', type: 'string', length: 32)]
    private string $remoteIp4;

    #[ORM\Column(name: 'remote_ip6', type: 'string', length:50)]
    private string $remoteIp6;

    #[ORM\Column(name: 'sessionid', type: 'string', length:50)]
    private string $sessionId;

    #[ORM\Column(name: 'active', type: 'boolean')]
    private bool $active = false;

    public function __construct(
        int $adminId,
        int $moment,
        string $remoteIp4,
        string $remoteIp6,
        string $sessionId,
    ) {
        $this->adminId = $adminId;
        $this->moment = $moment;
        $this->remoteIp4 = $remoteIp4;
        $this->remoteIp6 = $remoteIp6;
        $this->sessionId = $sessionId;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function getMoment(): int
    {
        return $this->moment;
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

    public function isActive(): bool
    {
        return $this->active;
    }
}

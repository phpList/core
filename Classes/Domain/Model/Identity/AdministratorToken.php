<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Identity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Proxy\Proxy;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PhpList\PhpList4\Domain\Model\Traits\IdentityTrait;

/**
 * This class represents an API authentication token for an administrator.
 *
 * @Entity(repositoryClass="PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository")
 * @Table(name="phplist_admintoken")
 * @ExclusionPolicy("all")
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorToken implements Identity
{
    use IdentityTrait;

    /**
     * @var string
     */
    const DEFAULT_EXPIRY = '+1 hour';

    /**
     * @var \DateTime
     * @Column(type="datetime", name="expires")
     * @Expose
     */
    private $expiry = null;

    /**
     * @var string
     * @Column(name="value")
     * @Expose
     */
    private $key = '';

    /**
     * @var Administrator|Proxy
     * @ManyToOne(targetEntity="PhpList\PhpList4\Domain\Model\Identity\Administrator")
     * @JoinColumn(name="adminid")
     */
    private $administrator = null;

    /**
     * The Constructor.
     */
    public function __construct()
    {
        $this->setExpiry(new \DateTime());
    }

    /**
     * @return \DateTime
     */
    public function getExpiry(): \DateTime
    {
        return $this->expiry;
    }

    /**
     * @param \DateTime $expiry
     *
     * @return void
     */
    private function setExpiry(\DateTime $expiry)
    {
        $this->expiry = $expiry;
    }

    /**
     * Generates and sets an expiry one hour in the future.
     *
     * @return void
     */
    public function generateExpiry()
    {
        $this->setExpiry(new \DateTime(self::DEFAULT_EXPIRY));
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function setKey(string $key)
    {
        $this->key = $key;
    }

    /**
     * Generates a new, random key.
     *
     * @return void
     */
    public function generateKey()
    {
        $key = md5(random_bytes(256));
        $this->setKey($key);
    }

    /**
     * @return Administrator|Proxy|null
     */
    public function getAdministrator()
    {
        return $this->administrator;
    }

    /**
     * @param Administrator $administrator
     *
     * @return void
     */
    public function setAdministrator(Administrator $administrator)
    {
        $this->administrator = $administrator;
    }
}

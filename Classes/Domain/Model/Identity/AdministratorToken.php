<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Interfaces\Identity;
use PhpList\PhpList4\Domain\Model\Traits\IdentityTrait;

/**
 * This class represents an API authentication token for an administrator.
 *
 * @Entity @Table(name="phplist_admintoken")
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
     */
    private $expiry = null;

    /**
     * @var string
     * @Column(type="string", name="value")
     */
    private $key = '';

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
    public function setExpiry(\DateTime $expiry)
    {
        $this->expiry = $expiry;
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
     * Generates and sets an expiry one hour in the future.
     *
     * @return void
     */
    public function generateExpiry()
    {
        $this->setExpiry(new \DateTime(self::DEFAULT_EXPIRY));
    }
}

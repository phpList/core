<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Identity;

/**
 * This class represents an API authentication token for an administrator.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorToken
{
    /**
     * @var string
     */
    const DEFAULT_EXPIRY = '+1 hour';

    /**
     * @var int
     */
    private $id = 0;

    /**
     * @var \DateTime
     */
    private $expiry = null;

    /**
     * @var string
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
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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

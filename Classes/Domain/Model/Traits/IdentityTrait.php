<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Traits;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * This class provides an ID property to domain models.
 *
 * This is the default implementation of the Identity interface.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait IdentityTrait
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id = 0;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}

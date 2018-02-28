<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Traits;

use Doctrine\ORM\Mapping;
use JMS\Serializer\Annotation\Expose;

/**
 * This trait provides an ID property to domain models.
 *
 * This is the default implementation of the Identity interface.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait IdentityTrait
{
    /**
     * @var int
     * @Mapping\Id
     * @Mapping\Column(type="integer")
     * @Mapping\GeneratedValue
     * @Expose
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

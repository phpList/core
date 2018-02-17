<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Interfaces;

/**
 * This interface communicates that a domain model has an ID property.
 *
 * The IdentityTrait is the default implementation.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
interface Identity
{
    /**
     * @return int
     */
    public function getId(): int;
}

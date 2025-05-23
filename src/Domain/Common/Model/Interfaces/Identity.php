<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Interfaces;

/**
 * This interface communicates that a domain model has an ID property.
 *
 * The IdentityTrait is the default implementation.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
interface Identity
{
    public function getId(): ?int;
}

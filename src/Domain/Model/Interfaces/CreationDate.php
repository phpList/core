<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Interfaces;

use DateTime;
use Doctrine\ORM\Mapping\PrePersist;

/**
 * This interface communicates that a domain model has a creation date.
 *
 * The CreationDateTrait is the default implementation.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
interface CreationDate
{
    /**
     * @return DateTime|null
     */
    public function getCreationDate(): ?DateTime;

    /**
     * Updates the creation date to now.
     *
     * @PrePersist
     *
     * @return void
     */
    public function updateCreationDate(): void;
}

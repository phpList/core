<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Interfaces;

use DateTime;
use Doctrine\ORM\Mapping;

/**
 * This interface communicates that a domain model has a modification date.
 *
 * The ModificationDateTrait is the default implementation.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
interface ModificationDate
{
    /**
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime;

    /**
     * Updates the modification date to be now.
     *
     * @Mapping\PrePersist
     * @Mapping\PreUpdate
     *
     * @return DomainModel
     */
    public function updateUpdatedAt(): DomainModel;
}

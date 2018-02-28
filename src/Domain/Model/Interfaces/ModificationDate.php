<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Interfaces;

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
     * @return \DateTime|null
     */
    public function getModificationDate();

    /**
     * Updates the modification date to now.
     *
     * @Mapping\PrePersist
     * @Mapping\PreUpdate
     *
     * @return void
     */
    public function updateModificationDate();
}

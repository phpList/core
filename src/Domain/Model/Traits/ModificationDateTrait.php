<?php
declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Traits;

use Doctrine\ORM\Mapping;

/**
 * This trait provides an automatic modification date for models.
 *
 * This is the default implementation of the ModificationDate interface.
 *
 * Please note that this trait requires the model to have the "HasLifecycleCallbacks" annotation in the class doc block,
 * and also to have a $modificationDate property with the correct column name mapping.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait ModificationDateTrait
{
    /**
     * @return \DateTime|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param \DateTime $modificationDate
     *
     * @return void
     */
    private function setModificationDate(\DateTime $modificationDate)
    {
        $this->modificationDate = $modificationDate;
    }

    /**
     * Updates the modification date to now.
     *
     * @Mapping\PrePersist
     * @Mapping\PreUpdate
     *
     * @return void
     */
    public function updateModificationDate()
    {
        $this->setModificationDate(new \DateTime());
    }
}

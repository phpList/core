<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Model\Traits;

use Doctrine\ORM\Mapping\PrePersist;

/**
 * This trait provides an automatic creation date for models.
 *
 * This is the default implementation of the CreationDate interface.
 *
 * Please note that this trait requires the model to have the "HasLifecycleCallbacks" annotation in the class doc block,
 * and also to have a $creationDate property with the correct column name mapping.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait CreationDateTrait
{
    /**
     * @return \DateTime|null
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @param \DateTime $creationDate
     *
     * @return void
     */
    private function setCreationDate(\DateTime $creationDate)
    {
        $this->creationDate = $creationDate;
    }

    /**
     * Updates the creation date to now.
     *
     * @PrePersist
     *
     * @return void
     */
    public function updateCreationDate()
    {
        $this->setCreationDate(new \DateTime());
    }
}

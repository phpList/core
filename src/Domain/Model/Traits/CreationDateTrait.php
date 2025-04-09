<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Traits;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

/**
 * This trait provides an automatic creation date for models.
 *
 * This is the default implementation of the CreationDate interface.
 *
 * Please note that this trait requires the model to have the "HasLifecycleCallbacks" annotation in the class doc block,
 * and also to have a $creationDate property with the correct column name mapping.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
trait CreationDateTrait
{
    /**
     * @return DateTime|null
     */
    public function getCreationDate(): ?DateTime
    {
        return $this->creationDate;
    }

    /**
     * @param DateTime $creationDate
     *
     * @return DomainModel
     */
    private function setCreationDate(DateTime $creationDate): DomainModel
    {
        $this->creationDate = $creationDate;
        return $this;
    }

    #[ORM\PrePersist]
    public function updateCreationDate(): DomainModel
    {
        $this->setCreationDate(new DateTime());

        return $this;
    }
}

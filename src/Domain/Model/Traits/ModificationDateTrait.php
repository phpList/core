<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Traits;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

/**
 * This trait provides an automatic modification date for models.
 *
 * This is the default implementation of the ModificationDate interface.
 *
 * Please note that this trait requires the model to have the "HasLifecycleCallbacks" attribute in the class doc block,
 * and also to have a $modificationDate property with the correct column name mapping.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait ModificationDateTrait
{
    #[ORM\Column(name: 'modified', type: 'datetime')]
    private ?DateTime $modificationDate = null;

    public function getModificationDate(): ?DateTime
    {
        return $this->modificationDate;
    }

    private function setModificationDate(DateTime $modificationDate): DomainModel
    {
        $this->modificationDate = $modificationDate;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateModificationDate(): DomainModel
    {
        $this->setModificationDate(new DateTime());

        return $this;
    }
}

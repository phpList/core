<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository;

use Doctrine\ORM\EntityRepository;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;

/**
 * Base class for repositories.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractRepository extends EntityRepository
{
    /**
     * Persists $model and flushes the entity manager change list.
     *
     * This method allows controllers to not depend on the entity manager, but only on the repositories instead,
     * following the Law of Demeter.
     *
     * @param DomainModel $model
     *
     * @return void
     */
    public function save(DomainModel $model): void
    {
        $this->getEntityManager()->persist($model);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes $model and flushes the entity manager change list.
     *
     * This method allows controllers to not depend on the entity manager, but only on the repositories instead,
     * following the Law of Demeter.
     *
     * @param DomainModel $model
     *
     * @return void
     */
    public function remove(DomainModel $model): void
    {
        $this->getEntityManager()->remove($model);
        $this->getEntityManager()->flush();
    }
}

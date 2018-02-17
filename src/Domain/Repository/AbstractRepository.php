<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Domain\Repository;

use Doctrine\ORM\EntityRepository;
use PhpList\PhpList4\Domain\Model\Interfaces\Identity;

/**
 * Base class for repositories.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
abstract class AbstractRepository extends EntityRepository
{
    /**
     * Persists and $model and flushes the entity manager change list.
     *
     * This method allows controllers to not depend on the entity manager, but only on the repositories instead,
     * following the Law of Demeter.
     *
     * @param Identity $model
     *
     * @return void
     */
    public function save(Identity $model)
    {
        $this->getEntityManager()->persist($model);
        $this->getEntityManager()->flush();
    }
}

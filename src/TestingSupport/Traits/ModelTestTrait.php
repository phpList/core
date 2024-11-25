<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use ReflectionObject;

/**
 * This trait provides methods helpful in testing domain models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait ModelTestTrait
{
    /**
     * Sets the (private) ID of $this->repository.
     *
     * @param int $id
     *
     * @return void
     */
    private function setSubjectId(DomainModel $model,int $id): void
    {
        $this->setSubjectProperty($model,'id', $id);
    }

    /**
     * Sets the (private) property $propertyName of $this->repository.
     *
     * @param string $propertyName
     * @param mixed $value
     *
     * @return void
     * @internal
     *
     */
    private function setSubjectProperty(DomainModel $model, string $propertyName, mixed $value): void
    {
        $reflectionObject = new ReflectionObject($model);
        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setValue($model, $value);
    }
}

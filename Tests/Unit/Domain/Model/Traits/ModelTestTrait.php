<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Traits;

/**
 * This trait provides methods helpful in testing domain models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait ModelTestTrait
{
    /**
     * Sets the (private) ID of $this->subject.
     *
     * @param int $id
     *
     * @return void
     */
    private function setSubjectId(int $id)
    {
        $this->setSubjectProperty('id', $id);
    }

    /**
     * Sets the (private) property $propertyName of $this->subject.
     *
     * @internal
     *
     * @param string $propertyName
     * @param mixed $value
     *
     * @return void
     */
    private function setSubjectProperty(string $propertyName, $value)
    {
        $reflectionObject = new \ReflectionObject($this->subject);
        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->subject, $value);
    }
}

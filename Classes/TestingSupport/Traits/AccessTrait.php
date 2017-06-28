<?php
declare(strict_types=1);

namespace PhpList\PhpList4\TestingSupport\Traits;

/**
 * This trait provides methods to provide access to protected and private methods.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait AccessTrait
{
    /**
     * Calls the (private or protected) method $method of $object
     *
     * @param object $object
     * @param string $methodName
     * @param mixed $arguments
     *
     * @return void
     */
    private function callInaccessibleMethod($object, string $methodName, ...$arguments)
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethod = $reflectionObject->getMethod($methodName);
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($object, $arguments);
    }
}

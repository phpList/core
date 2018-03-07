<?php
declare(strict_types=1);

namespace PhpList\Core\TestingSupport\Traits;

/**
 * This trait provides the assertContainsInstanceOf method.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait ContainsInstanceAssertionTrait
{
    /**
     * @param string $className
     * @param array $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertContainsInstanceOf(string $className, array $haystack, string $message = '')
    {
        $found = false;
        foreach ($haystack as $element) {
            if (is_object($element) && $element instanceof $className) {
                $found = true;
                break;
            }
        }

        $defaultMessage = 'Failed asserting that an array contains an instance of ' . $className;
        static::assertTrue($found, $message ?: $defaultMessage);
    }
}

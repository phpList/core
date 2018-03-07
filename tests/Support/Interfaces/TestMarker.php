<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Support\Interfaces;

/**
 * The presence of this interface means that the test classes can be autoloaded (which can only be the case if
 * core is the root package, never if it is used as a library).
 *
 * This is the only purpose of this interface.
 *
 * No class should implement this interface.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
interface TestMarker
{
}

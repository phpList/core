<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use PhpList\PhpList4\Core\ApplicationStructure;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationStructureTest extends TestCase
{
    /**
     * @var ApplicationStructure
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new ApplicationStructure();
    }

    /**
     * @test
     */
    public function getApplicationRootReturnsCoreApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }
}

<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use PhpList\Core\Core\ApplicationStructure;
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
        static::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }

    /**
     * @test
     */
    public function getCorePackageRootReturnsCorePackageRoot()
    {
        static::assertSame(dirname(__DIR__, 3), $this->subject->getCorePackageRoot());
    }
}

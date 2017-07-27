<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Core;

use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationKernelTest extends TestCase
{
    /**
     * @var ApplicationKernel
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new ApplicationKernel(Bootstrap::APPLICATION_CONTEXT_TESTING, true);
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function getProjectDirReturnsApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getProjectDir());
    }

    /**
     * @test
     */
    public function getRootDirReturnsApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getRootDir());
    }
}

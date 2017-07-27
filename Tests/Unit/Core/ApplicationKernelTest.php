<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;

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
    public function isKernelInstance()
    {
        self::assertInstanceOf(Kernel::class, $this->subject);
    }

    /**
     * @test
     */
    public function getProjectDirByDefaultReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getProjectDir());
    }

    /**
     * @test
     */
    public function setProjectDirSetsProjectDir()
    {
        $projectDir = '/foo';
        $this->subject->setProjectDir($projectDir);

        self::assertSame($projectDir, $this->subject->getProjectDir());
    }
}

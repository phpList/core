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
     * @return string
     */
    private function getCorePackageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @test
     */
    public function getProjectDirReturnsCorePackageRoot()
    {
        self::assertSame($this->getCorePackageRoot(), $this->subject->getProjectDir());
    }

    /**
     * @test
     */
    public function getRootDirReturnsCorePackageRoot()
    {
        self::assertSame($this->getCorePackageRoot(), $this->subject->getRootDir());
    }

    /**
     * @return string
     */
    private function getApplicationRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @test
     */
    public function getCacheDirReturnsEnvironmentSpecificVarCacheDirectoryInApplicationRoot()
    {
        self::assertSame(
            $this->getApplicationRoot() . '/var/cache/' . Bootstrap::APPLICATION_CONTEXT_TESTING,
            $this->subject->getCacheDir()
        );
    }

    /**
     * @test
     */
    public function getLogDirReturnsVarLogsDirectoryInApplicationRoot()
    {
        self::assertSame($this->getApplicationRoot() . '/var/logs', $this->subject->getLogDir());
    }
}

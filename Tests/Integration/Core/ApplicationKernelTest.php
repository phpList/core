<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Core;

use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
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
        $this->subject = new ApplicationKernel(Environment::TESTING, true);
        $this->subject->boot();
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
        static::assertSame($this->getCorePackageRoot(), $this->subject->getProjectDir());
    }

    /**
     * @test
     */
    public function getRootDirReturnsCorePackageRoot()
    {
        static::assertSame($this->getCorePackageRoot(), $this->subject->getRootDir());
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
        static::assertSame(
            $this->getApplicationRoot() . '/var/cache/' . Environment::TESTING,
            $this->subject->getCacheDir()
        );
    }

    /**
     * @test
     */
    public function getLogDirReturnsVarLogsDirectoryInApplicationRoot()
    {
        static::assertSame($this->getApplicationRoot() . '/var/logs', $this->subject->getLogDir());
    }

    /**
     * @test
     */
    public function applicationDirIsAvailableAsContainerParameter()
    {
        $container = $this->subject->getContainer();

        static::assertSame($this->getApplicationRoot(), $container->getParameter('kernel.application_dir'));
    }
}

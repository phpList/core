<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Core;

use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
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
    private ApplicationKernel $subject;

    protected function setUp(): void
    {
        $this->subject = new ApplicationKernel(Environment::TESTING, true);
        $this->subject->boot();
    }

    protected function tearDown(): void
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
            $this->getApplicationRoot() . '/var/cache/' . Environment::TESTING,
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

    /**
     * @test
     */
    public function applicationDirIsAvailableAsContainerParameter()
    {
        $container = $this->subject->getContainer();

        self::assertSame($this->getApplicationRoot(), $container->getParameter('kernel.application_dir'));
    }
}

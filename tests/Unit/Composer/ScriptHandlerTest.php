<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use PhpList\Core\Composer\ScriptHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ScriptHandlerTest extends TestCase
{
    private InstalledRepositoryInterface|MockObject $localRepository;
    private RootPackageInterface|MockObject $rootPackage;

    public function testCreateBinariesForCorePackageThrowsException(): void
    {
        $event = $this->createMockEventForCorePackage();

        $this->expectException(IOException::class);

        ScriptHandler::createBinaries($event);
    }

    public function testCreatePublicWebDirectoryForCorePackageThrowsException(): void
    {
        $event = $this->createMockEventForCorePackage();

        $this->expectException(IOException::class);

        ScriptHandler::createPublicWebDirectory($event);
    }

    public function testListModulesForPhpListModuleRootPackageListsIt(): void
    {
        $rootPackageName = 'phplist/core';
        $rootPackageVersion = '1.2.3';

        $event = $this->createMockEvent();

        $this->rootPackage->method('getName')->willReturn($rootPackageName);
        $this->rootPackage->method('getType')->willReturn('phplist-module');
        $this->rootPackage->method('getPrettyVersion')->willReturn($rootPackageVersion);

        $this->localRepository->method('getPackages')->willReturn([]);

        ScriptHandler::listModules($event);

        $this->expectOutputRegex(
            '/'
            . preg_quote($rootPackageName, '/')
            . '\s+'
            . preg_quote($rootPackageVersion, '/')
            . '/'
        );
    }

    public function testListModulesForNonPhpListModuleRootPackageExcludesIt(): void
    {
        $rootPackageName = 'phplist/core';

        $event = $this->createMockEvent();

        $this->rootPackage->method('getName')->willReturn($rootPackageName);
        $this->rootPackage->method('getType')->willReturn('project');

        $this->localRepository->method('getPackages')->willReturn([]);

        ScriptHandler::listModules($event);

        $output = $this->getActualOutput();
        self::assertStringNotContainsString($rootPackageName, $output);
    }

    public function testListModulesForPhpListModuleDependencyListsIt(): void
    {
        $rootPackageName = 'phplist/base-distribution';
        $dependencyPackageName = 'amazing/listview';
        $dependencyPackageVersion = '2.3.6';

        $event = $this->createMockEvent();

        $this->rootPackage->method('getName')->willReturn($rootPackageName);
        $this->rootPackage->method('getType')->willReturn('project');

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn($dependencyPackageName);
        $dependency->method('getType')->willReturn('phplist-module');
        $dependency->method('getPrettyVersion')->willReturn($dependencyPackageVersion);

        $this->localRepository->method('getPackages')->willReturn([$dependency]);

        ScriptHandler::listModules($event);

        $this->expectOutputRegex(
            '/'
            . preg_quote($dependencyPackageName, '/')
            . '\s+'
            . preg_quote($dependencyPackageVersion, '/')
            . '/'
        );
    }

    public function testListModulesForNonPhpListModuleDependencyExcludesIt(): void
    {
        $rootPackageName = 'phplist/base-distribution';
        $dependencyPackageName = 'symfony/symfony';

        $event = $this->createMockEvent();

        $this->rootPackage->method('getName')->willReturn($rootPackageName);
        $this->rootPackage->method('getType')->willReturn('project');

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn($dependencyPackageName);
        $dependency->method('getType')->willReturn('library');

        $this->localRepository->method('getPackages')->willReturn([$dependency]);

        ScriptHandler::listModules($event);

        $output = $this->getActualOutput();
        self::assertStringNotContainsString($dependencyPackageName, $output);
    }

    private function createMockEventForCorePackage(): Event
    {
        $this->rootPackage = $this->createMock(RootPackageInterface::class);

        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($this->rootPackage);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        $this->localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $repositoryManager->method('getLocalRepository')->willReturn($this->localRepository);

        $event = $this->createMock(Event::class);
        $event->method('getComposer')->willReturn($composer);

        return $event;
    }

    private function createMockEvent(): Event
    {
        $this->rootPackage = $this->createMock(RootPackageInterface::class);
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($this->rootPackage);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        $this->localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $repositoryManager->method('getLocalRepository')->willReturn($this->localRepository);

        $event = $this->createMock(Event::class);
        $event->method('getComposer')->willReturn($composer);

        return $event;
    }
}

<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use PhpList\PhpList4\Composer\ScriptHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophecy\ProphecySubjectInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ScriptHandlerTest extends TestCase
{
    /**
     * @var WritableRepositoryInterface|ObjectProphecy
     */
    private $localRepositoryProphecy = null;

    /**
     * @var RootPackageInterface|ObjectProphecy
     */
    private $rootPackageProphecy = null;

    /**
     * @test
     */
    public function createBinariesForCorePackageThrowsException()
    {
        $event = $this->createEventProphecyForCorePackage();

        $this->expectException(\DomainException::class);

        ScriptHandler::createBinaries($event);
    }

    /**
     * @test
     */
    public function createPublicWebDirectoryForCorePackageThrowsException()
    {
        $event = $this->createEventProphecyForCorePackage();

        $this->expectException(\DomainException::class);

        ScriptHandler::createPublicWebDirectory($event);
    }

    /**
     * @return Event|ProphecySubjectInterface
     */
    private function createEventProphecyForCorePackage()
    {
        /** @var RootPackageInterface|ObjectProphecy $packageProphecy */
        $packageProphecy = $this->prophesize(RootPackageInterface::class);
        $packageProphecy->getName()->willReturn('phplist/core');
        /** @var Composer|ObjectProphecy $composerProphecy */
        $composerProphecy = $this->prophesize(Composer::class);
        $composerProphecy->getPackage()->willReturn($packageProphecy->reveal());
        /** @var Event|ObjectProphecy $eventProphecy */
        $eventProphecy = $this->prophesize(Event::class);
        $eventProphecy->getComposer()->willReturn($composerProphecy->reveal());

        return $eventProphecy->reveal();
    }

    /**
     * @test
     */
    public function listModulesForPhpListModuleRootPackageListsIt()
    {
        $rootPackageName = 'phplist/core';
        $rootPackageVersion = '1.2.3';

        $event = $this->buildMockEvent();

        $this->rootPackageProphecy->getName()->willReturn($rootPackageName);
        $this->rootPackageProphecy->getType()->willReturn('phplist-module');
        $this->rootPackageProphecy->getPrettyVersion()->willReturn($rootPackageVersion);

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        ScriptHandler::listModules($event);

        $this->expectOutputRegex('#' . $rootPackageName . ' +' . $rootPackageVersion . '#');
    }

    /**
     * @test
     */
    public function listModulesForNonPhpListModuleRootPackageExcludesIt()
    {
        $rootPackageName = 'phplist/core';

        $event = $this->buildMockEvent();

        $this->rootPackageProphecy->getName()->willReturn($rootPackageName);
        $this->rootPackageProphecy->getType()->willReturn('project');

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        ScriptHandler::listModules($event);

        $output = $this->getActualOutput();
        static::assertNotContains($rootPackageName, $output);
    }

    /**
     * @test
     */
    public function listModulesForPhpListModuleDependencyListsIt()
    {
        $rootPackageName = 'phplist/base-distribution';
        $dependencyPackageName = 'amazing/listview';
        $dependencyPackageVersion = '2.3.6';

        $event = $this->buildMockEvent();

        $this->rootPackageProphecy->getName()->willReturn($rootPackageName);
        $this->rootPackageProphecy->getType()->willReturn('project');

        /** @var PackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(PackageInterface::class);
        $dependencyProphecy->getName()->willReturn($dependencyPackageName);
        $dependencyProphecy->getType()->willReturn('phplist-module');
        $dependencyProphecy->getPrettyVersion()->willReturn($dependencyPackageVersion);

        /** @var PackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();
        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        ScriptHandler::listModules($event);

        $this->expectOutputRegex('#' . $dependencyPackageName . ' +' . $dependencyPackageVersion . '#');
    }

    /**
     * @test
     */
    public function listModulesForNonPhpListModuleDependencyExcludesIt()
    {
        $rootPackageName = 'phplist/base-distribution';
        $dependencyPackageName = 'symfony/symfony';

        $event = $this->buildMockEvent();

        $this->rootPackageProphecy->getName()->willReturn($rootPackageName);
        $this->rootPackageProphecy->getType()->willReturn('project');

        /** @var PackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(PackageInterface::class);
        $dependencyProphecy->getName()->willReturn($dependencyPackageName);
        $dependencyProphecy->getType()->willReturn('library');

        /** @var PackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();
        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        ScriptHandler::listModules($event);

        $output = $this->getActualOutput();
        static::assertNotContains($dependencyPackageName, $output);
    }

    /**
     * @return Event
     */
    private function buildMockEvent(): Event
    {
        /** @var Composer|ObjectProphecy $composerProphecy */
        $composerProphecy = $this->prophesize(Composer::class);
        /** @var Composer|ProphecySubjectInterface $composer */
        $composer = $composerProphecy->reveal();

        /** @var RepositoryManager|ObjectProphecy $repositoryManagerProphecy */
        $repositoryManagerProphecy = $this->prophesize(RepositoryManager::class);
        /** @var RepositoryManager|ProphecySubjectInterface $repositoryManager */
        $repositoryManager = $repositoryManagerProphecy->reveal();
        $composerProphecy->getRepositoryManager()->willReturn($repositoryManager);

        $this->localRepositoryProphecy = $this->prophesize(WritableRepositoryInterface::class);
        /** @var WritableRepositoryInterface|ProphecySubjectInterface $localRepository */
        $localRepository = $this->localRepositoryProphecy->reveal();
        $repositoryManagerProphecy->getLocalRepository()->willReturn($localRepository);

        /** @var RootPackageInterface|ObjectProphecy $rootPackageProphecy */
        $rootPackageProphecy = $this->prophesize(RootPackageInterface::class);
        /** @var RootPackageInterface|ProphecySubjectInterface $rootPackage */
        $rootPackage = $rootPackageProphecy->reveal();
        $composerProphecy->getPackage()->willReturn($rootPackage);
        $this->rootPackageProphecy = $rootPackageProphecy;

        /** @var Event|ObjectProphecy $eventProphecy */
        $eventProphecy = $this->prophesize(Event::class);
        $eventProphecy->getComposer()->willReturn($composer);
        /** @var Event|ProphecySubjectInterface $eventProphecy */
        $event = $eventProphecy->reveal();

        return $event;
    }
}

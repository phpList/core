<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use PhpList\PhpList4\Composer\PackageRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophecy\ProphecySubjectInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PackageRepositoryTest extends TestCase
{
    /**
     * @var PackageRepository
     */
    private $subject = null;

    /**
     * @var Composer|ObjectProphecy
     */
    private $composerProphecy = null;

    /**
     * @var Composer|ProphecySubjectInterface
     */
    private $composer = null;

    /**
     * @var WritableRepositoryInterface|ProphecySubjectInterface
     */
    private $localRepositoryProphecy = null;

    protected function setUp()
    {
        $this->subject = new PackageRepository();

        /** @var Composer|ObjectProphecy $composerProphecy */
        $composerProphecy = $this->prophesize(Composer::class);
        $this->composerProphecy = $composerProphecy;
        $this->composer = $composerProphecy->reveal();

        /** @var RepositoryManager|ObjectProphecy $repositoryManagerProphecy */
        $repositoryManagerProphecy = $this->prophesize(RepositoryManager::class);
        /** @var RepositoryManager|ProphecySubjectInterface $repositoryManager */
        $repositoryManager = $repositoryManagerProphecy->reveal();
        $composerProphecy->getRepositoryManager()->willReturn($repositoryManager);

        $this->localRepositoryProphecy = $this->prophesize(WritableRepositoryInterface::class);
        /** @var WritableRepositoryInterface|ProphecySubjectInterface $localRepository */
        $localRepository = $this->localRepositoryProphecy->reveal();
        $repositoryManagerProphecy->getLocalRepository()->willReturn($localRepository);

        $this->subject->injectComposer($this->composer);
    }

    /**
     * @test
     */
    public function findAllIncludesDependencies()
    {
        $this->composerProphecy->getPackage()->willReturn($this->prophesize(RootPackageInterface::class)->reveal());

        /** @var PackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(PackageInterface::class);
        $dependencyProphecy->getName()->willReturn('phplist/core');
        /** @var PackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();
        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        static::assertContains($dependency, $this->subject->findAll());
    }

    /**
     * @test
     */
    public function findAllIncludesRootPackage()
    {
        /** @var RootPackageInterface|ProphecySubjectInterface $rootPackage */
        $rootPackage = $this->prophesize(RootPackageInterface::class)->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        static::assertContains($rootPackage, $this->subject->findAll());
    }

    /**
     * @test
     */
    public function findAllExcludesDuplicates()
    {
        $this->composerProphecy->getPackage()->willReturn($this->prophesize(RootPackageInterface::class)->reveal());

        $packageName = 'phplist/core';

        /** @var PackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(PackageInterface::class);
        $dependencyProphecy->getName()->willReturn($packageName);
        /** @var PackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();
        /** @var PackageInterface|ObjectProphecy $dependencyAliasProphecy */
        $dependencyAliasProphecy = $this->prophesize(PackageInterface::class);
        $dependencyAliasProphecy->getName()->willReturn($packageName);
        /** @var PackageInterface|ProphecySubjectInterface $dependency1 */
        $dependencyAlias = $dependencyAliasProphecy->reveal();
        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency, $dependencyAlias]);

        static::assertNotContains($dependencyAlias, $this->subject->findAll());
    }

    /**
     * @test
     */
    public function findModulesForPhpListModuleRootPackageIncludesIt()
    {
        /** @var RootPackageInterface|ObjectProphecy $rootPackageProphecy */
        $rootPackageProphecy = $this->prophesize(RootPackageInterface::class);
        $rootPackageProphecy->getName()->willReturn('phplist/base-installation');
        $rootPackageProphecy->getType()->willReturn('phplist-module');
        /** @var RootPackageInterface|ProphecySubjectInterface $rootPackage */
        $rootPackage = $rootPackageProphecy->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        static::assertContains($rootPackage, $this->subject->findModules());
    }

    /**
     * @test
     */
    public function findModulesForPhpListModuleDependencyReturnsIt()
    {
        /** @var RootPackageInterface|ObjectProphecy $rootPackageProphecy */
        $rootPackageProphecy = $this->prophesize(RootPackageInterface::class);
        /** @var RootPackageInterface|ObjectProphecy $rootPackage */
        $rootPackage = $rootPackageProphecy->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        /** @var RootPackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(RootPackageInterface::class);
        $dependencyProphecy->getType()->willReturn('phplist-module');
        $dependencyProphecy->getName()->willReturn('phplist/core');
        /** @var RootPackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();

        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        static::assertContains($dependency, $this->subject->findModules());
    }

    /**
     * @return string[][]
     */
    public function nonPhpListModuleTypeDataProvider(): array
    {
        return [
            'empty type' => [''],
            'library (default)' => ['library'],
            'project' => ['project'],
            'symfony-bundle' => ['symfony-bundle'],
        ];
    }

    /**
     * @test
     * @param string $type
     * @dataProvider nonPhpListModuleTypeDataProvider
     */
    public function findModulesForNonPhpListModuleRootPackageIgnoresIt(string $type)
    {
        /** @var RootPackageInterface|ObjectProphecy $rootPackageProphecy */
        $rootPackageProphecy = $this->prophesize(RootPackageInterface::class);
        $rootPackageProphecy->getType()->willReturn($type);
        $rootPackageProphecy->getName()->willReturn('phplist/base-installation');
        /** @var RootPackageInterface|ProphecySubjectInterface $rootPackage */
        $rootPackage = $rootPackageProphecy->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        static::assertNotContains($rootPackage, $this->subject->findModules());
    }

    /**
     * @test
     * @param string $type
     * @dataProvider nonPhpListModuleTypeDataProvider
     */
    public function findModulesForNonPhpListModuleDependencyIgnoresIt(string $type)
    {
        /** @var RootPackageInterface|ObjectProphecy $rootPackageProphecy */
        $rootPackageProphecy = $this->prophesize(RootPackageInterface::class);
        /** @var RootPackageInterface|ProphecySubjectInterface $rootPackage */
        $rootPackage = $rootPackageProphecy->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        /** @var RootPackageInterface|ObjectProphecy $dependencyProphecy */
        $dependencyProphecy = $this->prophesize(RootPackageInterface::class);
        $dependencyProphecy->getType()->willReturn($type);
        $dependencyProphecy->getName()->willReturn('phplist/test');
        /** @var RootPackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $dependencyProphecy->reveal();

        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        static::assertNotContains($dependency, $this->subject->findModules());
    }
}

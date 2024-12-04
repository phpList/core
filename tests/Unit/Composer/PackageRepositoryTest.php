<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use PhpList\Core\Composer\PackageRepository;
use PHPUnit\Framework\TestCase;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PackageRepositoryTest extends TestCase
{
    private PackageRepository $subject;
    private Composer $composer;
    private InstalledRepositoryInterface $localRepository;

    protected function setUp(): void
    {
        $this->subject = new PackageRepository();

        $this->composer = $this->createMock(Composer::class);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $this->composer->method('getRepositoryManager')->willReturn($repositoryManager);

        $this->localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $repositoryManager->method('getLocalRepository')->willReturn($this->localRepository);

        $this->subject->injectComposer($this->composer);

        $this->subject->injectComposer($this->composer);
    }

    public function testFindAllIncludesDependencies(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $this->composer->method('getPackage')->willReturn($rootPackage);

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn('phplist/core');

        $this->localRepository->method('getPackages')->willReturn([$dependency]);

        $result = $this->subject->findAll();
        self::assertContains($dependency, $result);
    }

    public function testFindAllIncludesRootPackage(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $this->composer->method('getPackage')->willReturn($rootPackage);

        $this->localRepository->method('getPackages')->willReturn([]);

        $result = $this->subject->findAll();
        self::assertContains($rootPackage, $result);
    }

    public function testFindAllExcludesDuplicates(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $this->composer->method('getPackage')->willReturn($rootPackage);

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn('phplist/core');

        $duplicateDependency = $this->createMock(PackageInterface::class);
        $duplicateDependency->method('getName')->willReturn('phplist/core');

        $this->localRepository->method('getPackages')->willReturn([$dependency, $duplicateDependency]);

        $result = $this->subject->findAll();
        self::assertNotContains($duplicateDependency, $result);
    }

    public function testFindModulesForPhpListModuleRootPackageIncludesIt(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getName')->willReturn('phplist/base-installation');
        $rootPackage->method('getType')->willReturn('phplist-module');

        $this->composer->method('getPackage')->willReturn($rootPackage);

        $this->localRepository->method('getPackages')->willReturn([]);

        $result = $this->subject->findModules();
        self::assertContains($rootPackage, $result);
    }

    public function testFindModulesForPhpListModuleDependencyReturnsIt(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $this->composer->method('getPackage')->willReturn($rootPackage);

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn('phplist/core');
        $dependency->method('getType')->willReturn('phplist-module');

        $this->localRepository->method('getPackages')->willReturn([$dependency]);

        $result = $this->subject->findModules();
        self::assertContains($dependency, $result);
    }

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
     * @dataProvider nonPhpListModuleTypeDataProvider
     */
    public function testFindModulesForNonPhpListModuleRootPackageIgnoresIt(string $type): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getName')->willReturn('phplist/base-installation');
        $rootPackage->method('getType')->willReturn($type);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        $this->localRepository->method('getPackages')->willReturn([]);

        $result = $this->subject->findModules();
        self::assertNotContains($rootPackage, $result);
    }

    /**
     * @dataProvider nonPhpListModuleTypeDataProvider
     */
    public function testFindModulesForNonPhpListModuleDependencyIgnoresIt(string $type): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $this->composer->method('getPackage')->willReturn($rootPackage);

        $dependency = $this->createMock(PackageInterface::class);
        $dependency->method('getName')->willReturn('phplist/test');
        $dependency->method('getType')->willReturn($type);

        $this->localRepository->method('getPackages')->willReturn([$dependency]);

        $result = $this->subject->findModules();
        self::assertNotContains($dependency, $result);
    }
}

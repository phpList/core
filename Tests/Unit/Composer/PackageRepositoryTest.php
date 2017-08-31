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

        /** @var PackageInterface|ProphecySubjectInterface $dependency */
        $dependency = $this->prophesize(PackageInterface::class)->reveal();
        $this->localRepositoryProphecy->getPackages()->willReturn([$dependency]);

        self::assertContains($dependency, $this->subject->findAll());
    }

    /**
     * @test
     */
    public function findAllIncludesRootPackage()
    {
        /** @var RootPackageInterface|ObjectProphecy $rootPackage */
        $rootPackage = $this->prophesize(RootPackageInterface::class)->reveal();
        $this->composerProphecy->getPackage()->willReturn($rootPackage);

        $this->localRepositoryProphecy->getPackages()->willReturn([]);

        self::assertContains($rootPackage, $this->subject->findAll());
    }
}

<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Composer;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
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
        $packageProphecy->getName()->willReturn('phplist/phplist4-core');
        /** @var Composer|ObjectProphecy $composerProphecy */
        $composerProphecy = $this->prophesize(Composer::class);
        $composerProphecy->getPackage()->willReturn($packageProphecy->reveal());
        /** @var Event|ObjectProphecy $eventProphecy */
        $eventProphecy = $this->prophesize(Event::class);
        $eventProphecy->getComposer()->willReturn($composerProphecy->reveal());

        return $eventProphecy->reveal();
    }
}

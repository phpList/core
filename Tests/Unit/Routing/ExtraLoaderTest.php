<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Routing;

use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Routing\ExtraLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ExtraLoaderTest extends TestCase
{
    /**
     * @var ExtraLoader
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new ExtraLoader();
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function classIsLoader()
    {
        self::assertInstanceOf(Loader::class, $this->subject);
    }

    /**
     * @test
     */
    public function supportsExtraType()
    {
        self::assertTrue($this->subject->supports('', 'extra'));
    }

    /**
     * @test
     */
    public function notSupportsOtherType()
    {
        self::assertFalse($this->subject->supports('', 'foo'));
    }

    /**
     * @test
     */
    public function loadReturnsRouteCollection()
    {
        self::assertInstanceOf(RouteCollection::class, $this->subject->load('', 'extra'));
    }
}

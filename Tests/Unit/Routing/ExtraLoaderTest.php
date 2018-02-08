<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Routing;

use PhpList\PhpList4\Core\ApplicationStructure;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Routing\ExtraLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\Loader;

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
        $this->subject = new ExtraLoader(new ApplicationStructure());
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
        static::assertInstanceOf(Loader::class, $this->subject);
    }

    /**
     * @test
     */
    public function supportsExtraType()
    {
        static::assertTrue($this->subject->supports('', 'extra'));
    }

    /**
     * @test
     */
    public function notSupportsOtherType()
    {
        static::assertFalse($this->subject->supports('', 'foo'));
    }
}

<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Routing;

use PhpList\Core\Core\ApplicationStructure;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Routing\ExtraLoader;
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

    protected function setUp(): void
    {
        $this->subject = new ExtraLoader(new ApplicationStructure());
    }

    protected function tearDown(): void
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

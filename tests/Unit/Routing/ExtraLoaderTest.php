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
    private ExtraLoader $subject;

    protected function setUp(): void
    {
        $this->subject = new ExtraLoader(new ApplicationStructure());
    }

    protected function tearDown(): void
    {
        Bootstrap::purgeInstance();
    }

    public function testClassIsLoader(): void
    {
        self::assertInstanceOf(Loader::class, $this->subject);
    }

    public function testSupportsExtraType(): void
    {
        self::assertTrue($this->subject->supports('', 'extra'));
    }

    public function testNotSupportsOtherType(): void
    {
        self::assertFalse($this->subject->supports('', 'foo'));
    }
}

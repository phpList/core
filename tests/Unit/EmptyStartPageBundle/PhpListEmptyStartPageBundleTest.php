<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\EmptyStartPageBundle;

use PhpList\Core\EmptyStartPageBundle\PhpListEmptyStartPageBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PhpListEmptyStartPageBundleTest extends TestCase
{
    /**
     * @var PhpListEmptyStartPageBundle
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new PhpListEmptyStartPageBundle();
    }

    /**
     * @test
     */
    public function classIsBundle()
    {
        static::assertInstanceOf(Bundle::class, $this->subject);
    }
}

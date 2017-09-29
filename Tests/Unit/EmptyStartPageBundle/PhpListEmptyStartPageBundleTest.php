<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\EmptyStartPageBundle;

use PhpList\PhpList4\EmptyStartPageBundle\PhpListEmptyStartPageBundle;
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
        self::assertInstanceOf(Bundle::class, $this->subject);
    }
}

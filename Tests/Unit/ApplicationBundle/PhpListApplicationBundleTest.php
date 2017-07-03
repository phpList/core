<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\ApplicationBundle;

use PhpList\PhpList4\ApplicationBundle\PhpListApplicationBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PhpListApplicationBundleTest extends TestCase
{
    /**
     * @var PhpListApplicationBundle
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new PhpListApplicationBundle();
    }

    /**
     * @test
     */
    public function classIsBundle()
    {
        self::assertInstanceOf(Bundle::class, $this->subject);
    }
}

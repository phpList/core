<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\EmptyStartPageBundle;

use PhpList\Core\EmptyStartPageBundle\EmptyStartPageBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PhpListEmptyStartPageBundleTest extends TestCase
{
    private EmptyStartPageBundle $subject;

    protected function setUp(): void
    {
        $this->subject = new EmptyStartPageBundle();
    }

    public function testClassIsBundle(): void
    {
        self::assertInstanceOf(Bundle::class, $this->subject);
    }
}

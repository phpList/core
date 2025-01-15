<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use PhpList\Core\Core\ApplicationStructure;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationStructureTest extends TestCase
{
    private ApplicationStructure $subject;

    protected function setUp(): void
    {
        $this->subject = new ApplicationStructure();
    }

    public function testGetApplicationRootReturnsCoreApplicationRoot(): void
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }

    public function testGetCorePackageRootReturnsCorePackageRoot(): void
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getCorePackageRoot());
    }
}

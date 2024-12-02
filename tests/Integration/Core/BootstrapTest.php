<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Core;

use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class BootstrapTest extends TestCase
{
    private Bootstrap $subject;

    protected function setUp(): void
    {
        $this->subject = Bootstrap::getInstance();
        $this->subject->setEnvironment(Environment::TESTING);
    }

    protected function tearDown(): void
    {
        Bootstrap::purgeInstance();
    }

    public function testEnsureDevelopmentOrTestingEnvironmentForTestingEnvironmentHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->ensureDevelopmentOrTestingEnvironment());
    }

    public function testGetApplicationRootReturnsCoreApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }
}

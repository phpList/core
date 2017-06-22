<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use PhpList\PhpList4\Core\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class BootstrapTest extends TestCase
{
    /**
     * @var Bootstrap
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = Bootstrap::getInstance();
        $this->subject->activateDevelopmentMode();
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function getInstanceReturnsBootstrapInstance()
    {
        self::assertInstanceOf(Bootstrap::class, Bootstrap::getInstance());
    }

    /**
     * @test
     */
    public function classIsSingleton()
    {
        self::assertSame(Bootstrap::getInstance(), Bootstrap::getInstance());
    }

    /**
     * @test
     */
    public function purgeInstancePurgesSingletonInstance()
    {
        $firstInstance = Bootstrap::getInstance();

        Bootstrap::purgeInstance();

        $secondInstance = Bootstrap::getInstance();
        self::assertNotSame($firstInstance, $secondInstance);
    }

    /**
     * @test
     */
    public function developmentModeIsOffByDefault()
    {
        Bootstrap::purgeInstance();
        $newInstance = Bootstrap::getInstance();

        self::assertFalse($newInstance->isInDevelopmentMode());
    }

    /**
     * @test
     */
    public function activateDevelopmentModeHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->activateDevelopmentMode());
    }

    /**
     * @test
     */
    public function activateDevelopmentModeActivatesDevelopmentMode()
    {
        $this->subject->activateDevelopmentMode();

        self::assertTrue($this->subject->isInDevelopmentMode());
    }

    /**
     * @test
     */
    public function configureHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->configure());
    }
}

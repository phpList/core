<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\PhpList4\Core\ApplicationKernel;
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
        $this->subject->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING);
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
    public function applicationContextIsProductionByDefault()
    {
        Bootstrap::purgeInstance();

        $subject = Bootstrap::getInstance();

        self::assertSame('Production', $subject->getApplicationContext());
    }

    /**
     * @test
     */
    public function setApplicationContextHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING));
    }

    /**
     * @return string[][]
     */
    public function validApplicationContextDataProvider(): array
    {
        return [
            'Production' => [Bootstrap::APPLICATION_CONTEXT_PRODUCTION],
            'Development' => [Bootstrap::APPLICATION_CONTEXT_DEVELOPMENT],
            'Testing' => [Bootstrap::APPLICATION_CONTEXT_TESTING],
        ];
    }

    /**
     * @test
     * @param string $context
     * @dataProvider validApplicationContextDataProvider
     */
    public function setApplicationContextWithValidContextSetsContext(string $context)
    {
        $this->subject->setApplicationContext($context);

        self::assertSame($context, $this->subject->getApplicationContext());
    }

    /**
     * @test
     */
    public function setApplicationContextWithInvalidContextThrowsException()
    {
        $this->expectException(\UnexpectedValueException::class);

        $this->subject->setApplicationContext('Reckless');
    }

    /**
     * @test
     */
    public function configureHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->configure());
    }

    /**
     * @test
     */
    public function configureInitializesEntityManager()
    {
        $this->subject->configure();

        self::assertInstanceOf(EntityManagerInterface::class, $this->subject->getEntityManager());
    }

    /**
     * @test
     */
    public function configureCreatesApplicationKernel()
    {
        $this->subject->configure();

        self::assertInstanceOf(ApplicationKernel::class, $this->subject->getApplicationKernel());
    }
}

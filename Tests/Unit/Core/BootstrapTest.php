<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
        $this->subject->setEnvironment(Environment::TESTING);
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
    public function environmentIsProductionByDefault()
    {
        Bootstrap::purgeInstance();

        $subject = Bootstrap::getInstance();

        self::assertSame(Environment::PRODUCTION, $subject->getEnvironment());
    }

    /**
     * @test
     */
    public function setEnvironmentHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->setEnvironment(Environment::TESTING));
    }

    /**
     * @return string[][]
     */
    public function validEnvironmentDataProvider(): array
    {
        return [
            'Production' => [Environment::PRODUCTION],
            'Development' => [Environment::DEVELOPMENT],
            'Testing' => [Environment::TESTING],
        ];
    }

    /**
     * @test
     * @param string $environment
     * @dataProvider validEnvironmentDataProvider
     */
    public function setEnvironmentWithValidEnvironmentSetsEnvironment(string $environment)
    {
        $this->subject->setEnvironment($environment);

        self::assertSame($environment, $this->subject->getEnvironment());
    }

    /**
     * @test
     */
    public function setEnvironmentWithInvalidEnvironmentThrowsException()
    {
        $this->expectException(\UnexpectedValueException::class);

        $this->subject->setEnvironment('Reckless');
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
    public function configureCreatesApplicationKernel()
    {
        $this->subject->configure();

        self::assertInstanceOf(ApplicationKernel::class, $this->subject->getApplicationKernel());
    }

    /**
     * @test
     */
    public function getApplicationKernelWithoutConfigureThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->getApplicationKernel();
    }

    /**
     * @test
     */
    public function dispatchWithoutConfigureThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->dispatch();
    }

    /**
     * @test
     */
    public function getContainerReturnsContainer()
    {
        $this->subject->configure();

        self::assertInstanceOf(ContainerInterface::class, $this->subject->getContainer());
    }

    /**
     * @test
     */
    public function getEntityManagerWithoutConfigureThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->getEntityManager();
    }

    /**
     * @test
     */
    public function getEntityManagerAfterConfigureReturnsEntityManager()
    {
        $this->subject->configure();

        self::assertInstanceOf(EntityManagerInterface::class, $this->subject->getEntityManager());
    }
}

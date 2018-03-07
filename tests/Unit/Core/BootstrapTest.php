<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
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
        static::assertInstanceOf(Bootstrap::class, Bootstrap::getInstance());
    }

    /**
     * @test
     */
    public function classIsSingleton()
    {
        static::assertSame(Bootstrap::getInstance(), Bootstrap::getInstance());
    }

    /**
     * @test
     */
    public function purgeInstancePurgesSingletonInstance()
    {
        $firstInstance = Bootstrap::getInstance();

        Bootstrap::purgeInstance();

        $secondInstance = Bootstrap::getInstance();
        static::assertNotSame($firstInstance, $secondInstance);
    }

    /**
     * @test
     */
    public function environmentIsProductionByDefault()
    {
        Bootstrap::purgeInstance();

        $subject = Bootstrap::getInstance();

        static::assertSame(Environment::PRODUCTION, $subject->getEnvironment());
    }

    /**
     * @test
     */
    public function setEnvironmentHasFluentInterface()
    {
        static::assertSame($this->subject, $this->subject->setEnvironment(Environment::TESTING));
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

        static::assertSame($environment, $this->subject->getEnvironment());
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
        static::assertSame($this->subject, $this->subject->configure());
    }

    /**
     * @test
     */
    public function configureCreatesApplicationKernel()
    {
        $this->subject->configure();

        static::assertInstanceOf(ApplicationKernel::class, $this->subject->getApplicationKernel());
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

        static::assertInstanceOf(ContainerInterface::class, $this->subject->getContainer());
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

        static::assertInstanceOf(EntityManagerInterface::class, $this->subject->getEntityManager());
    }
}

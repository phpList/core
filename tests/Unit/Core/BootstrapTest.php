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

    public function testGetInstanceReturnsBootstrapInstance(): void
    {
        self::assertInstanceOf(Bootstrap::class, Bootstrap::getInstance());
    }

    public function testClassIsSingleton(): void
    {
        self::assertSame(Bootstrap::getInstance(), Bootstrap::getInstance());
    }

    public function testPurgeInstancePurgesSingletonInstance(): void
    {
        $firstInstance = Bootstrap::getInstance();

        Bootstrap::purgeInstance();

        $secondInstance = Bootstrap::getInstance();
        self::assertNotSame($firstInstance, $secondInstance);
    }

    public function testEnvironmentIsProductionByDefault(): void
    {
        Bootstrap::purgeInstance();

        $subject = Bootstrap::getInstance();

        self::assertSame(Environment::PRODUCTION, $subject->getEnvironment());
    }

    public function testSetEnvironmentHasFluentInterface(): void
    {
        self::assertSame($this->subject, $this->subject->setEnvironment(Environment::TESTING));
    }

    /**
     * @return array<string[]>
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
     * @dataProvider validEnvironmentDataProvider
     */
    public function testSetEnvironmentWithValidEnvironmentSetsEnvironment(string $environment): void
    {
        $this->subject->setEnvironment($environment);

        self::assertSame($environment, $this->subject->getEnvironment());
    }

    public function testSetEnvironmentWithInvalidEnvironmentThrowsException(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $this->subject->setEnvironment('Reckless');
    }

    public function testConfigureHasFluentInterface(): void
    {
        self::assertSame($this->subject, $this->subject->configure());
    }

    public function testConfigureCreatesApplicationKernel(): void
    {
        $this->subject->configure();

        self::assertInstanceOf(ApplicationKernel::class, $this->subject->getApplicationKernel());
    }

    public function testGetApplicationKernelWithoutConfigureThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->getApplicationKernel();
    }

    public function testDispatchWithoutConfigureThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->dispatch();
    }

    public function testGetContainerReturnsContainer(): void
    {
        $this->subject->configure();

        self::assertInstanceOf(ContainerInterface::class, $this->subject->getContainer());
    }

    public function testGetEntityManagerWithoutConfigureThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->getEntityManager();
    }

    public function testGetEntityManagerAfterConfigureReturnsEntityManager(): void
    {
        $this->subject->configure();

        self::assertInstanceOf(EntityManagerInterface::class, $this->subject->getEntityManager());
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PhpList\Core\EmptyStartPageBundle\EmptyStartPageBundle;
use PhpList\Core\TestingSupport\Traits\ContainsInstanceAssertionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationKernelTest extends TestCase
{
    use ContainsInstanceAssertionTrait;

    private ApplicationKernel $subject;

    protected function setUp(): void
    {
        $this->subject = new ApplicationKernel(Environment::TESTING, true);
    }

    protected function tearDown(): void
    {
        Bootstrap::purgeInstance();
    }

    public function testIsKernelInstance(): void
    {
        self::assertInstanceOf(Kernel::class, $this->subject);
    }

    public function testRegisterBundlesReturnsBundlesOnly(): void
    {
        $bundles = $this->subject->registerBundles();

        self::assertContainsOnlyInstancesOf(BundleInterface::class, $bundles);
    }

    /**
     * @return array<string[]>
     */
    public function requiredBundlesDataProvider(): array
    {
        return [
            'framework' => [FrameworkBundle::class],
            'phpList default bundle' => [EmptyStartPageBundle::class],
        ];
    }

    /**
     * @test
     * @dataProvider requiredBundlesDataProvider
     */
    public function testRegisterBundlesHasAllRequiredBundles(string $className): void
    {
        $bundles = $this->subject->registerBundles();

        self::assertContainsInstanceOf($className, $bundles);
    }
}

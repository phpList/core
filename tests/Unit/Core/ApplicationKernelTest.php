<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PhpList\Core\EmptyStartPageBundle\EmptyStartPageBundle;
use PhpList\Core\Tests\TestingSupport\Traits\ContainsInstanceAssertionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\WebServerBundle\WebServerBundle;
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

    /**
     * @var ApplicationKernel
     */
    private $subject = null;

    protected function setUp(): void
    {
        $this->subject = new ApplicationKernel(Environment::TESTING, true);
    }

    protected function tearDown(): void
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function isKernelInstance()
    {
        static::assertInstanceOf(Kernel::class, $this->subject);
    }

    /**
     * @test
     */
    public function registerBundlesReturnsBundlesOnly()
    {
        $bundles = $this->subject->registerBundles();

        static::assertContainsOnlyInstancesOf(BundleInterface::class, $bundles);
    }

    /**
     * @return string[][]
     */
    public function requiredBundlesDataProvider(): array
    {
        return [
            'framework' => [FrameworkBundle::class],
            'phpList default bundle' => [EmptyStartPageBundle::class],
            'web server' => [WebServerBundle::class],
        ];
    }

    /**
     * @test
     * @param string $className
     * @dataProvider requiredBundlesDataProvider
     */
    public function registerBundlesHasAllRequiredBundles(string $className)
    {
        $bundles = $this->subject->registerBundles();

        static::assertContainsInstanceOf($className, $bundles);
    }
}

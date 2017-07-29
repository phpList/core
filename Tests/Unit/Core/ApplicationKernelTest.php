<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Core;

use PhpList\PhpList4\ApplicationBundle\PhpListApplicationBundle;
use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PhpList\PhpList4\Tests\Support\Traits\ContainsInstanceAssertionTrait;
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

    protected function setUp()
    {
        $this->subject = new ApplicationKernel(Environment::TESTING, true);
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function isKernelInstance()
    {
        self::assertInstanceOf(Kernel::class, $this->subject);
    }

    /**
     * @test
     */
    public function registerBundlesReturnsBundlesOnly()
    {
        $bundles = $this->subject->registerBundles();

        self::assertContainsOnlyInstancesOf(BundleInterface::class, $bundles);
    }

    /**
     * @return string[][]
     */
    public function requiredBundlesDataProvider(): array
    {
        return [
            'framework' => [FrameworkBundle::class],
            'phpList default bundle' => [PhpListApplicationBundle::class],
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

        self::assertContainsInstanceOf($className, $bundles);
    }
}

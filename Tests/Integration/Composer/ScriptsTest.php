<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Composer;

use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ScriptsTest extends TestCase
{
    /**
     * @return string
     */
    private function getBundleConfigurationFilePath(): string
    {
        return dirname(__DIR__, 3) . '/Configuration/bundles.yml';
    }

    /**
     * @test
     */
    public function bundleConfigurationFileExists()
    {
        self::assertFileExists($this->getBundleConfigurationFilePath());
    }

    /**
     * @return string[][]
     */
    public function bundleClassNameDataProvider(): array
    {
        return [
            'framework bundle' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
            'sensio framework extras' => ['Sensio\\Bundle\\FrameworkExtraBundle\\SensioFrameworkExtraBundle'],
            'application bundle' => ['PhpList\\PhpList4\\ApplicationBundle\\PhpListApplicationBundle'],
        ];
    }

    /**
     * @test
     * @param string $bundleClassName
     * @dataProvider bundleClassNameDataProvider
     */
    public function bundleConfigurationFileContainsModuleBundles(string $bundleClassName)
    {
        $fileContents = file_get_contents($this->getBundleConfigurationFilePath());

        self::assertContains($bundleClassName, $fileContents);
    }

    /**
     * @return string
     */
    private function getModuleRoutesConfigurationFilePath(): string
    {
        return dirname(__DIR__, 3) . '/Configuration/routing_modules.yml';
    }

    /**
     * @test
     */
    public function moduleRoutesConfigurationFileExists()
    {
        self::assertFileExists($this->getModuleRoutesConfigurationFilePath());
    }

    /**
     * @return string[][]
     */
    public function moduleRoutingDataProvider(): array
    {
        return [
            'route name' => ['phplist/phplist4-core.homepage'],
            'resource' => ["resource: '@PhpListApplicationBundle/Controller/'"],
            'type' => ['type: annotation'],
        ];
    }

    /**
     * @test
     * @param string $routeSearchString
     * @dataProvider moduleRoutingDataProvider
     */
    public function moduleRoutesConfigurationFileContainsModuleRoutes(string $routeSearchString)
    {
        $fileContents = file_get_contents($this->getModuleRoutesConfigurationFilePath());

        self::assertContains($routeSearchString, $fileContents);
    }
}

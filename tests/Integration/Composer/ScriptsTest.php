<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Composer;

use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ScriptsTest extends TestCase
{
    private function getBundleConfigurationFilePath(): string
    {
        return dirname(__DIR__, 3) . '/config/bundles.yml';
    }

    public function testBundleConfigurationFileExists(): void
    {
        self::assertFileExists($this->getBundleConfigurationFilePath());
    }

    public function bundleClassNameDataProvider(): array
    {
        return [
            'Symfony framework bundle' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
            'Doctrine bundle' => ['Doctrine\\Bundle\\DoctrineBundle\\DoctrineBundle'],
            'empty start page bundle' => ['PhpList\\Core\\EmptyStartPageBundle\\EmptyStartPageBundle'],
        ];
    }

    /**
     * @dataProvider bundleClassNameDataProvider
     */
    public function testBundleConfigurationFileContainsModuleBundles(string $bundleClassName): void
    {
        $fileContents = file_get_contents($this->getBundleConfigurationFilePath());
        self::assertStringContainsString($bundleClassName, $fileContents);
    }

    private function getModuleRoutesConfigurationFilePath(): string
    {
        return dirname(__DIR__, 3) . '/config/routing_modules.yml';
    }

    public function testModuleRoutesConfigurationFileExists(): void
    {
        self::assertFileExists($this->getModuleRoutesConfigurationFilePath());
    }

    public function moduleRoutingDataProvider(): array
    {
        return [
            'route name' => ['phplist/core.homepage'],
            'resource' => ["resource: '@EmptyStartPageBundle/Controller/'"],
            'type' => ['type: attribute'],
        ];
    }

    /**
     * @dataProvider moduleRoutingDataProvider
     */
    public function testModuleRoutesConfigurationFileContainsModuleRoutes(string $routeSearchString): void
    {
        $fileContents = file_get_contents($this->getModuleRoutesConfigurationFilePath());
        self::assertStringContainsString($routeSearchString, $fileContents);
    }

    public function testParametersConfigurationFileExists(): void
    {
        self::assertFileExists(dirname(__DIR__, 3) . '/config/parameters.yml');
    }

    public function testModulesConfigurationFileExists(): void
    {
        self::assertFileExists(dirname(__DIR__, 3) . '/config/config_modules.yml');
    }
}

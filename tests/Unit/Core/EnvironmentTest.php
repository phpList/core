<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Core;

use PhpList\Core\Core\Environment;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class EnvironmentTest extends TestCase
{
    public function testDefaultEnvironmentIsProduction(): void
    {
        self::assertSame(Environment::PRODUCTION, Environment::DEFAULT_ENVIRONMENT);
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
    public function testValidateEnvironmentForValidEnvironmentPasses(string $environment): void
    {
        Environment::validateEnvironment($environment);

        // Adding an assertion to confirm the method executes without throwing an exception.
        self::assertTrue(true);
    }

    public function testValidateEnvironmentForInvalidEnvironmentThrowsException(): void
    {
        $environment = 'home';

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('$environment must be one of "prod", dev", test", but actually was: "home"');

        Environment::validateEnvironment($environment);
    }
}

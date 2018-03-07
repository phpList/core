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
    /**
     * @test
     */
    public function defaultEnvironmentIsProduction()
    {
        static::assertSame(Environment::PRODUCTION, Environment::DEFAULT_ENVIRONMENT);
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
    public function validateEnvironmentForValidEnvironmentPasses(string $environment)
    {
        Environment::validateEnvironment($environment);

        // This is to avoid a warning in PHPUnit that this test has no assertions (as there is no assertion
        // for "no exception is thrown").
        static::assertTrue(true);
    }

    /**
     * @test
     */
    public function validateEnvironmentForInvalidEnvironmentThrowsException()
    {
        $environment = 'home';

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('$environment must be one of "prod", dev", test", but actually was: "home"');

        Environment::validateEnvironment($environment);
    }
}

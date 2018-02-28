<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Core;

/**
 * This class provides methods and constants for the application environment/context.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
final class Environment
{
    /**
     * environment for running a live site
     *
     * @var string
     */
    const PRODUCTION = 'prod';

    /**
     * environment for developing locally
     *
     * @var string
     */
    const DEVELOPMENT = 'dev';

    /**
     * environment for running automated tests
     *
     * @var string
     */
    const TESTING = 'test';

    /**
     * @var string
     */
    const DEFAULT_ENVIRONMENT = self::PRODUCTION;

    /**
     * @var string[]
     */
    private static $validEnvironments = [self::PRODUCTION, self::DEVELOPMENT, self::TESTING];

    /**
     * Private constructor to avoid instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Validates that $environment is a valid environment, and throws an exception otherwise.
     *
     * @param string $environment must be one of the environment constants
     *
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    public static function validateEnvironment(string $environment)
    {
        if (!in_array($environment, self::$validEnvironments, true)) {
            $environmentsText = '"' . implode('", ', self::$validEnvironments) . '"';
            throw new \UnexpectedValueException(
                '$environment must be one of ' . $environmentsText . ', but actually was: "' . $environment . '"',
                1499112172
            );
        }
    }
}

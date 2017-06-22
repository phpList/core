<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Core;

/**
 * This class bootstraps the phpList core system.
 *
 * Include it in from the entry point and call Bootstrap::getInstance()->configure().
 *
 * This class is the only "real" singleton in the system. All other singletons will use
 * Symfony dependency injection.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class Bootstrap
{
    /**
     * @var Bootstrap
     */
    private static $instance = null;

    /**
     * @var bool
     */
    private $developmentMode = false;

    /**
     * Disable direct cloning of this object.
     */
    protected function __clone()
    {
    }

    /**
     * Returns 'this' as singleton.
     *
     * @return Bootstrap
     */
    public static function getInstance(): Bootstrap
    {
        if (static::$instance === null) {
            self::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Purges the singleton instance.
     *
     * Note: This method is intended to be use for tests only.
     *
     * @return void
     */
    public static function purgeInstance()
    {
        self::$instance = null;
    }

    /**
     * Activates the development mode (which basically disables all caching).
     *
     * @return Bootstrap fluent interface
     */
    public function activateDevelopmentMode(): Bootstrap
    {
        $this->developmentMode = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isInDevelopmentMode(): bool
    {
        return $this->developmentMode;
    }

    /**
     * Main entry point called at every request usually from global scope. Checks if everything is correct
     * and loads the configuration.
     *
     * @return Bootstrap fluent interface
     */
    public function configure(): Bootstrap
    {
        return $this;
    }
}

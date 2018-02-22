<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Core;

/**
 * This class provides information about the current application and its file structure.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationStructure
{
    /**
     * Returns the absolute path to the core package root.
     *
     * @return string the absolute path without the trailing slash.
     *
     * @throws \RuntimeException if there is no composer.json in the application root
     */
    public function getCorePackageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Returns the absolute path to the application root.
     *
     * When core is installed as a dependency (library) of an application, this method will return
     * the application's package path.
     *
     * When phpList4-core is installed stand-alone (i.e., as an application - usually only for testing),
     * this method will be the phpList4-core package path.
     *
     * @return string the absolute path without the trailing slash
     *
     * @throws \RuntimeException if there is no composer.json in the application root
     */
    public function getApplicationRoot(): string
    {
        $corePackagePath = $this->getCorePackageRoot();
        $corePackageIsRootPackage = interface_exists('PhpList\\PhpList4\\Tests\\Support\\Interfaces\\TestMarker');
        if ($corePackageIsRootPackage) {
            $applicationRoot = $corePackagePath;
        } else {
            // remove 3 more path segments, i.e., "vendor/phplist/core/"
            $corePackagePath = dirname($corePackagePath, 3);
            $applicationRoot = $corePackagePath;
        }

        if (!file_exists($applicationRoot . '/composer.json')) {
            throw new \RuntimeException(
                'There is no composer.json in the supposed application root "' . $applicationRoot . '".',
                1501169001588
            );
        }

        return $applicationRoot;
    }
}

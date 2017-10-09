<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Composer;

use Composer\Composer;
use Composer\Package\PackageInterface;

/**
 * Repository for Composer packages.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PackageRepository
{
    /**
     * @var string
     */
    const PHPLIST_MODULE_TYPE = 'phplist-module';

    /**
     * @var Composer
     */
    private $composer = null;

    /**
     * @param Composer $composer
     *
     * @return void
     */
    public function injectComposer(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * Finds all installed packages (including the root package).
     *
     * @return PackageInterface[]
     */
    public function findAll(): array
    {
        $rootPackage = $this->composer->getPackage();
        $dependencies = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();

        $packagesWithDuplicates = array_merge([$rootPackage], $dependencies);

        return $this->removeDuplicates($packagesWithDuplicates);
    }

    /**
     * @param PackageInterface[] $packages
     *
     * @return PackageInterface[]
     */
    private function removeDuplicates(array $packages): array
    {
        /** @var bool[] $registeredPackages */
        $registeredPackages = [];

        $result = array_filter(
            $packages,
            function (PackageInterface $package) use (&$registeredPackages) {
                $packageName = $package->getName();
                if (isset($registeredPackages[$packageName])) {
                    return false;
                }

                $registeredPackages[$packageName] = true;
                return true;
            }
        );

        return $result;
    }

    /**
     * Finds all the installed packages (including the root package) that are phpList modules (as per their type).
     *
     * @return PackageInterface[]
     */
    public function findModules(): array
    {
        return array_filter(
            $this->findAll(),
            function (PackageInterface $package) {
                return $package->getType() === self::PHPLIST_MODULE_TYPE;
            }
        );
    }
}

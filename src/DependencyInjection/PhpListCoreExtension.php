<?php

declare(strict_types=1);

namespace PhpList\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PhpListCoreExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config/services'));

        // Load core service definitions if present (keep optional to avoid breaking consumers)
        foreach (['services.yml', 'builders.yml', 'managers.yml'] as $file) {
            $path = __DIR__ . '/../../config/services/' . $file;
            if (is_file($path) && is_readable($path)) {
                $loader->load($file);
            }
        }
    }
}

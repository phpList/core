<?php

declare(strict_types=1);

namespace PhpList\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PhpListCoreExtension extends Extension
{
    private array $configFiles = [
        'builders.yml',
        'commands.yml',
        'managers.yml',
        'mappers.yml',
        'messengers.yml',
        'processors.yml',
        'providers.yml',
        'repositories.yml',
        'resolvers.yml',
        'services.yml',
        'validators.yml',
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config/services'));

        // Load core service definitions if present (keep optional to avoid breaking consumers)
        foreach ($this->configFiles as $file) {
            $path = __DIR__ . '/../../config/services/' . $file;
            if (is_file($path) && is_readable($path)) {
                $loader->load($file);
            }
        }
    }
}

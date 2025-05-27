<?php

declare(strict_types=1);

namespace PhpList\Core\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $basePath = $projectDir . '/src/Domain';

        $driverDefinition = $container->getDefinition('doctrine.orm.default_metadata_driver');

        foreach (scandir($basePath) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $modelPath = $basePath . '/' . $dir . '/Model';
            if (!is_dir($modelPath)) {
                continue;
            }

            $namespace = 'PhpList\\Core\\Domain\\' . $dir . '\\Model';

            $attributeDriverDef = new Definition(AttributeDriver::class, [[$modelPath]]);
            $attributeDriverId = 'doctrine.orm.driver.' . $dir;

            $container->setDefinition($attributeDriverId, $attributeDriverDef);

            $driverDefinition->addMethodCall('addDriver', [
                new Reference($attributeDriverId),
                $namespace,
            ]);
        }
    }
}

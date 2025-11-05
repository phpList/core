<?php

declare(strict_types=1);

namespace PhpList\Core\Core;

use PhpList\Core\Bounce\Service\BounceProcessingServiceInterface;
use PhpList\Core\Bounce\Service\NativeBounceProcessingService;
use PhpList\Core\Bounce\Service\WebklexBounceProcessingService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BounceProcessorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $native = NativeBounceProcessingService::class;
        $webklex = WebklexBounceProcessingService::class;

        if (!$container->hasDefinition($native) || !$container->hasDefinition($webklex)) {
            return;
        }

        $aliasTo = extension_loaded('imap') ? $native : $webklex;

        $container->setAlias(BounceProcessingServiceInterface::class, $aliasTo)->setPublic(false);
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

interface SupportingPlaceholderResolverInterface
{
    public function supports(string $key, PlaceholderContext $ctx): bool;
    public function resolve(string $key, PlaceholderContext $ctx): ?string;
}

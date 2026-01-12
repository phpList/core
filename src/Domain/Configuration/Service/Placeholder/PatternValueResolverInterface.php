<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

interface PatternValueResolverInterface
{
    public function pattern(): string;
    public function __invoke(PlaceholderContext $ctx, array $matches): string;
}

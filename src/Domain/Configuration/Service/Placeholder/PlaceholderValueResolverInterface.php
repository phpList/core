<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

interface PlaceholderValueResolverInterface
{
    public function name(): string;
    public function __invoke(PlaceholderContext $ctx): string;
}

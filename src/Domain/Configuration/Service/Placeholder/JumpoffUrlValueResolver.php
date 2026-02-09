<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Placeholder;

class JumpoffUrlValueResolver extends JumpoffValueResolver implements PlaceholderValueResolverInterface
{
    public function name(): string
    {
        return 'JUMPOFFURL';
    }
}

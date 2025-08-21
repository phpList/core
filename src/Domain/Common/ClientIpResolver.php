<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use Symfony\Component\HttpFoundation\RequestStack;

class ClientIpResolver
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function resolve(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            return $request->getClientIp() ?? '';
        }

        return (gethostname() ?: 'localhost') . ':' . getmypid();
    }
}

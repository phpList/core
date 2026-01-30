<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpReceivedStampBuilder
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire('%app.rest_api_domain%')] private readonly string $hostname,
    ) {
    }

    public function buildStamp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $ipAddress = $request->getClientIp();
        if (!$ipAddress) {
            return null;
        }

        $remoteHost = $request->server->get('REMOTE_HOST');
        $ipDomain = $remoteHost ?: $this->getHostByAddr($ipAddress);

        if ($ipDomain && $ipDomain !== $ipAddress) {
            $from = sprintf('%s [%s]', $ipDomain, $ipAddress);
        } else {
            $from = sprintf('[%s]', $ipAddress);
        }

        $requestTime = $request->server->get('REQUEST_TIME') ?? time();
        $date = (new DateTimeImmutable('@' . $requestTime))->format(\DATE_RFC2822);

        return sprintf('from %s by %s with HTTP; %s', $from, $this->hostname, $date);
    }

    private function getHostByAddr(string $ipAddress): ?string
    {
        $previousHandler = set_error_handler(static fn(): bool => true);

        try {
            $host = gethostbyaddr($ipAddress);
        } finally {
            if ($previousHandler !== null) {
                set_error_handler($previousHandler);
            } else {
                restore_error_handler();
            }
        }

        if ($host === false || $host === $ipAddress) {
            return null;
        }

        return $host;
    }
}

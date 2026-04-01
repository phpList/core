<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

class LegacyUrlBuilder
{
    public function withUid(string $baseUrl, string $uid): string
    {
        return $this->withQueryParam($baseUrl, 'uid', $uid);
    }

    public function withEmail(string $baseUrl, string $email): string
    {
        return $this->withQueryParam($baseUrl, 'email', $email);
    }

    private function withQueryParam(string $baseUrl, string $paramName, string $paramValue): string
    {
        $parts = parse_url($baseUrl) ?: [];
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query[$paramName] = $paramValue;

        $parts['query'] = http_build_query($query);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryStr = $parts['query'] ? '?' . $parts['query'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . $queryStr . $frag;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

class LegacyUrlBuilder
{
    public function withUid(string $baseUrl, string $uid): string
    {
        $parts = parse_url($baseUrl) ?: [];
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['uid'] = $uid;

        $parts['query'] = http_build_query($query);

        // rebuild url
        $scheme   = $parts['scheme'] ?? 'https';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $queryStr = $parts['query'] ? '?'.$parts['query'] : '';
        $frag     = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$queryStr}{$frag}";
    }
}

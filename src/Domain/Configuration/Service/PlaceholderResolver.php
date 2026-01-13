<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;

class PlaceholderResolver
{
    /** @var array<string, callable> */
    private array $resolvers = [];

    /** @var array<int, array{pattern: string, resolver: callable}> */
    private array $patternResolvers = [];

    public function register(string $name, callable $resolver): void
    {
        $name = $this->normalizePlaceholderKey($name);
        $this->resolvers[strtoupper($name)] = $resolver;
    }

    public function registerPattern(string $pattern, callable $resolver): void
    {
        $this->patternResolvers[] = ['pattern' => $pattern, 'resolver' => $resolver];
    }

    public function resolve(string $value, PlaceholderContext $context): string
    {
        if (!str_contains($value, '[')) {
            return $value;
        }

        foreach ($this->patternResolvers as $r) {
            $value = preg_replace_callback(
                $r['pattern'],
                fn(array $m) => (string) ($r['resolver'])($context, $m),
                $value
            );
        }

        return preg_replace_callback(
            '/\[([^\]%%]+)(?:%%([^\]]+))?\]/i',
            function (array $matches) use ($context) {
                $rawKey = $matches[1];
                $default = $matches[2] ?? null;

                $key = $this->normalizePlaceholderKey($rawKey);

                $resolver = $this->resolvers[$key]
                    ?? $this->resolvers[strtoupper($key)]
                    ?? $this->resolvers[strtolower($key)]
                    ?? null;

                $resolved = (string) $resolver($context);

                if ($default !== null && $resolved === '') {
                    return $default;
                }

                return $resolved;
            },
            $value
        );
    }

    private function normalizePlaceholderKey(string $rawKey): string
    {
        $key = trim($rawKey);
        $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $key = str_ireplace("\xC2\xA0", ' ', $key);
        $key = str_ireplace('&nbsp;', ' ', $key);

        return preg_replace('/\s+/u', ' ', $key) ?? $key;
    }
}

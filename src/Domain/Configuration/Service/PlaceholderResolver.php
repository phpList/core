<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SupportingPlaceholderResolverInterface;

class PlaceholderResolver
{
    /** @var array<string, callable> */
    private array $resolvers = [];

    /** @var array<int, array{pattern: string, resolver: callable}> */
    private array $patternResolvers = [];

    /** @var SupportingPlaceholderResolverInterface[] */
    private array $supportingResolvers = [];

    public function register(string $name, callable $resolver): void
    {
        $name = $this->normalizePlaceholderKey($name);
        $this->resolvers[strtoupper($name)] = $resolver;
    }

    public function registerPattern(string $pattern, callable $resolver): void
    {
        $this->patternResolvers[] = ['pattern' => $pattern, 'resolver' => $resolver];
    }

    public function registerSupporting(SupportingPlaceholderResolverInterface $resolver): void
    {
        $this->supportingResolvers[] = $resolver;
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

                $keyNormalized = $this->normalizePlaceholderKey($rawKey);
                $canon = strtoupper($this->normalizePlaceholderKey($rawKey));

                // 1) Exact resolver (system placeholders)
                if (isset($this->resolvers[$canon])) {
                    $resolved = (string) ($this->resolvers[$canon])($context);

                    if ($default !== null && $resolved === '') {
                        return $default;
                    }
                    return $resolved;
                }

                // 2) Supporting resolvers (userdata, attributes, etc.)
                foreach ($this->supportingResolvers as $resolver) {
                    if (!$resolver->supports($keyNormalized, $context) && !$resolver->supports($canon, $context)) {
                        continue;
                    }

                    $resolved = $resolver->resolve($keyNormalized, $context);
                    $resolved = $resolved ?? '';

                    if ($default !== null && $resolved === '') {
                        return $default;
                    }
                    return $resolved;
                }

                // 3) if there is a %%default, use it; otherwise keep placeholder unchanged
                return $default ?? $matches[0];            },
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

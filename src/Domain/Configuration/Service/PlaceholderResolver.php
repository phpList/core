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
        $this->resolvers[$name] = $resolver;
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
            '/\[([A-Z0-9_]+)\]/',
            function (array $m) use ($context) {
                $key = $m[1];

                if (!isset($this->resolvers[$key])) {
                    return $m[0];
                }

                return (string) ($this->resolvers[$key])($context);
            },
            $value
        );
    }
}

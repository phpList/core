<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service;

class PlaceholderResolver
{
    /** @var array<string, callable():string> */
    private array $providers = [];

    public function register(string $token, callable $provider): void
    {
        // tokens like [UNSUBSCRIBEURL] (case-insensitive)
        $this->providers[strtoupper($token)] = $provider;
    }

    public function resolve(?string $input): ?string
    {
        if ($input === null || $input === '') return $input;

        // Replace [TOKEN] (case-insensitive)
        return preg_replace_callback('/\[(\w+)\]/i', function ($map) {
            $key = strtoupper($map[1]);
            if (!isset($this->providers[$key])) {
                return $map[0];
            }
            return (string) ($this->providers[$key])();
        }, $input);
    }
}

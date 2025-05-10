<?php

declare(strict_types=1);

namespace PhpList\Core\Core;

class ConfigProvider
{
    public function __construct(private readonly array $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->config;
    }
}


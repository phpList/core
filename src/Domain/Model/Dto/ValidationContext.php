<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Dto;

class ValidationContext
{
    private array $options = [];

    public function set(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}

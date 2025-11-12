<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model;

use InvalidArgumentException;
use UnexpectedValueException;

class Privileges
{
    /**
     * @var array<string, bool>
     */
    private array $flags = [];

    public function __construct(?array $flags = [])
    {
        foreach (PrivilegeFlag::cases() as $flag) {
            $key = $flag->value;
            $this->flags[$key] = $flags[$key] ?? false;
        }
    }

    public static function fromSerialized(?string $serialized): self
    {
        if (!$serialized) {
            return new self();
        }

        set_error_handler(function () {
            throw new InvalidArgumentException('Invalid serialized privileges string.');
        });

        try {
            $data = unserialize($serialized);
        } finally {
            restore_error_handler();
        }

        if (!is_array($data)) {
            throw new UnexpectedValueException('Privileges must deserialize to an array.');
        }

        return new self($data);
    }

    public function toSerialized(): string
    {
        return serialize($this->flags);
    }

    public function has(PrivilegeFlag $flag): bool
    {
        return $this->flags[$flag->value] ?? false;
    }

    public function grant(PrivilegeFlag $flag): self
    {
        $clone = clone $this;
        $clone->flags[$flag->value] = true;
        return $clone;
    }

    public function revoke(PrivilegeFlag $flag): self
    {
        $clone = clone $this;
        $clone->flags[$flag->value] = false;
        return $clone;
    }

    public function all(): array
    {
        return $this->flags;
    }
}

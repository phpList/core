<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

class ChangeSetDto
{
    public const IGNORED_ATTRIBUTES = ['password', 'modified'];

    /**
     * @var array<string, array{0: mixed, 1: mixed}>
     *
     * Example:
     * [
     *     'email' => [null, 'newemail@example.com'],
     *     'isActive' => [true, false]
     * ]
     */
    private array $changes = [];

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changes
     */
    public function __construct(array $changes = [])
    {
        $this->changes = $changes;
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function hasChanges(): bool
    {
        return !empty($this->changes);
    }

    public function hasField(string $field): bool
    {
        return array_key_exists($field, $this->changes);
    }

    /**
     * @return array{0: mixed, 1: mixed}|null
     */
    public function getFieldChange(string $field): ?array
    {
        return $this->changes[$field] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function getOldValue(string $field): mixed
    {
        return $this->changes[$field][0] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function getNewValue(string $field): mixed
    {
        return $this->changes[$field][1] ?? null;
    }

    public function toArray(): array
    {
        return $this->changes;
    }

    public static function fromDoctrineChangeSet(array $changeSet): self
    {
        return new self($changeSet);
    }
}

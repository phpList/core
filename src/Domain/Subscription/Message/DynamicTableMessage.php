<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Message;

class DynamicTableMessage
{
    private string $tableName;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}

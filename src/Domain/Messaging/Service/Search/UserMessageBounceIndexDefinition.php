<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Search;

use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;

class UserMessageBounceIndexDefinition implements SearchIndexDefinitionInterface
{
    public function getIndexAlias(): string
    {
        return UserMessageBounce::SEARCH_INDEX_NAME;
    }

    public function getMapping(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'keyword'],
                // Numeric mirror of `id`, used for range/sort in cursor pagination - `id` stays a
                // keyword for exact-match filtering.
                'idSort' => ['type' => 'long'],
                'userId' => ['type' => 'keyword'],
                'messageId' => ['type' => 'keyword'],
                'bounceId' => ['type' => 'keyword'],
                'time' => ['type' => 'date'],
            ],
        ];
    }

    public function getSettings(): array
    {
        return [];
    }
}

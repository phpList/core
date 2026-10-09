<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Search;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;

class SubscriberHistoryIndexDefinition implements SearchIndexDefinitionInterface
{
    public function getIndexAlias(): string
    {
        return SubscriberHistory::SEARCH_INDEX_NAME;
    }

    public function getMapping(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'keyword'],
                // Numeric mirror of `id`, used for range/sort in cursor pagination - `id` stays a
                // keyword for exact-match filtering.
                'idSort' => ['type' => 'long'],
                'subscriberId' => ['type' => 'keyword'],
                'ip' => ['type' => 'keyword'],
                'date' => ['type' => 'date'],
                'summary' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword', 'ignore_above' => 256],
                    ],
                ],
                'detail' => ['type' => 'text'],
                'systemInfo' => ['type' => 'text'],
            ],
        ];
    }

    public function getSettings(): array
    {
        return [];
    }
}

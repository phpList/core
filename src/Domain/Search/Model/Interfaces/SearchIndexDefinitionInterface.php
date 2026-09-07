<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Model\Interfaces;

/**
 * Describes one Elasticsearch index: its logical alias (matches SearchIndexableInterface::getSearchIndexName())
 * and the mapping/settings used to create or update it. Implementations are auto-tagged via
 * config/services/elasticsearch.yml and picked up by the `phplist:search:init-indices` command.
 */
interface SearchIndexDefinitionInterface
{
    public function getIndexAlias(): string;

    /** @return array<string, mixed> */
    public function getMapping(): array;

    /** @return array<string, mixed> */
    public function getSettings(): array;
}

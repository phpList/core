<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Registry;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexDefinitionInterface;

class SearchIndexDefinitionRegistry
{
    /** @var SearchIndexDefinitionInterface[] */
    private readonly array $definitions;

    /** @param iterable<SearchIndexDefinitionInterface> $definitions */
    public function __construct(iterable $definitions)
    {
        $this->definitions = $definitions instanceof \Traversable ? iterator_to_array($definitions) : $definitions;
    }

    /** @return SearchIndexDefinitionInterface[] */
    public function getAll(): array
    {
        return $this->definitions;
    }

    public function find(string $alias): ?SearchIndexDefinitionInterface
    {
        foreach ($this->definitions as $definition) {
            if ($definition->getIndexAlias() === $alias) {
                return $definition;
            }
        }

        return null;
    }
}

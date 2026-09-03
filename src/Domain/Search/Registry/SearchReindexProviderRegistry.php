<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Registry;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchReindexProviderInterface;

class SearchReindexProviderRegistry
{
    /** @var SearchReindexProviderInterface[] */
    private readonly array $providers;

    /** @param iterable<SearchReindexProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers instanceof \Traversable ? iterator_to_array($providers) : $providers;
    }

    /** @return SearchReindexProviderInterface[] */
    public function getAll(): array
    {
        return $this->providers;
    }

    public function find(string $alias): ?SearchReindexProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getAlias() === $alias) {
                return $provider;
            }
        }

        return null;
    }
}

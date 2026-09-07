<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Registry;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchPurgeProviderInterface;
use Traversable;

class SearchPurgeProviderRegistry
{
    /** @var SearchPurgeProviderInterface[] */
    private readonly array $providers;

    /** @param iterable<SearchPurgeProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers instanceof Traversable ? iterator_to_array($providers) : $providers;
    }

    /** @return SearchPurgeProviderInterface[] */
    public function getAll(): array
    {
        return $this->providers;
    }

    public function find(string $alias): ?SearchPurgeProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getAlias() === $alias) {
                return $provider;
            }
        }

        return null;
    }
}

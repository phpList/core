<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Provider;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use Psr\SimpleCache\CacheInterface;

class ConfigProvider
{
    private array $booleanValues = [
        ConfigOption::MaintenanceMode,
    ];

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 300
    ) {
    }

    public function isEnabled(ConfigOption $key): bool
    {
        if (!in_array($key, $this->booleanValues)) {
            throw new InvalidArgumentException('Invalid boolean value key');
        }
        $config = $this->configRepository->findOneBy(['item' => $key->value]);

        return $config?->getValue() === '1';
    }

    /**
     * Get configuration value by its key
     */
    public function getValue(ConfigOption $key, ?string $default = null): ?string
    {
        if (in_array($key, $this->booleanValues)) {
            throw new InvalidArgumentException('Key is a boolean value, use isEnabled instead');
        }
        $cacheKey = 'cfg:' . $key->value;
        $value = $this->cache->get($cacheKey);
        if ($value === null) {
            $value = $this->configRepository->findValueByItem($key->value);
            $this->cache->set($cacheKey, $value, $this->ttlSeconds);
        }

        return $value ?? $default;
    }

    public function getValueWithNamespace(ConfigOption $key, ?string $default = null): ?string
    {
        $full = $this->getValue($key);
        if ($full !== null && $full !== '') {
            return $full;
        }

        if (str_contains($key->value, ':')) {
            [$parent] = explode(':', $key->value, 2);
            $parentKey = ConfigOption::from($parent);

            return $this->getValue($parentKey, $default);
        }

        return $default;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Provider;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use Psr\SimpleCache\CacheInterface;
use ValueError;

class ConfigProvider
{
    private array $booleanValues = [
        ConfigOption::MaintenanceMode,
        ConfigOption::SendAdminCopies,
    ];

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly CacheInterface $cache,
        private readonly DefaultConfigProvider $defaultConfigs,
        private readonly ?int $ttlSeconds = 300
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isEnabled(ConfigOption $key): bool
    {
        if (!in_array($key, $this->booleanValues, true)) {
            throw new InvalidArgumentException('Invalid boolean value key');
        }
        $config = $this->configRepository->findOneBy(['item' => $key->value]);

        if ($config !== null) {
            return filter_var($config->getValue(), FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->defaultConfigs->has($key)) {
            return filter_var(
                $this->defaultConfigs->get($key)['value'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        return false;
    }

    /**
     * Get configuration value by its key, from settings or default configs or default value (if provided)
     * @throws InvalidArgumentException
     */
    public function getValue(ConfigOption $key): ?string
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

        if ($value !== null) {
            return $value;
        }

        return $this->defaultConfigs->has($key) ? (string) $this->defaultConfigs->get($key)['value'] : null;
    }

    public function getValueWithNamespace(ConfigOption|string $key): ?string
    {
        if ($key instanceof ConfigOption) {
            $full = $this->getValue($key);
            $keyValue = $key->value;
        } else {
            $keyValue = $key;
            $cacheKey = 'cfg:' . $keyValue;
            $full = $this->cache->get($cacheKey);
            if ($full === null) {
                $full = $this->configRepository->findValueByItem($keyValue);
                $this->cache->set($cacheKey, $full, $this->ttlSeconds);
            }
        }

        if ($full !== null && $full !== '') {
            return $full;
        }

        if (str_contains($keyValue, ':')) {
            [$parent] = explode(':', $keyValue, 2);
            try {
                $parentKey = ConfigOption::from($parent);
            } catch (ValueError) {
                return null;
            }

            return $this->getValue($parentKey);
        }

        return null;
    }
}

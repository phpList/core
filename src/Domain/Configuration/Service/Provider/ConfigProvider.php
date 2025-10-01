<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Provider;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;

class ConfigProvider
{
    private array $booleanValues = [
        ConfigOption::MaintenanceMode,
    ];

    private ConfigRepository $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
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
    public function getValue(string $ikey, ?string $default = null): ?string
    {
        $config = $this->configRepository->findOneBy(['item' => $ikey]);

        return $config?->getValue() ?? $default;
    }

}

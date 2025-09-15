<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Manager;

use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Configuration\Exception\ConfigNotEditableException;

class ConfigManager
{
    private ConfigRepository $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    public function inMaintenanceMode(): bool
    {
        $config = $this->getByItem('maintenancemode');
        return $config?->getValue() === '1';
    }

    /**
     * Get a configuration item by its key
     */
    public function getByItem(string $item): ?Config
    {
        return $this->configRepository->findOneBy(['item' => $item]);
    }

    /**
     * Get all configuration items
     *
     * @return Config[]
     */
    public function getAll(): array
    {
        return $this->configRepository->findAll();
    }

    /**
     * Update a configuration item
     * @throws ConfigNotEditableException
     */
    public function update(Config $config, string $value): void
    {
        if (!$config->isEditable()) {
            throw new ConfigNotEditableException($config->getKey());
        }
        $config->setValue($value);

        $this->configRepository->save($config);
    }

    public function create(string $key, string $value, bool $editable, ?string $type = null): void
    {
        $config = (new Config())
            ->setKey($key)
            ->setValue($value)
            ->setEditable($editable)
            ->setType($type);

        $this->configRepository->save($config);
    }

    public function delete(Config $config): void
    {
        $this->configRepository->remove($config);
    }
}

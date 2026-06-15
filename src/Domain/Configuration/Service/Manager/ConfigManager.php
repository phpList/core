<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Manager;

use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Model\Dto\CreateConfigDto;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Configuration\Exception\ConfigNotEditableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConfigManager
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        #[Autowire('%parallel_use_with_phplist3%')]
        private readonly bool $parallelUseWithPhpList3,
    ) {
    }

    /**
     * Get a configuration item by its key
     */
    public function findByKey(string $item): ?Config
    {
        return $this->configRepository->findByKey($item);
    }

    /**
     * Get all configuration items
     *
     * @return Config[]
     */
    public function getAllEditable(): array
    {
        $all = $this->configRepository->findAll();

        if ($this->parallelUseWithPhpList3) {
            $filtered = [];
            foreach ($all as $config) {
                $key = trim($config->getKey());
                if ($key === '' || $config->isEditable() === false) {
                    continue;
                }
                // filter legacy config items (WithNamespaces)
                if (str_contains($key, ':') === true) {
                    continue;
                }
                if (str_starts_with($key, 'lastlanguageupdate-') === true) {
                    continue;
                }
                $filtered[] = $config;
            }
            $all = $filtered;
        }

        return $all;
    }

    /**
     * Update a configuration item
     * @throws ConfigNotEditableException
     */
    public function update(Config $config, string $value): Config
    {
        if (!$config->isEditable()) {
            throw new ConfigNotEditableException($config->getKey());
        }
        $config->setValue($value);

        return $config;
    }

    public function create(CreateConfigDto $configRequestDto): Config
    {
        $config = (new Config())
            ->setKey($configRequestDto->key)
            ->setValue($configRequestDto->value)
            ->setEditable($configRequestDto->editable)
            ->setType($configRequestDto->type);

        $this->configRepository->persist($config);

        return $config;
    }

    public function delete(Config $config): void
    {
        $this->configRepository->remove($config);
    }
}

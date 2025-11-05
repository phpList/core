<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Manager;

use PhpList\Core\Domain\Configuration\Exception\ConfigNotEditableException;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Configuration\Service\Manager\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    public function testGetByItemReturnsConfigFromRepository(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $config = new Config();
        $config->setKey('test_item');
        $config->setValue('test_value');

        $configRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['item' => 'test_item'])
            ->willReturn($config);

        $result = $manager->getByItem('test_item');

        $this->assertSame($config, $result);
        $this->assertSame('test_item', $result->getKey());
        $this->assertSame('test_value', $result->getValue());
    }

    public function testGetAllReturnsAllConfigsFromRepository(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $config1 = new Config();
        $config1->setKey('item1');
        $config1->setValue('value1');

        $config2 = new Config();
        $config2->setKey('item2');
        $config2->setValue('value2');

        $configs = [$config1, $config2];

        $configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($configs);

        $result = $manager->getAll();

        $this->assertSame($configs, $result);
        $this->assertCount(2, $result);
        $this->assertSame('item1', $result[0]->getKey());
        $this->assertSame('value1', $result[0]->getValue());
        $this->assertSame('item2', $result[1]->getKey());
        $this->assertSame('value2', $result[1]->getValue());
    }

    public function testCreateSavesNewConfigToRepository(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $configRepository->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Config $config) {
                return $config->getKey() === 'test_key' &&
                    $config->getValue() === 'test_value' &&
                    $config->isEditable() === true &&
                    $config->getType() === 'test_type';
            }));

        $manager->create('test_key', 'test_value', true, 'test_type');
    }
    public function testGetByItemReturnsNullWhenItemDoesNotExist(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $configRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['item' => 'non_existent_item'])
            ->willReturn(null);

        $result = $manager->getByItem('non_existent_item');

        $this->assertNull($result);
    }

    public function testUpdateThrowsExceptionWhenConfigIsNotEditable(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $config = new Config();
        $config->setKey('test_item');
        $config->setValue('test_value');
        $config->setEditable(false);

        $this->expectException(ConfigNotEditableException::class);
        $this->expectExceptionMessage('Configuration item "test_item" is not editable.');

        $manager->update($config, 'new_value');
    }

    public function testDeleteRemovesConfigFromRepository(): void
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $manager = new ConfigManager($configRepository);

        $config = new Config();
        $config->setKey('test_item');
        $config->setValue('test_value');

        $configRepository->expects($this->once())
            ->method('remove')
            ->with($config);

        $manager->delete($config);
    }
}

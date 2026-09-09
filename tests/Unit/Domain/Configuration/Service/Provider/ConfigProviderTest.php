<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Provider;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\Provider\DefaultConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @covers \PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider
 */
final class ConfigProviderTest extends TestCase
{
    private ConfigRepository&MockObject $repo;
    private CacheInterface&MockObject $cache;
    private DefaultConfigProvider&MockObject $defaults;
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ConfigRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->defaults = $this->createMock(DefaultConfigProvider::class);

        $this->provider = new ConfigProvider(
            configRepository: $this->repo,
            cache: $this->cache,
            defaultConfigs: $this->defaults,
            ttlSeconds: 300
        );
    }

    /**
     * Utility: pick a non-boolean enum case (i.e., anything except MaintenanceMode).
     */
    private function pickNonBooleanCase(): ConfigOption
    {
        foreach (ConfigOption::cases() as $case) {
            if ($case !== ConfigOption::MaintenanceMode) {
                return $case;
            }
        }
        $this->markTestSkipped('No non-boolean ConfigOption cases available to test.');
    }

    public function testIsEnabledRejectsNonBooleanKeys(): void
    {
        $nonBoolean = $this->pickNonBooleanCase();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid boolean value key');

        $this->provider->isEnabled($nonBoolean);
    }

    public function testIsEnabledUsesRepositoryValueWhenPresent(): void
    {
        $key = ConfigOption::MaintenanceMode;

        $configEntity = $this->createMock(Config::class);
        $configEntity->method('getValue')->willReturn('1');

        $this->repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['key' => $key->value])
            ->willReturn($configEntity);

        // Defaults should not be consulted if repo has value
        $this->defaults->expects($this->never())->method('has');
        $this->defaults->expects($this->never())->method('get');

        $enabled = $this->provider->isEnabled($key);

        $this->assertTrue($enabled, 'When repo has value "1", isEnabled() should return true.');
    }

    public function testIsEnabledFallsBackToDefaultsWhenRepoMissing(): void
    {
        $key = ConfigOption::MaintenanceMode;

        $this->repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['key' => $key->value])
            ->willReturn(null);

        $this->defaults
            ->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(true);

        $this->defaults
            ->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(['value' => '1']);

        $this->assertTrue($this->provider->isEnabled($key));
    }

    public function testGetValueRejectsBooleanKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key is a boolean value, use isEnabled instead');

        $this->provider->getValue(ConfigOption::MaintenanceMode);
    }

    public function testGetValueReturnsFromCacheWhenPresent(): void
    {
        $key = $this->pickNonBooleanCase();
        $cacheKey = 'cfg:' . $key->value;

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn('CACHED');

        $this->repo->expects($this->never())->method('findValueByItem');
        $this->defaults->expects($this->never())->method('has');
        $this->defaults->expects($this->never())->method('get');

        $this->assertSame('CACHED', $this->provider->getValue($key));
    }

    public function testGetValueLoadsFromRepoAndCachesWhenCacheMiss(): void
    {
        $key = $this->pickNonBooleanCase();
        $cacheKey = 'cfg:' . $key->value;

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn(null);

        $this->repo
            ->expects($this->once())
            ->method('findValueByItem')
            ->with($key->value)
            ->willReturn('DBVAL');

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with($cacheKey, 'DBVAL', 300);

        $this->defaults->expects($this->never())->method('has');
        $this->defaults->expects($this->never())->method('get');

        $this->assertSame('DBVAL', $this->provider->getValue($key));
    }

    public function testGetValueFallsBackToDefaultConfigsWhenNoCacheAndNoRepo(): void
    {
        $key = $this->pickNonBooleanCase();
        $cacheKey = 'cfg:' . $key->value;

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn(null);

        $this->repo
            ->expects($this->once())
            ->method('findValueByItem')
            ->with($key->value)
            ->willReturn(null);

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with($cacheKey, null, 300);

        $this->defaults
            ->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(true);

        $this->defaults
            ->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(['value' => 'DEF']);

        $this->assertSame('DEF', $this->provider->getValue($key));
    }

    public function testGetValueReturnsNullWhenNoCacheNoRepoNoDefault(): void
    {
        $key = $this->pickNonBooleanCase();
        $cacheKey = 'cfg:' . $key->value;

        $this->cache->expects($this->once())->method('get')->with($cacheKey)->willReturn(null);
        $this->repo->expects($this->once())->method('findValueByItem')->with($key->value)->willReturn(null);
        $this->cache->expects($this->once())->method('set')->with($cacheKey, null, 300);

        $this->defaults->expects($this->once())->method('has')->with($key)->willReturn(false);
        $this->defaults->expects($this->never())->method('get');

        $this->assertNull($this->provider->getValue($key));
    }

    public function testGetValueWithNamespacePrefersFullValue(): void
    {
        $key = $this->pickNonBooleanCase();

        // Force getValue($key) to return a non-empty string
        $this->cache->method('get')->willReturn('FULL');
        $this->repo->expects($this->never())->method('findValueByItem');

        $this->assertSame('FULL', $this->provider->getValueWithNamespace($key));
    }

    public function testGetValueWithNamespaceSupportsStringNamespacedKey(): void
    {
        $this->cache
            ->method('get')
            ->willReturnMap([
                ['cfg:subscribemessage:1', null],
                ['cfg:subscribemessage', 'PARENT'],
            ]);

        $this->repo
            ->method('findValueByItem')
            ->willReturnMap([
                ['subscribemessage:1', null],
                ['subscribemessage', 'PARENT'],
            ]);

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('set')
            ->withAnyParameters();

        $this->assertSame('PARENT', $this->provider->getValueWithNamespace('subscribemessage:1'));
    }
}

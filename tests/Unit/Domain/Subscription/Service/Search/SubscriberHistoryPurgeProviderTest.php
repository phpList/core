<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Search;

use DateInterval;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Service\Search\SubscriberHistoryPurgeProvider;
use PHPUnit\Framework\TestCase;

class SubscriberHistoryPurgeProviderTest extends TestCase
{
    public function testAliasMatchesEntitySearchIndexName(): void
    {
        $provider = new SubscriberHistoryPurgeProvider(
            $this->createMock(SubscriberHistoryRepository::class),
            'phplist_',
            'P1M',
        );

        $this->assertSame('subscriber_history', $provider->getAlias());
    }

    public function testGetRetentionPeriodReturnsNullWhenNotConfigured(): void
    {
        $provider = new SubscriberHistoryPurgeProvider(
            $this->createMock(SubscriberHistoryRepository::class),
            'phplist_',
            '',
        );

        $this->assertNull($provider->getRetentionPeriod());
    }

    public function testGetRetentionPeriodParsesConfiguredIsoDuration(): void
    {
        $provider = new SubscriberHistoryPurgeProvider(
            $this->createMock(SubscriberHistoryRepository::class),
            'phplist_',
            'P1M',
        );

        $this->assertEquals(new DateInterval('P1M'), $provider->getRetentionPeriod());
    }

    public function testGetSearchIndexNameIncludesPrefix(): void
    {
        $provider = new SubscriberHistoryPurgeProvider(
            $this->createMock(SubscriberHistoryRepository::class),
            'phplist_',
            'P1M',
        );

        $this->assertSame('phplist_subscriber_history', $provider->getSearchIndexName());
    }
}

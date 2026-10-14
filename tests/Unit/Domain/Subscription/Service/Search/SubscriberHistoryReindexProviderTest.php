<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Search;

use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Service\Search\SubscriberHistoryReindexProvider;
use PHPUnit\Framework\TestCase;

class SubscriberHistoryReindexProviderTest extends TestCase
{
    public function testAliasMatchesEntitySearchIndexName(): void
    {
        $provider = new SubscriberHistoryReindexProvider($this->createMock(SubscriberHistoryRepository::class));

        $this->assertSame('subscriber_history', $provider->getAlias());
    }
}

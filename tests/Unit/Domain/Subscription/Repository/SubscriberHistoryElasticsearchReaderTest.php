<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryElasticsearchReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberHistoryElasticsearchReaderTest extends TestCase
{
    private ElasticsearchClientInterface&MockObject $client;
    private SubscriberHistoryElasticsearchReader $reader;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->reader = new SubscriberHistoryElasticsearchReader($this->client, 'phplist_');
    }

    public function testGetFilteredAfterIdQueriesPrefixedIndexAndHydratesResults(): void
    {
        $filter = new SubscriberHistoryFilter(lastId: 5, limit: 10);

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_subscriber_history',
                $this->callback(function (array $query): bool {
                    return $query['size'] === 10
                        && $query['query']['bool']['filter'][0] === ['range' => ['idSort' => ['gt' => 5]]];
                }),
            )
            ->willReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [
                        ['_source' => [
                            'id' => 7,
                            'subscriberId' => 3,
                            'ip' => '127.0.0.1',
                            'date' => '2026-01-01T00:00:00+00:00',
                            'summary' => 'Updated',
                            'detail' => 'Detail',
                            'systemInfo' => 'Info',
                        ]],
                    ],
                ],
            ]);

        $result = $this->reader->getFilteredAfterId($filter);

        $this->assertSame(1, $result->getTotal());
        $this->assertCount(1, $result->getItems());
        $this->assertSame(7, $result->getItems()[0]->getId());
        $this->assertSame(3, $result->getItems()[0]->getSubscriberId());
        $this->assertSame('Updated', $result->getItems()[0]->getSummary());
    }

    public function testGetFilteredAfterIdRejectsWrongFilterType(): void
    {
        $wrongFilter = $this->createMock(FilterRequestInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->reader->getFilteredAfterId($wrongFilter);
    }

    public function testGetBySubscriberSortsDescending(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getId')->willReturn(9);

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_subscriber_history',
                $this->callback(function (array $query): bool {
                    return $query['query'] === ['term' => ['subscriberId' => 9]]
                        && $query['sort'] === [['idSort' => 'desc']];
                }),
            )
            ->willReturn(['hits' => ['hits' => []]]);

        $result = $this->reader->getBySubscriber($subscriber);

        $this->assertSame([], $result);
    }
}

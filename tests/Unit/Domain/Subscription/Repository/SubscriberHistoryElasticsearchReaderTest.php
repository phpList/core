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
                            'idSort' => 7,
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

    public function testGetFilteredAfterIdPaginatesAcrossTwoPagesWithoutRepeatingResults(): void
    {
        $firstFilter = new SubscriberHistoryFilter(lastId: 0, limit: 1);

        $this->client
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                [
                    'hits' => [
                        'total' => ['value' => 2],
                        'hits' => [
                            ['_source' => [
                                'id' => 5,
                                'idSort' => 5,
                                'subscriberId' => 1,
                                'ip' => '127.0.0.1',
                                'date' => '2026-01-01T00:00:00+00:00',
                                'summary' => 'First',
                                'detail' => 'Detail',
                                'systemInfo' => 'Info',
                            ]],
                        ],
                    ],
                ],
                [
                    'hits' => [
                        'total' => ['value' => 2],
                        'hits' => [
                            ['_source' => [
                                'id' => 8,
                                'idSort' => 8,
                                'subscriberId' => 2,
                                'ip' => '127.0.0.1',
                                'date' => '2026-01-02T00:00:00+00:00',
                                'summary' => 'Second',
                                'detail' => 'Detail',
                                'systemInfo' => 'Info',
                            ]],
                        ],
                    ],
                ],
            );

        $firstPage = $this->reader->getFilteredAfterId($firstFilter);

        $this->assertSame(5, $firstPage->getLastId());
        $this->assertSame(5, $firstPage->getItems()[0]->getId());

        $secondFilter = new SubscriberHistoryFilter(lastId: $firstPage->getLastId(), limit: 1);
        $secondPage = $this->reader->getFilteredAfterId($secondFilter);

        $this->assertSame(8, $secondPage->getLastId());
        $this->assertSame(8, $secondPage->getItems()[0]->getId());
        $this->assertNotSame(
            $firstPage->getItems()[0]->getId(),
            $secondPage->getItems()[0]->getId(),
        );
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

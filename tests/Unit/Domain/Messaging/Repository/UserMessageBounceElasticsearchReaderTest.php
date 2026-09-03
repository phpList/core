<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Messaging\Model\Filter\UserMessageBounceFilter;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceElasticsearchReader;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserMessageBounceElasticsearchReaderTest extends TestCase
{
    private ElasticsearchClientInterface&MockObject $client;
    private UserMessageBounceElasticsearchReader $reader;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->reader = new UserMessageBounceElasticsearchReader($this->client, 'phplist_');
    }

    public function testGetFilteredAfterIdQueriesPrefixedIndexAndHydratesResults(): void
    {
        $filter = new UserMessageBounceFilter(lastId: 5, limit: 10);

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
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
                            'userId' => 3,
                            'messageId' => 42,
                            'bounceId' => 99,
                            'time' => '2026-01-01T00:00:00+00:00',
                        ]],
                    ],
                ],
            ]);

        $result = $this->reader->getFilteredAfterId($filter);

        $this->assertSame(1, $result->getTotal());
        $this->assertCount(1, $result->getItems());
        $this->assertSame(7, $result->getItems()[0]->getId());
        $this->assertSame(3, $result->getItems()[0]->getUserId());
        $this->assertSame(42, $result->getItems()[0]->getMessageId());
        $this->assertSame(99, $result->getItems()[0]->getBounceId());
    }

    public function testGetFilteredAfterIdPaginatesAcrossTwoPagesWithoutRepeatingResults(): void
    {
        $firstFilter = new UserMessageBounceFilter(lastId: 0, limit: 1);

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
                                'userId' => 1,
                                'messageId' => 10,
                                'bounceId' => 20,
                                'time' => '2026-01-01T00:00:00+00:00',
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
                                'userId' => 2,
                                'messageId' => 11,
                                'bounceId' => 21,
                                'time' => '2026-01-02T00:00:00+00:00',
                            ]],
                        ],
                    ],
                ],
            );

        $firstPage = $this->reader->getFilteredAfterId($firstFilter);

        $this->assertSame(5, $firstPage->getLastId());
        $this->assertSame(5, $firstPage->getItems()[0]->getId());

        $secondFilter = new UserMessageBounceFilter(lastId: $firstPage->getLastId(), limit: 1);
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

    public function testGetByUserIdSortsDescending(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
                $this->callback(function (array $query): bool {
                    return $query['query'] === ['term' => ['userId' => 9]]
                        && $query['sort'] === [['idSort' => 'desc']];
                }),
            )
            ->willReturn(['hits' => ['hits' => []]]);

        $result = $this->reader->getByUserId(9);

        $this->assertSame([], $result);
    }
}

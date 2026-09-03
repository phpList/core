<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryConfigurableReader;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryElasticsearchReader;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberHistoryConfigurableReaderTest extends TestCase
{
    private SubscriberHistoryRepository&MockObject $databaseReader;
    private SubscriberHistoryElasticsearchReader&MockObject $elasticsearchReader;

    protected function setUp(): void
    {
        $this->databaseReader = $this->createMock(SubscriberHistoryRepository::class);
        $this->elasticsearchReader = $this->createMock(SubscriberHistoryElasticsearchReader::class);
    }

    public function testDelegatesToElasticsearchWhenEnabled(): void
    {
        $reader = new SubscriberHistoryConfigurableReader(
            $this->databaseReader,
            $this->elasticsearchReader,
            true,
        );
        $filter = $this->createMock(FilterRequestInterface::class);
        $expected = new PaginatedResult([], 0, 50, 0);

        $this->elasticsearchReader->expects($this->once())
            ->method('getFilteredAfterId')
            ->with($filter)
            ->willReturn($expected);
        $this->databaseReader->expects($this->never())->method('getFilteredAfterId');

        $this->assertSame($expected, $reader->getFilteredAfterId($filter));
    }

    public function testDelegatesToDatabaseWhenDisabled(): void
    {
        $reader = new SubscriberHistoryConfigurableReader(
            $this->databaseReader,
            $this->elasticsearchReader,
            false,
        );
        $subscriber = $this->createMock(Subscriber::class);
        $expected = [];

        $this->databaseReader->expects($this->once())
            ->method('getBySubscriber')
            ->with($subscriber)
            ->willReturn($expected);
        $this->elasticsearchReader->expects($this->never())->method('getBySubscriber');

        $this->assertSame($expected, $reader->getBySubscriber($subscriber));
    }
}

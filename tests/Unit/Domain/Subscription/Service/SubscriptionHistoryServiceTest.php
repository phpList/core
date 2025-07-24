<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriptionHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriptionHistoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriptionHistoryServiceTest extends TestCase
{
    private SubscriberHistoryRepository|MockObject $subscriberHistoryRepository;
    private SubscriptionHistoryService $subscriptionHistoryService;

    protected function setUp(): void
    {
        $this->subscriberHistoryRepository = $this->createMock(SubscriberHistoryRepository::class);
        $this->subscriptionHistoryService = new SubscriptionHistoryService(
            repository: $this->subscriberHistoryRepository
        );
    }

    public function testGetHistoryCallsRepositoryWithCorrectParameters(): void
    {
        $lastId = 10;
        $limit = 20;
        $filter = $this->createMock(SubscriptionHistoryFilter::class);
        $expectedResult = [$this->createMock(SubscriberHistory::class)];

        $this->subscriberHistoryRepository
            ->expects($this->once())
            ->method('getFilteredAfterId')
            ->with($lastId, $limit, $filter)
            ->willReturn($expectedResult);

        $result = $this->subscriptionHistoryService->getHistory($lastId, $limit, $filter);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetHistoryReturnsEmptyArrayWhenRepositoryReturnsEmptyArray(): void
    {
        $lastId = 10;
        $limit = 20;
        $filter = $this->createMock(SubscriptionHistoryFilter::class);
        $expectedResult = [];

        $this->subscriberHistoryRepository
            ->expects($this->once())
            ->method('getFilteredAfterId')
            ->with($lastId, $limit, $filter)
            ->willReturn($expectedResult);

        $result = $this->subscriptionHistoryService->getHistory($lastId, $limit, $filter);

        $this->assertSame($expectedResult, $result);
        $this->assertEmpty($result);
    }
}

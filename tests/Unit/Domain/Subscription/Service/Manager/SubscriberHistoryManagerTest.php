<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Common\ClientIpResolver;
use PhpList\Core\Domain\Common\SystemInfoCollector;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberHistoryManagerTest extends TestCase
{
    private SubscriberHistoryRepository|MockObject $subscriberHistoryRepository;
    private SubscriberHistoryManager $subscriptionHistoryService;

    protected function setUp(): void
    {
        $this->subscriberHistoryRepository = $this->createMock(SubscriberHistoryRepository::class);
        $this->subscriptionHistoryService = new SubscriberHistoryManager(
            repository: $this->subscriberHistoryRepository,
            clientIpResolver: $this->createMock(ClientIpResolver::class),
            systemInfoCollector: $this->createMock(SystemInfoCollector::class),
            translator: $this->createMock(TranslatorInterface::class),
        );
    }

    public function testGetHistoryCallsRepositoryWithCorrectParameters(): void
    {
        $lastId = 10;
        $limit = 20;
        $filter = $this->createMock(SubscriberHistoryFilter::class);
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
        $filter = $this->createMock(SubscriberHistoryFilter::class);
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

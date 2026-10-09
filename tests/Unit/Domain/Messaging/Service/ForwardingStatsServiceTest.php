<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\ForwardingStatsService;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ForwardingStatsServiceTest extends TestCase
{
    private SubscriberAttributeValueRepository&MockObject $valueRepo;
    private SubscriberAttributeManager&MockObject $attrManager;

    protected function setUp(): void
    {
        $this->valueRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $this->attrManager = $this->createMock(SubscriberAttributeManager::class);
    }

    public function testNoAttributeConfiguredDoesNothing(): void
    {
        $service = new ForwardingStatsService(
            subscriberAttributeValueRepo: $this->valueRepo,
            subscriberAttributeManager: $this->attrManager,
            // becomes null internally
            forwardFriendCountAttr: ''
        );

        $subscriber = $this->createMock(Subscriber::class);

        // No repository or manager calls expected
        $this->valueRepo->expects(self::never())->method(self::anything());
        $this->attrManager->expects(self::never())->method(self::anything());

        $service->incrementFriendsCount($subscriber);
        $service->updateFriendsCount($subscriber);
    }

    public function testIncrementThenUpdatePersistsAndResets(): void
    {
        $service = new ForwardingStatsService(
            subscriberAttributeValueRepo: $this->valueRepo,
            subscriberAttributeManager: $this->attrManager,
            forwardFriendCountAttr: 'FriendsForwarded'
        );

        $subscriber = $this->createConfiguredMock(Subscriber::class, ['getId' => 123]);

        // Simulate existing attribute value of 3
        $existing = $this->getMockBuilder(SubscriberAttributeValue::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue'])
            ->getMock();
        $existing->method('getValue')->willReturn('3');

        $this->valueRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttributeName')
            ->with(self::identicalTo($subscriber), 'FriendsForwarded')
            ->willReturn($existing);

        // After two increments (3 -> 4 -> 5), update should persist '5'
        $this->attrManager->expects(self::once())
            ->method('createOrUpdateByName')
            ->with(
                self::identicalTo($subscriber),
                'FriendsForwarded',
                '5'
            );

        $service->incrementFriendsCount($subscriber);
        $service->incrementFriendsCount($subscriber);
        $service->updateFriendsCount($subscriber);

        // Second update attempt should be a no-op due to cache reset
        $this->attrManager->expects(self::never())->method('createOrUpdateByName');
        $service->updateFriendsCount($subscriber);
    }

    public function testCacheIsolationBySubscriber(): void
    {
        $service = new ForwardingStatsService(
            subscriberAttributeValueRepo: $this->valueRepo,
            subscriberAttributeManager: $this->attrManager,
            forwardFriendCountAttr: 'FriendsForwarded'
        );

        $subscriberA = $this->createConfiguredMock(Subscriber::class, ['getId' => 1]);
        $subscriberB = $this->createConfiguredMock(Subscriber::class, ['getId' => 2]);

        // Initial load for A returns 0
        $this->valueRepo->expects(self::once())
            ->method('findOneBySubscriberAndAttributeName')
            ->with(self::identicalTo($subscriberA), 'FriendsForwarded')
            ->willReturn(null);
        // cache for A becomes 1
        $service->incrementFriendsCount($subscriberA);

        // Expect exactly one persistence call overall (for A only)
        $this->attrManager->expects(self::once())
            ->method('createOrUpdateByName')
            ->with(
                self::identicalTo($subscriberA),
                'FriendsForwarded',
                '1'
            );
        // Calling update for B must be a no-op (cache belongs to A)
        $service->updateFriendsCount($subscriberB);
        $service->updateFriendsCount($subscriberA);
    }
}

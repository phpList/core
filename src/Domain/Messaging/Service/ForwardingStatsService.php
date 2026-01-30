<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ForwardingStatsService
{
    private readonly ?string $forwardFriendCountAttribute;
    /**
     * Cached friend counts for this request.
     */
    private ?int $friendCount = null;

    public function __construct(
        private readonly SubscriberAttributeValueRepository $subscriberAttributeValueRepo,
        private readonly SubscriberAttributeManager $subscriberAttributeManager,
        #[Autowire('%phplist.forward_friend_count_attribute%')] string $forwardFriendCountAttr,
    ) {
        $forwardFriendCountAttr = trim($forwardFriendCountAttr);
        $this->forwardFriendCountAttribute = $forwardFriendCountAttr !== '' ? $forwardFriendCountAttr : null;
    }

    public function incrementFriendsCount(Subscriber $subscriber): void
    {
        if ($this->forwardFriendCountAttribute === null) {
            return;
        }

        if ($this->friendCount === null) {
            $this->friendCount = $this->loadFriendsCount($subscriber);
        }

        $this->friendCount++;
    }

    public function updateFriendsCount(Subscriber $subscriber): void
    {
        if ($this->forwardFriendCountAttribute === null) {
            return;
        }

        if ($this->friendCount === null) {
            return;
        }

        $this->subscriberAttributeManager->createOrUpdateByName(
            subscriber: $subscriber,
            attributeName: $this->forwardFriendCountAttribute,
            value: (string) $this->friendCount
        );

        unset($this->friendCount);
    }

    private function loadFriendsCount(Subscriber $subscriber): int
    {
        if ($this->forwardFriendCountAttribute === null) {
            return 0;
        }

        $attribute = $this->subscriberAttributeValueRepo->findOneBySubscriberAndAttributeName(
            $subscriber,
            $this->forwardFriendCountAttribute
        );

        return (int) ($attribute?->getValue() ?? 0);
    }
}

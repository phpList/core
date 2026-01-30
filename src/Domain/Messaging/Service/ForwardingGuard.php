<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Exception\ForwardLimitExceededException;
use PhpList\Core\Domain\Messaging\Exception\MessageNotReceivedException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ForwardingGuard
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly UserMessageForwardRepository $forwardRepository,
        #[Autowire('%phplist.forward_message_count%')] private readonly int $forwardMessageCount,
    ) {
    }

    public function assertCanForward(string $uid, Message $campaign, \DateTimeInterface $cutoff): Subscriber
    {
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uid);
        $receivedMessage = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $campaign);

        if ($receivedMessage === null) {
            throw new MessageNotReceivedException();
        }

        $forwardPeriodCount = $this->forwardRepository->getCountByUserSince($subscriber, $cutoff);
        if ($forwardPeriodCount > $this->forwardMessageCount) {
            throw new ForwardLimitExceededException();
        }

        return $subscriber;
    }

    public function hasAlreadyBeenSent(string $friendEmail, Message $campaign): bool
    {
        $existing = $this->forwardRepository->findByEmailAndMessage($friendEmail, $campaign->getId());

        return $existing !== null && $existing->getStatus() === 'sent';
    }
}

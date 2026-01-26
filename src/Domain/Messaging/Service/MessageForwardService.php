<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageForwardService
{
    public function __construct(
        private readonly UserMessageForwardRepository $forwardRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly TranslatorInterface $translator,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly SubscriberAttributeValueRepository $subscriberAttributeValueManager,
        #[Autowire('%phplist.forward_friend_count_attribute%')] private readonly string $forwardFriendCount,
    ) {
    }

    public function forward(array $emails, string $uid, Message $message, DateTimeInterface $cutoff): void
    {
        $messageData = ($this->messageDataLoader)($message);
        $subtitle = $this->translator->trans('Forwarding the message with subject') . ' ' . stripslashes($messageData['subject']);
        $subscriber = $this->subscriberRepository->findOneByUniqueId($uid);
        $receivedMessage = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $message);
        if ($receivedMessage === null) {
            // todo: do something
        }

        if ($this->forwardFriendCount && $this->forwardFriendCount !== '') {
            $iCountFriends = $this->forwardFriendCount;
        } else {
            $iCountFriends = 0;
        }
        if ($iCountFriends) {
            $nFriends = intval($this->subscriberAttributeValueManager->getAttributeValue($subscriber, $iCountFriends));
        }


        $forwardPeriodCount = $this->forwardRepository->getCountByUserSince($subscriber, $cutoff);

    }
}

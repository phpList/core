<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Identity\Service\AdminNotifier;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageForwardDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

class MessageForwardService
{
    public function __construct(
        private readonly ForwardingGuard $guard,
        private readonly ForwardDeliveryService $forwardDeliveryService,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly SubscriberListRepository $subscriberListRepository,
        private readonly ForwardContentService $forwardContentService,
        private readonly MessagePrecacheService $precacheService,
        private readonly AdminNotifier $adminNotifier,
        private readonly ForwardingStatsService $forwardingStatsService,
    ) {
    }

    public function forward(MessageForwardDto $messageForwardDto, Message $campaign): void
    {
        $loadedMessageData = ($this->messageDataLoader)($campaign);
        $forwardingSubscriber = $this->guard->assertCanForward(
            uid: $messageForwardDto->getUid(),
            campaign: $campaign,
            cutoff: $messageForwardDto->getCutoff(),
        );
        $messageLists = $this->subscriberListRepository->getListsByMessage($campaign);

        foreach ($messageForwardDto->getEmails() as $friendEmail) {
            if ($this->guard->hasAlreadyBeenSent(friendEmail: $friendEmail, campaign: $campaign)) {
                continue;
            }

            if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData, true)) {
                $this->adminNotifier->notifyForwardFailed(
                    campaign: $campaign,
                    forwardingSubscriber: $forwardingSubscriber,
                    friendEmail: $friendEmail,
                    lists: $messageLists
                );
                $this->forwardDeliveryService->markFailed($campaign, $forwardingSubscriber, $friendEmail);
                continue;
            }

            $result = $this->forwardContentService->getContents(
                campaign: $campaign,
                forwardingSubscriber: $forwardingSubscriber,
                friendEmail: $friendEmail,
                forwardDto: $messageForwardDto,
            );

            if ($result === null) {
                $this->adminNotifier->notifyForwardFailed(
                    campaign: $campaign,
                    forwardingSubscriber: $forwardingSubscriber,
                    friendEmail: $friendEmail,
                    lists: $messageLists
                );
                $this->forwardDeliveryService->markFailed($campaign, $forwardingSubscriber, $friendEmail);
                continue;
            }

            [$email, $sentAs] = $result;
            $this->forwardDeliveryService->send($email);
            $this->adminNotifier->notifyForwardSucceeded(
                campaign: $campaign,
                forwardingSubscriber: $forwardingSubscriber,
                friendEmail: $friendEmail,
                lists: $messageLists
            );
            $this->forwardDeliveryService->markSent($campaign, $forwardingSubscriber, $friendEmail);
            $campaign->incrementSentCount($sentAs);
            $this->forwardingStatsService->incrementFriendsCount($forwardingSubscriber);
        }

        $this->forwardingStatsService->updateFriendsCount($forwardingSubscriber);
    }
}

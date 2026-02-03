<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Identity\Service\AdminNotifier;
use PhpList\Core\Domain\Messaging\Exception\EmailBlacklistedException;
use PhpList\Core\Domain\Messaging\Exception\InvalidRecipientOrSubjectException;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageForwardDto;
use PhpList\Core\Domain\Messaging\Model\Dto\ForwardingRecipientResult;
use PhpList\Core\Domain\Messaging\Model\Dto\ForwardingResult;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
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

    public function forward(MessageForwardDto $messageForwardDto, Message $campaign): ForwardingResult
    {
        $recipientsResults = [];
        $totalRequested = count($messageForwardDto->getEmails());
        $totalSent = 0;
        $totalFailed = 0;
        $totalAlreadySent = 0;

        $loadedMessageData = ($this->messageDataLoader)($campaign);
        $forwardingSubscriber = $this->guard->assertCanForward(
            uid: $messageForwardDto->getUid(),
            campaign: $campaign,
        );
        $messageLists = $this->subscriberListRepository->getListsByMessage($campaign);

        foreach ($messageForwardDto->getEmails() as $friendEmail) {
            if ($this->guard->hasAlreadyBeenSent(friendEmail: $friendEmail, campaign: $campaign)) {
                $totalAlreadySent++;
                $recipientsResults[] = new ForwardingRecipientResult(
                    email: $friendEmail,
                    status: 'already_sent',
                );
                continue;
            }

            if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData, true)) {
                $forwardingRecipientResult = $this->handleFailure(
                    campaign: $campaign,
                    forwardingSubscriber: $forwardingSubscriber,
                    friendEmail: $friendEmail,
                    messageLists: $messageLists,
                );
                $forwardingRecipientResult->reason = 'precache_failed';
                $recipientsResults[] = $forwardingRecipientResult;
                $totalFailed++;
                continue;
            }

            try {
                $result = $this->forwardContentService->getContents(
                    campaign: $campaign,
                    forwardingSubscriber: $forwardingSubscriber,
                    friendEmail: $friendEmail,
                    forwardDto: $messageForwardDto,
                );
            } catch (EmailBlacklistedException | MessageCacheMissingException | InvalidRecipientOrSubjectException $e) {
                $forwardingRecipientResult = $this->handleFailure(
                    campaign: $campaign,
                    forwardingSubscriber: $forwardingSubscriber,
                    friendEmail: $friendEmail,
                    messageLists: $messageLists,
                );

                $forwardingRecipientResult->reason = $e->getMessage();
                $recipientsResults[] = $forwardingRecipientResult;
                $totalFailed++;
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
            $totalSent++;
            $recipientsResults[] = new ForwardingRecipientResult(
                email: $friendEmail,
                status: 'sent',
            );
        }

        $this->forwardingStatsService->updateFriendsCount($forwardingSubscriber);

        return new ForwardingResult(
            totalRequested: $totalRequested,
            totalSent: $totalSent,
            totalFailed: $totalFailed,
            totalAlreadySent: $totalAlreadySent,
            recipients: $recipientsResults,
        );
    }

    private function handleFailure(
        Message $campaign,
        Subscriber $forwardingSubscriber,
        string $friendEmail,
        array $messageLists
    ): ForwardingRecipientResult {
        $this->adminNotifier->notifyForwardFailed(
            campaign: $campaign,
            forwardingSubscriber: $forwardingSubscriber,
            friendEmail: $friendEmail,
            lists: $messageLists
        );
        $this->forwardDeliveryService->markFailed($campaign, $forwardingSubscriber, $friendEmail);

        return new ForwardingRecipientResult(
            email: $friendEmail,
            status: 'failed',
        );
    }
}

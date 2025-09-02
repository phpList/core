<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;

class BounceDataProcessor
{
    private readonly BounceManager $bounceManager;
    private readonly SubscriberRepository $subscriberRepository;
    private readonly MessageRepository $messageRepository;
    private readonly LoggerInterface $logger;
    private readonly SubscriberManager $subscriberManager;
    private readonly SubscriberHistoryManager $subscriberHistoryManager;

    public function __construct(
        BounceManager $bounceManager,
        SubscriberRepository $subscriberRepository,
        MessageRepository $messageRepository,
        LoggerInterface $logger,
        SubscriberManager $subscriberManager,
        SubscriberHistoryManager $subscriberHistoryManager,
    ) {
        $this->bounceManager = $bounceManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->messageRepository = $messageRepository;
        $this->logger = $logger;
        $this->subscriberManager = $subscriberManager;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
    }

    public function process(Bounce $bounce, ?string $msgId, ?int $userId, DateTimeImmutable $bounceDate): bool
    {
        $user = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;

        if ($msgId === 'systemmessage') {
            return $userId ? $this->handleSystemMessageWithUser(
                $bounce,
                $bounceDate,
                $userId,
                $user
            ) : $this->handleSystemMessageUnknownUser($bounce);
        }

        if ($msgId && $userId) {
            return $this->handleKnownMessageAndUser($bounce, $bounceDate, (int)$msgId, $userId);
        }

        if ($userId) {
            return $this->handleUserOnly($bounce, $userId);
        }

        if ($msgId) {
            return $this->handleMessageOnly($bounce, (int)$msgId);
        }

        $this->bounceManager->update($bounce, 'unidentified bounce', 'not processed');

        return false;
    }

    private function handleSystemMessageWithUser(
        Bounce $bounce,
        DateTimeImmutable $date,
        int $userId,
        $userOrNull
    ): bool {
        $this->bounceManager->update(
            bounce: $bounce,
            status: 'bounced system message',
            comment: sprintf('%d marked unconfirmed', $userId)
        );
        $this->bounceManager->linkUserMessageBounce($bounce, $date, $userId);
        $this->subscriberRepository->markUnconfirmed($userId);
        $this->logger->info('system message bounced, user marked unconfirmed', ['userId' => $userId]);

        if ($userOrNull) {
            $this->subscriberHistoryManager->addHistory(
                subscriber: $userOrNull,
                message: 'Bounced system message',
                details: sprintf('User marked unconfirmed. Bounce #%d', $bounce->getId())
            );
        }

        return true;
    }

    private function handleSystemMessageUnknownUser(Bounce $bounce): bool
    {
        $this->bounceManager->update($bounce, 'bounced system message', 'unknown user');
        $this->logger->info('system message bounced, but unknown user');

        return true;
    }

    private function handleKnownMessageAndUser(
        Bounce $bounce,
        DateTimeImmutable $date,
        int $msgId,
        int $userId
    ): bool {
        if (!$this->bounceManager->existsUserMessageBounce($userId, $msgId)) {
            $this->bounceManager->linkUserMessageBounce($bounce, $date, $userId, $msgId);
            $this->bounceManager->update(
                bounce: $bounce,
                status: sprintf('bounced list message %d', $msgId),
                comment: sprintf('%d bouncecount increased', $userId)
            );
            $this->messageRepository->incrementBounceCount($msgId);
            $this->subscriberRepository->incrementBounceCount($userId);
        } else {
            $this->bounceManager->linkUserMessageBounce($bounce, $date, $userId, $msgId);
            $this->bounceManager->update(
                bounce: $bounce,
                status: sprintf('duplicate bounce for %d', $userId),
                comment: sprintf('duplicate bounce for subscriber %d on message %d', $userId, $msgId)
            );
        }

        return true;
    }

    private function handleUserOnly(Bounce $bounce, int $userId): bool
    {
        $this->bounceManager->update(
            bounce: $bounce,
            status: 'bounced unidentified message',
            comment: sprintf('%d bouncecount increased', $userId)
        );
        $this->subscriberRepository->incrementBounceCount($userId);

        return true;
    }

    private function handleMessageOnly(Bounce $bounce, int $msgId): bool
    {
        $this->bounceManager->update(
            bounce: $bounce,
            status: sprintf('bounced list message %d', $msgId),
            comment: 'unknown user'
        );
        $this->messageRepository->incrementBounceCount($msgId);

        return true;
    }
}

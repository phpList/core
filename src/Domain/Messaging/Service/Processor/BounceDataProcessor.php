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
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberRepository $users,
        private readonly MessageRepository $messages,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
    ) {
    }

    public function process(Bounce $bounce, ?string $msgId, ?int $userId, DateTimeImmutable $bounceDate): bool
    {
        $user = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;

        if ($msgId === 'systemmessage' && $userId) {
            $this->bounceManager->update(
                bounce: $bounce,
                status: 'bounced system message',
                comment: sprintf('%d marked unconfirmed', $userId)
            );
            $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId);
            $this->subscriberManager->markUnconfirmed($userId);
            $this->logger->info('system message bounced, user marked unconfirmed', ['userId' => $userId]);
            if ($user) {
                $this->subscriberHistoryManager->addHistory(
                    subscriber: $user,
                    message: 'Bounced system message',
                    details: sprintf('User marked unconfirmed. Bounce #%d', $bounce->getId())
                );
            }

            return true;
        }

        if ($msgId && $userId) {
            if (!$this->bounceManager->existsUserMessageBounce($userId, (int)$msgId)) {
                $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId, (int)$msgId);
                $this->bounceManager->update(
                    bounce: $bounce,
                    status: sprintf('bounced list message %d', $msgId),
                    comment: sprintf('%d bouncecount increased', $userId)
                );
                $this->messages->incrementBounceCount((int)$msgId);
                $this->users->incrementBounceCount($userId);
            } else {
                $this->bounceManager->linkUserMessageBounce($bounce, $bounceDate, $userId, (int)$msgId);
                $this->bounceManager->update(
                    bounce: $bounce,
                    status: sprintf('duplicate bounce for %d', $userId),
                    comment: sprintf('duplicate bounce for subscriber %d on message %d', $userId, $msgId)
                );
            }

            return true;
        }

        if ($userId) {
            $this->bounceManager->update(
                bounce: $bounce,
                status: 'bounced unidentified message',
                comment: sprintf('%d bouncecount increased', $userId)
            );
            $this->users->incrementBounceCount($userId);

            return true;
        }

        if ($msgId === 'systemmessage') {
            $this->bounceManager->update($bounce, 'bounced system message', 'unknown user');
            $this->logger->info('system message bounced, but unknown user');

            return true;
        }

        if ($msgId) {
            $this->bounceManager->update($bounce, sprintf('bounced list message %d', $msgId), 'unknown user');
            $this->messages->incrementBounceCount((int)$msgId);

            return true;
        }

        $this->bounceManager->update($bounce, 'unidentified bounce', 'not processed');

        return false;
    }
}

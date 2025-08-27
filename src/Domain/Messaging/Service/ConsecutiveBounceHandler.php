<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsecutiveBounceHandler
{
    private BounceManager $bounceManager;
    private SubscriberRepository $subscriberRepository;
    private SubscriberManager $subscriberManager;
    private SubscriberHistoryManager $subscriberHistoryManager;
    private int $unsubscribeThreshold;
    private int $blacklistThreshold;

    public function __construct(
        BounceManager $bounceManager,
        SubscriberRepository $subscriberRepository,
        SubscriberManager $subscriberManager,
        SubscriberHistoryManager $subscriberHistoryManager,
        int $unsubscribeThreshold,
        int $blacklistThreshold,
    ) {
        $this->bounceManager = $bounceManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->subscriberManager = $subscriberManager;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->unsubscribeThreshold = $unsubscribeThreshold;
        $this->blacklistThreshold = $blacklistThreshold;
    }

    public function handle(SymfonyStyle $io): void
    {
        $io->section('Identifying consecutive bounces');

        $users = $this->subscriberRepository->distinctUsersWithBouncesConfirmedNotBlacklisted();
        $total = count($users);

        if ($total === 0) {
            $io->writeln('Nothing to do');
            return;
        }

        $processed = 0;
        foreach ($users as $user) {
            $this->processUser($user);
            $processed++;

            if ($processed % 5 === 0) {
                $io->writeln(\sprintf('processed %d out of %d subscribers', $processed, $total));
            }
        }

        $io->writeln(\sprintf('total of %d subscribers processed', $total));
    }

    private function processUser(Subscriber $user): void
    {
        $history = $this->bounceManager->getUserMessageHistoryWithBounces($user);
        if (count($history) === 0) {
            return;
        }

        $consecutive = 0;
        $unsubscribed = false;

        foreach ($history as $row) {
            /** @var array{um: UserMessage, umb: UserMessageBounce|null, b: Bounce|null} $row */
            $bounce = $row['b'] ?? null;

            if ($this->isDuplicate($bounce)) {
                continue;
            }

            if (!$this->hasRealId($bounce)) {
                break;
            }

            $consecutive++;

            if ($this->applyThresholdActions($user, $consecutive, $unsubscribed)) {
                break;
            }

            if (!$unsubscribed && $consecutive >= $this->unsubscribeThreshold) {
                $unsubscribed = true;
            }
        }
    }

    private function isDuplicate(?Bounce $bounce): bool
    {
        if ($bounce === null) {
            return false;
        }
        $status = strtolower($bounce->getStatus() ?? '');
        $comment = strtolower($bounce->getComment() ?? '');

        return str_contains($status, 'duplicate') || str_contains($comment, 'duplicate');
    }

    private function hasRealId(?Bounce $bounce): bool
    {
        return $bounce !== null && (int) $bounce->getId() > 0;
    }

    /**
     * Returns true if processing should stop for this user (e.g., blacklisted).
     */
    private function applyThresholdActions($user, int $consecutive, bool $alreadyUnsubscribed): bool
    {
        if ($consecutive >= $this->unsubscribeThreshold && !$alreadyUnsubscribed) {
            $this->subscriberRepository->markUnconfirmed($user->getId());
            $this->subscriberHistoryManager->addHistory(
                subscriber: $user,
                message: 'Auto Unconfirmed',
                details: sprintf('Subscriber auto unconfirmed for %d consecutive bounces', $consecutive)
            );
        }

        if ($this->blacklistThreshold > 0 && $consecutive >= $this->blacklistThreshold) {
            $this->subscriberManager->blacklist(
                subscriber: $user,
                reason: sprintf('%d consecutive bounces, threshold reached', $consecutive)
            );
            return true;
        }

        return false;
    }
}

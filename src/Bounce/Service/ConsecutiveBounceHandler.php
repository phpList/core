<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConsecutiveBounceHandler
{
    private BounceManager $bounceManager;
    private SubscriberRepository $subscriberRepository;
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberBlacklistService $blacklistService;
    private TranslatorInterface $translator;
    private int $unsubscribeThreshold;
    private int $blacklistThreshold;

    public function __construct(
        BounceManager $bounceManager,
        SubscriberRepository $subscriberRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberBlacklistService $blacklistService,
        TranslatorInterface $translator,
        int $unsubscribeThreshold,
        int $blacklistThreshold,
    ) {
        $this->bounceManager = $bounceManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->blacklistService = $blacklistService;
        $this->translator = $translator;
        $this->unsubscribeThreshold = $unsubscribeThreshold;
        $this->blacklistThreshold = $blacklistThreshold;
    }

    public function handle(SymfonyStyle $io): void
    {
        $io->section($this->translator->trans('Identifying consecutive bounces'));

        $users = $this->subscriberRepository->distinctUsersWithBouncesConfirmedNotBlacklisted();
        $total = count($users);

        if ($total === 0) {
            $io->writeln($this->translator->trans('Nothing to do'));

            return;
        }

        $processed = 0;
        foreach ($users as $user) {
            $this->processUser($user);
            $processed++;

            if ($processed % 5 === 0) {
                $io->writeln($this->translator->trans('Processed %processed% out of %total% subscribers', [
                    '%processed%' => $processed,
                    '%total%' => $total,
                ]));
            }
        }

        $io->writeln($this->translator->trans('Total of %total% subscribers processed', ['%total%' => $total]));
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
                message: $this->translator->trans('Auto unconfirmed'),
                details: $this->translator->trans('Subscriber auto unconfirmed for %count% consecutive bounces', [
                    '%count%' => $consecutive
                ])
            );
        }

        if ($this->blacklistThreshold > 0 && $consecutive >= $this->blacklistThreshold) {
            $this->blacklistService->blacklist(
                subscriber: $user,
                reason: $this->translator->trans('%count% consecutive bounces, threshold reached', [
                    '%count%' => $consecutive
                ])
            );
            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsecutiveBounceHandler
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
    ) {
    }

    public function handle(SymfonyStyle $io, int $unsubscribeThreshold, int $blacklistThreshold): void
    {
        $io->section('Identifying consecutive bounces');
        $users = $this->subscriberRepository->distinctUsersWithBouncesConfirmedNotBlacklisted();
        $total = count($users);
        if ($total === 0) {
            $io->writeln('Nothing to do');
            return;
        }
        $usercnt = 0;
        foreach ($users as $user) {
            $usercnt++;
            $history = $this->bounceManager->getUserMessageHistoryWithBounces($user);
            $cnt = 0; $removed = false; $unsubscribed = false;
            foreach ($history as $bounce) {
                /** @var $bounce array{um: UserMessage, umb: UserMessageBounce|null, b: Bounce|null} */
                if (
                    stripos($bounce['b']->getStatus() ?? '', 'duplicate') === false
                    && stripos($bounce['b']->getComment() ?? '', 'duplicate') === false
                ) {
                    if ($bounce['b']->getId()) {
                        $cnt++;
                        if ($cnt >= $unsubscribeThreshold) {
                            if (!$unsubscribed) {
                                $this->subscriberManager->markUnconfirmed($user->getId());
                                $this->subscriberHistoryManager->addHistory(
                                    subscriber: $user,
                                    message: 'Auto Unconfirmed',
                                    details: sprintf('Subscriber auto unconfirmed for %d consecutive bounces', $cnt)
                                );
                                $unsubscribed = true;
                            }
                            if ($blacklistThreshold > 0 && $cnt >= $blacklistThreshold) {
                                $this->subscriberManager->blacklist(
                                    subscriber: $user,
                                    reason: sprintf('%d consecutive bounces, threshold reached', $cnt)
                                );
                                $removed = true;
                            }
                        }
                    } else {
                        break;
                    }
                }
                if ($removed) {
                    break;
                }
            }
            if ($usercnt % 5 === 0) {
                $io->writeln(sprintf('processed %d out of %d subscribers', $usercnt, $total));
            }
        }
        $io->writeln(sprintf('total of %d subscribers processed', $total));
    }
}

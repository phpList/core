<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberBlacklistManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;

class SubscriberBlacklistService
{
    private EntityManagerInterface $entityManager;
    private SubscriberBlacklistManager $blacklistManager;
    private SubscriberHistoryManager $historyManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubscriberBlacklistManager $blacklistManager,
        SubscriberHistoryManager $historyManager,
    ) {
        $this->entityManager = $entityManager;
        $this->blacklistManager = $blacklistManager;
        $this->historyManager = $historyManager;
    }

    public function blacklist(Subscriber $subscriber, string $reason): void
    {
        $subscriber->setBlacklisted(true);
        $this->entityManager->flush($subscriber);
        $this->blacklistManager->addEmailToBlacklist($subscriber->getEmail(), $reason);

        foreach (array('REMOTE_ADDR','HTTP_X_FORWARDED_FOR') as $item) {
            if (isset($_SERVER[$item])) {
                $this->blacklistManager->addBlacklistData($subscriber->getEmail(), $item, $_SERVER[$item]);
            }
        }

        $this->historyManager->addHistory(
            subscriber: $subscriber,
            message: 'Added to blacklist',
            details: sprintf('Added to blacklist for reason %s', $reason)
        );

        if (isset($GLOBALS['plugins']) && is_array($GLOBALS['plugins'])) {
            foreach ($GLOBALS['plugins'] as $pluginName => $plugin) {
                if (method_exists($plugin, 'blacklistEmail')) {
                    $plugin->blacklistEmail($subscriber->getEmail(), $reason);
                }
            }
        }
    }
}

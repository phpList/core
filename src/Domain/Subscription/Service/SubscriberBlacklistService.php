<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberBlacklistManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Symfony\Component\HttpFoundation\RequestStack;

class SubscriberBlacklistService
{
    private EntityManagerInterface $entityManager;
    private SubscriberBlacklistManager $blacklistManager;
    private SubscriberHistoryManager $historyManager;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubscriberBlacklistManager $blacklistManager,
        SubscriberHistoryManager $historyManager,
        RequestStack $requestStack,
    ) {
        $this->entityManager = $entityManager;
        $this->blacklistManager = $blacklistManager;
        $this->historyManager = $historyManager;
        $this->requestStack = $requestStack;
    }

    public function blacklist(Subscriber $subscriber, string $reason): void
    {
        $subscriber->setBlacklisted(true);
        $this->entityManager->flush();
        $this->blacklistManager->addEmailToBlacklist($subscriber->getEmail(), $reason);

        foreach (array('REMOTE_ADDR','HTTP_X_FORWARDED_FOR') as $item) {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request) {
                return;
            }
            if ($request->server->get($item)) {
                $this->blacklistManager->addBlacklistData(
                    email: $subscriber->getEmail(),
                    name: $item,
                    data: $request->server->get($item)
                );
            }
        }

        $this->historyManager->addHistory(
            subscriber: $subscriber,
            message: 'Added to blacklist',
            details: sprintf('Added to blacklist for reason %s', $reason)
        );

        if (isset($GLOBALS['plugins']) && is_array($GLOBALS['plugins'])) {
            foreach ($GLOBALS['plugins'] as $plugin) {
                if (method_exists($plugin, 'blacklistEmail')) {
                    $plugin->blacklistEmail($subscriber->getEmail(), $reason);
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlacklistEmailAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private BounceManager $bounceManager;
    private SubscriberBlacklistService $blacklistService;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        BounceManager $bounceManager,
        SubscriberBlacklistService $blacklistService,
        TranslatorInterface $translator,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->bounceManager = $bounceManager;
        $this->blacklistService = $blacklistService;
        $this->translator = $translator;
    }

    public function supports(string $action): bool
    {
        return $action === 'blacklistemailanddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $reason = $this->translator->trans('Email address auto blacklisted by bounce rule %rule_id%', [
                '%rule_id%' => $closureData['ruleId']
            ]);
            $this->blacklistService->blacklist(
                subscriber: $closureData['subscriber'],
                reason: $reason
            );
            $details = $this->translator->trans('User auto unsubscribed for bounce rule %rule_id%', [
                '%rule_id%' => $closureData['ruleId']
            ]);
            $this->subscriberHistoryManager->addHistory(
                subscriber: $closureData['subscriber'],
                message: $this->translator->trans('Auto Unsubscribed'),
                details: $details
            );
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}

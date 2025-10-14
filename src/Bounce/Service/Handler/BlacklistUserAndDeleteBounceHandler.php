<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlacklistUserAndDeleteBounceHandler implements BounceActionHandlerInterface
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
        return $action === 'blacklistuseranddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && !$closureData['blacklisted']) {
            $this->blacklistService->blacklist(
                subscriber: $closureData['subscriber'],
                reason: $this->translator->trans('Subscriber auto blacklisted by bounce rule %rule_id%', [
                    '%rule_id%' => $closureData['ruleId']
                ])
            );
            $this->subscriberHistoryManager->addHistory(
                subscriber: $closureData['subscriber'],
                message: $this->translator->trans('Auto Unsubscribed'),
                details: $this->translator->trans('User auto unsubscribed for bounce rule %rule_id%', [
                    '%rule_id%' => $closureData['ruleId']
                ])
            );
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}

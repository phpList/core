<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlacklistEmailHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberBlacklistService $blacklistService;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberBlacklistService $blacklistService,
        TranslatorInterface $translator,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->blacklistService = $blacklistService;
        $this->translator = $translator;
    }

    public function supports(string $action): bool
    {
        return $action === 'blacklistemail';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->blacklistService->blacklist(
                subscriber: $closureData['subscriber'],
                reason: $this->translator->trans('Email address auto blacklisted by bounce rule %rule_id%', [
                    '%rule_id%' => $closureData['ruleId']
                ]),
            );
            $this->subscriberHistoryManager->addHistory(
                subscriber: $closureData['subscriber'],
                message: $this->translator->trans('Auto Unsubscribed'),
                details: $this->translator->trans('email auto unsubscribed for bounce rule %rule_id%', [
                    '%rule_id%' => $closureData['ruleId']
                ])
            );
        }
    }
}

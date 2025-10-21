<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Handler;

use PhpList\Core\Bounce\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class DecreaseCountConfirmUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberManager $subscriberManager;
    private BounceManager $bounceManager;
    private SubscriberRepository $subscriberRepository;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberManager $subscriberManager,
        BounceManager $bounceManager,
        SubscriberRepository $subscriberRepository,
        TranslatorInterface $translator,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->subscriberManager = $subscriberManager;
        $this->bounceManager = $bounceManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->translator = $translator;
    }

    public function supports(string $action): bool
    {
        return $action === 'decreasecountconfirmuseranddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->subscriberManager->decrementBounceCount($closureData['subscriber']);
            if (!$closureData['confirmed']) {
                $this->subscriberRepository->markConfirmed($closureData['userId']);
                $this->subscriberHistoryManager->addHistory(
                    subscriber: $closureData['subscriber'],
                    message: $this->translator->trans('Auto confirmed'),
                    details: $this->translator->trans('Subscriber auto confirmed for bounce rule %rule_id%', [
                        '%rule_id%' => $closureData['ruleId']
                    ])
                );
            }
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}

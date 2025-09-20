<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnconfirmUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberRepository $subscriberRepository;
    private BounceManager $bounceManager;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberRepository $subscriberRepository,
        BounceManager $bounceManager,
        TranslatorInterface $translator,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->bounceManager = $bounceManager;
        $this->translator = $translator;
    }

    public function supports(string $action): bool
    {
        return $action === 'unconfirmuseranddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && $closureData['confirmed']) {
            $this->subscriberRepository->markUnconfirmed($closureData['userId']);
            $this->subscriberHistoryManager->addHistory(
                subscriber: $closureData['subscriber'],
                message: $this->translator->trans('Auto unconfirmed'),
                details: $this->translator->trans('Subscriber auto unconfirmed for bounce rule %rule_id%', [
                    '%rule_id%' => $closureData['ruleId']
                ])
            );
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}

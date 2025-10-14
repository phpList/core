<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Processor;

use PhpList\Core\Bounce\Service\BounceActionResolver;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRuleManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdvancedBounceRulesProcessor
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly BounceRuleManager $ruleManager,
        private readonly BounceActionResolver $actionResolver,
        private readonly SubscriberManager $subscriberManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function process(SymfonyStyle $io, int $batchSize): void
    {
        $io->section($this->translator->trans('Processing bounces based on active bounce rules'));

        $rules = $this->ruleManager->loadActiveRules();
        if (!$rules) {
            $io->writeln($this->translator->trans('No active rules'));

            return;
        }

        $total = $this->bounceManager->getUserMessageBounceCount();
        $fromId = 0;
        $matched = 0;
        $notMatched = 0;
        $processed = 0;

        while ($processed < $total) {
            $batch = $this->bounceManager->fetchUserMessageBounceBatch($fromId, $batchSize);
            if (!$batch) {
                break;
            }

            foreach ($batch as $row) {
                $fromId = $row['umb']->getId();

                $bounce = $row['bounce'];
                $userId = (int) $row['umb']->getUserId();
                $text = $this->composeText($bounce);
                $rule = $this->ruleManager->matchBounceRules($text, $rules);

                if ($rule) {
                    $this->incrementRuleCounters($rule, $bounce);

                    $subscriber = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;
                    $ctx = $this->makeContext($subscriber, $bounce, (int)$rule->getId());

                    $action = (string) $rule->getAction();
                    $this->actionResolver->handle($action, $ctx);

                    $matched++;
                } else {
                    $notMatched++;
                }

                $processed++;
            }

            $io->writeln($this->translator->trans(
                'Processed %processed% out of %total% bounces for advanced bounce rules',
                ['%processed%' => min($processed, $total), '%total%' => $total]
            ));
        }

        $io->writeln($this->translator->trans(
            '%processed% bounces processed by advanced processing',
            ['%processed%' => $matched]
        ));
        $io->writeln($this->translator->trans(
            '%not_processed% bounces were not matched by advanced processing rules',
            ['%not_processed%' => $notMatched]
        ));
    }

    private function composeText(Bounce $bounce): string
    {
        return $bounce->getHeader() . "\n\n" . $bounce->getData();
    }

    private function incrementRuleCounters($rule, Bounce $bounce): void
    {
        $this->ruleManager->incrementCount($rule);
        $rule->setCount($rule->getCount() + 1);
        $this->ruleManager->linkRuleToBounce($rule, $bounce);
    }

    /**
     * @return array{
     *   subscriber: ?Subscriber,
     *   bounce: Bounce,
     *   userId: int,
     *   confirmed: bool,
     *   blacklisted: bool,
     *   ruleId: int
     * }
     */
    private function makeContext(?Subscriber $subscriber, Bounce $bounce, int $ruleId): array
    {
        $userId = $subscriber?->getId() ?? 0;
        $confirmed = $subscriber?->isConfirmed() ?? false;
        $blacklisted = $subscriber?->isBlacklisted() ?? false;

        return [
            'subscriber' => $subscriber,
            'bounce'     => $bounce,
            'userId'     => $userId,
            'confirmed'  => $confirmed,
            'blacklisted' => $blacklisted,
            'ruleId'     => $ruleId,
        ];
    }
}

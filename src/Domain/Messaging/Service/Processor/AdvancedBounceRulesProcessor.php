<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRuleManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AdvancedBounceRulesProcessor
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly BounceRuleManager $ruleManager,
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
    ) {
    }

    public function process(SymfonyStyle $io, int $batchSize): void
    {
        $io->section('Processing bounces based on active bounce rules');
        $rules = $this->ruleManager->loadActiveRules();
        if (!$rules) {
            $io->writeln('No active rules');
            return;
        }

        $total = $this->bounceManager->getUserMessageBounceCount();
        $fromId = 0;
        $matched = 0;
        $notmatched = 0;
        $counter = 0;

        while ($counter < $total) {
            $batch = $this->bounceManager->fetchUserMessageBounceBatch($fromId, $batchSize);
            $counter += count($batch);
            $io->writeln(sprintf('processed %d out of %d bounces for advanced bounce rules', min($counter, $total), $total));
            foreach ($batch as $row) {
                $fromId = $row['umb']->getId();
                // $row has: bounce(header,data,id), umb(user,message,bounce)
                $text = $row['bounce']->getHeader()."\n\n".$row['bounce']->getData();
                $rule = $this->ruleManager->matchBounceRules($text, $rules);
                $userId = (int)$row['umb']->getUserId();
                $bounce = $row['bounce'];
                $userdata = $userId ? $this->subscriberManager->getSubscriberById($userId) : null;
                $confirmed = $userdata?->isConfirmed() ?? false;
                $blacklisted = $userdata?->isBlacklisted() ?? false;

                if ($rule) {
                    $this->ruleManager->incrementCount($rule);
                    $rule->setCount($rule->getCount() + 1);
                    $this->ruleManager->linkRuleToBounce($rule, $bounce);

                    switch ($rule->getAction()) {
                        case 'deleteuser':
                            if ($userdata) {
                                $this->logger->info('User deleted by bounce rule', ['user' => $userdata->getEmail(), 'rule' => $rule->getId()]);
                                $this->subscriberManager->deleteSubscriber($userdata);
                            }
                            break;
                        case 'unconfirmuser':
                            if ($userdata && $confirmed) {
                                $this->subscriberManager->markUnconfirmed($userId);
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unconfirmed', 'Subscriber auto unconfirmed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'deleteuserandbounce':
                            if ($userdata) {
                                $this->subscriberManager->deleteSubscriber($userdata);
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'unconfirmuseranddeletebounce':
                            if ($userdata && $confirmed) {
                                $this->subscriberManager->markUnconfirmed($userId);
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto unconfirmed', 'Subscriber auto unconfirmed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'decreasecountconfirmuseranddeletebounce':
                            if ($userdata) {
                                $this->subscriberManager->decrementBounceCount($userdata);
                                if (!$confirmed) {
                                    $this->subscriberManager->markConfirmed($userId);
                                    $this->subscriberHistoryManager->addHistory($userdata, 'Auto confirmed', 'Subscriber auto confirmed for bounce rule '.$rule->getId());
                                }
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'blacklistuser':
                            if ($userdata && !$blacklisted) {
                                $this->subscriberManager->blacklist($userdata, 'Subscriber auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'blacklistuseranddeletebounce':
                            if ($userdata && !$blacklisted) {
                                $this->subscriberManager->blacklist($userdata, 'Subscriber auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'blacklistemail':
                            if ($userdata) {
                                $this->subscriberManager->blacklist($userdata, 'Email address auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'email auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            break;
                        case 'blacklistemailanddeletebounce':
                            if ($userdata) {
                                $this->subscriberManager->blacklist($userdata, 'Email address auto blacklisted by bounce rule '.$rule->getId());
                                $this->subscriberHistoryManager->addHistory($userdata, 'Auto Unsubscribed', 'User auto unsubscribed for bounce rule '.$rule->getId());
                            }
                            $this->bounceManager->delete($bounce);
                            break;
                        case 'deletebounce':
                            $this->bounceManager->delete($bounce);
                            break;
                    }
                    $matched++;
                } else {
                    $notmatched++;
                }
            }
        }
        $io->writeln(sprintf('%d bounces processed by advanced processing', $matched));
        $io->writeln(sprintf('%d bounces were not matched by advanced processing rules', $notmatched));
    }
}

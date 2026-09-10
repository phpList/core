<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Iterates campaign recipients, applying the process-time and per-domain rate limits,
 * and delegates the actual per-subscriber send to CampaignEmailSender.
 */
class CampaignSendingLoop
{
    public function __construct(
        private readonly UserMessageRepository $userMessageRepository,
        private readonly MaxProcessTimeLimiter $timeLimiter,
        private readonly DomainRateLimiter $domainRateLimiter,
        private readonly CacheInterface $cache,
        private readonly CampaignEmailSender $emailSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool whether processing stopped before exhausting $subscribers (time limit or domain throttle hit)
     * @throws MessageCacheMissingException if the precached message content has expired from cache
     */
    public function run(Message $campaign, array $subscribers, string $cacheKey): bool
    {
        $this->timeLimiter->start();
        $stoppedEarly = false;

        $total = count($subscribers);
        $sentAttempted = 0;
        $skippedAlreadyProcessed = 0;
        $throttled = 0;
        $invalidEmails = 0;

        $this->logger->info('Campaign send loop starting', [
            'campaign_id' => $campaign->getId(),
            'recipient_count' => $total,
        ]);

        foreach ($subscribers as $index => $subscriber) {
            if ($this->timeLimiter->shouldStop()) {
                $stoppedEarly = true;
                $this->logger->info('Campaign send loop stopping: time limit reached', [
                    'campaign_id' => $campaign->getId(),
                    'processed' => $index,
                    'remaining' => $total - $index,
                ]);
                break;
            }

            $existing = $this->userMessageRepository->findByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo) {
                $skippedAlreadyProcessed++;
                $this->logger->debug('Skipping subscriber: already processed', [
                    'campaign_id' => $campaign->getId(),
                    'subscriber_id' => $subscriber->getId(),
                    'existing_status' => $existing->getStatus()->value,
                ]);
                continue;
            }

            if (!$this->domainRateLimiter->attemptSend($subscriber->getEmail())->allowed) {
                // Leave no UserMessage record so this subscriber is picked up again on a
                // later run, once their domain's throttle window has passed.
                $stoppedEarly = true;
                $throttled++;
                $this->logger->debug('Skipping subscriber: domain rate limit hit', [
                    'campaign_id' => $campaign->getId(),
                    'subscriber_id' => $subscriber->getId(),
                    'email_domain' => substr((string) strrchr($subscriber->getEmail(), '@'), 1),
                ]);
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Active);
            $this->userMessageRepository->save($userMessage);

            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $invalidEmails++;
                $this->logger->warning('Invalid email address, skipping send', [
                    'campaign_id' => $campaign->getId(),
                    'subscriber_id' => $subscriber->getId(),
                ]);
                $this->emailSender->handleInvalidEmail($userMessage, $subscriber, $campaign);
                continue;
            }

            $messagePrecacheDto = $this->cache->get($cacheKey);
            if ($messagePrecacheDto === null) {
                $this->logger->error('Message precache missing, aborting loop', [
                    'campaign_id' => $campaign->getId(),
                    'cache_key' => $cacheKey,
                    'processed' => $index,
                ]);
                throw new MessageCacheMissingException();
            }

            $sentAttempted++;
            $this->logger->debug('Sending to subscriber', [
                'campaign_id' => $campaign->getId(),
                'subscriber_id' => $subscriber->getId(),
            ]);
            // todo: maybe catch exception and return false to stop early?
            $this->emailSender->send($campaign, $subscriber, $userMessage, $messagePrecacheDto);
        }

        $this->logger->info('Campaign send loop finished', [
            'campaign_id' => $campaign->getId(),
            'total_recipients' => $total,
            'send_attempted' => $sentAttempted,
            'skipped_already_processed' => $skippedAlreadyProcessed,
            'throttled' => $throttled,
            'invalid_emails' => $invalidEmails,
            'stopped_early' => $stoppedEarly,
        ]);

        return $stoppedEarly;
    }
}

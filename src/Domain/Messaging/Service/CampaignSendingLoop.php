<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
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

        foreach ($subscribers as $subscriber) {
            if ($this->timeLimiter->shouldStop()) {
                $stoppedEarly = true;
                break;
            }

            $existing = $this->userMessageRepository->findByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo) {
                continue;
            }

            if (!$this->domainRateLimiter->attemptSend($subscriber->getEmail())->allowed) {
                // Leave no UserMessage record so this subscriber is picked up again on a
                // later run, once their domain's throttle window has passed.
                $stoppedEarly = true;
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Active);
            $this->userMessageRepository->save($userMessage);

            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $this->emailSender->handleInvalidEmail($userMessage, $subscriber, $campaign);
                continue;
            }

            $messagePrecacheDto = $this->cache->get($cacheKey);
            if ($messagePrecacheDto === null) {
                throw new MessageCacheMissingException();
            }
            // todo: maybe catch exception and return false to stop early?
            $this->emailSender->send($campaign, $subscriber, $userMessage, $messagePrecacheDto);
        }

        return $stoppedEarly;
    }
}

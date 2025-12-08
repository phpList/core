<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Message\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageDataManager;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
#[AsMessageHandler]
class CampaignProcessorMessageHandler
{
    private ?int $maxMailSize;

    public function __construct(
        private readonly RateLimitedCampaignMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriberProvider $subscriberProvider,
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly MaxProcessTimeLimiter $timeLimiter,
        private readonly RequeueHandler $requeueHandler,
        private readonly TranslatorInterface $translator,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly MessageRepository $messageRepository,
        private readonly EventLogManager $eventLogManager,
        private readonly MessageDataManager $messageDataManager,
        private readonly MessagePrecacheService $precacheService,
        ?int $maxMailSize = null,
    ) {
        $this->maxMailSize = $maxMailSize ?? 0;
    }

    public function __invoke(CampaignProcessorMessage|SyncCampaignProcessorMessage $message): void
    {
        $campaign = $this->messageRepository->findByIdAndStatus($message->getMessageId(), MessageStatus::Submitted);
        if (!$campaign) {
            $this->logger->warning(
                $this->translator->trans('Campaign not found or not in submitted status'),
                ['campaign_id' => $message->getMessageId()]
            );

            return;
        }

        $messageContent = $this->precacheService->getOrCacheBaseMessageContent($campaign);

        $this->updateMessageStatus($campaign, MessageStatus::Prepared);
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);

        $this->updateMessageStatus($campaign, MessageStatus::InProcess);

        $this->timeLimiter->start();
        $stoppedEarly = false;

        foreach ($subscribers as $subscriber) {
            if ($this->timeLimiter->shouldStop()) {
                $stoppedEarly = true;
                break;
            }

            $existing = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo) {
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Active);
            $this->userMessageRepository->save($userMessage);

            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $this->handleInvalidEmail($userMessage, $subscriber, $campaign);
                $this->entityManager->flush();
                continue;
            }

            $this->handleEmailSending($campaign, $subscriber, $userMessage, $messageContent);
        }

        if ($stoppedEarly && $this->requeueHandler->handle($campaign)) {
            $this->entityManager->flush();
            return;
        }

        $this->updateMessageStatus($campaign, MessageStatus::Sent);
    }

    private function unconfirmSubscriber(Subscriber $subscriber): void
    {
        if ($subscriber->isConfirmed()) {
            $subscriber->setConfirmed(false);
            $this->entityManager->flush();
        }
    }

    private function updateMessageStatus(Message $message, MessageStatus $status): void
    {
        $message->getMetadata()->setStatus($status);
        $this->entityManager->flush();
    }

    private function updateUserMessageStatus(UserMessage $userMessage, UserMessageStatus $status): void
    {
        $userMessage->setStatus($status);
        $this->entityManager->flush();
    }

    private function handleInvalidEmail(UserMessage $userMessage, Subscriber $subscriber, Message $campaign): void
    {
        $this->updateUserMessageStatus($userMessage, UserMessageStatus::InvalidEmailAddress);
        $this->unconfirmSubscriber($subscriber);
        $this->logger->warning($this->translator->trans('Invalid email, marking unconfirmed: %email%', [
            '%email%' => $subscriber->getEmail(),
        ]));
        $this->subscriberHistoryManager->addHistory(
            subscriber: $subscriber,
            message: $this->translator->trans('Subscriber marked unconfirmed for invalid email address'),
            details: $this->translator->trans(
                'Marked unconfirmed while sending campaign %message_id%',
                ['%message_id%' => $campaign->getId()]
            )
        );
    }

    private function handleEmailSending(
        Message $campaign,
        Subscriber $subscriber,
        UserMessage $userMessage,
        Message\MessageContent $precachedContent,
    ): void {
        $processed = $this->messagePreparator->processMessageLinks($campaign->getId(), $precachedContent, $subscriber);

        try {
            $email = $this->mailer->composeEmail($campaign, $subscriber, $processed);
            $this->mailer->send($email);
            $this->checkMessageSizeOrSuspendCampaign($campaign, $email, $subscriber->hasHtmlEmail());
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);
        } catch (MessageSizeLimitExceededException $e) {
            // stop after the first message if size is exceeded
            $this->updateMessageStatus($campaign, MessageStatus::Suspended);
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);

            throw $e;
        } catch (Throwable $e) {
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::NotSent);
            $this->logger->error($e->getMessage(), [
                'subscriber_id' => $subscriber->getId(),
                'campaign_id' => $campaign->getId(),
            ]);
            $this->logger->warning($this->translator->trans('Failed to send to: %email%', [
                '%email%' => $subscriber->getEmail(),
            ]));
        }
    }

    private function checkMessageSizeOrSuspendCampaign(
        Message $campaign,
        Email $email,
        bool $hasHtmlEmail
    ): void {
        if ($this->maxMailSize <= 0) {
            return;
        }
        $sizeName = $hasHtmlEmail ? 'htmlsize' : 'textsize';
        $cacheKey = sprintf('messaging.size.%d.%s', $campaign->getId(), $sizeName);
        if (!$this->cache->has($cacheKey)) {
            $size = $this->calculateEmailSize($email);
            $this->messageDataManager->setMessageData($campaign, $sizeName, $size);
            $this->cache->set($cacheKey, $size);
        }

        $size = $this->cache->get($cacheKey);
        if ($size <= $this->maxMailSize) {
            return;
        }

        $this->logger->warning(sprintf(
            'Message too large (%d is over %d), suspending campaign %d',
            $size,
            $this->maxMailSize,
            $campaign->getId()
        ));

        $this->eventLogManager->log('send', sprintf(
            'Message too large (%d is over %d), suspending',
            $size,
            $this->maxMailSize
        ));

        $this->eventLogManager->log('send', sprintf(
            'Campaign %d suspended. Message too large',
            $campaign->getId()
        ));

        throw new MessageSizeLimitExceededException($size, $this->maxMailSize);
    }

    private function calculateEmailSize(Email $email): int
    {
        $size = 0;

        foreach ($email->toIterable() as $line) {
            $size += strlen($line);
        }

        return $size;
    }
}

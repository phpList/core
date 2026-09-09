<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler\CampaignProcessor;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Messaging\Service\DomainRateLimiter;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\MailSizeChecker;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsMessageHandler]
class CampaignProcessorMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RateLimitedCampaignMailer $rateLimitedCampaignMailer,
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
        private readonly MessagePrecacheService $precacheService,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly SystemEmailBuilder $systemEmailBuilder,
        private readonly EmailBuilder $campaignEmailBuilder,
        private readonly MailSizeChecker $mailSizeChecker,
        private readonly ConfigProvider $configProvider,
        private readonly DomainRateLimiter $domainRateLimiter,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
        #[Autowire('%messaging.use_list_exclude%')] private readonly bool $useListExclude = false,
    ) {
    }

    public function __invoke(CampaignProcessorMessage|SyncCampaignProcessorMessage $data): void
    {
        $campaign = $this->messageRepository->tryClaimForProcessing($data->getMessageId());
        if (!$campaign) {
            $this->logger->warning(
                $this->translator->trans('Campaign not found or not in submitted status'),
                ['campaign_id' => $data->getMessageId()]
            );

            return;
        }

        $loadedMessageData = ($this->messageDataLoader)($campaign);
//        if (!empty($loadedMessageData['resetstats'])) {
//            resetMessageStatistics($loadedMessageData['id']);
//            setMessageData($loadedMessageData['id'], 'resetstats', 0);
//        }
//        $stopSending = false;
//        if (!empty($loadedMessageData['finishsending'])) {
//            $finishSendingBefore = mktime(
//                $loadedMessageData['finishsending']['hour'],
//                $loadedMessageData['finishsending']['minute'],
//                0,
//                $loadedMessageData['finishsending']['month'],
//                $loadedMessageData['finishsending']['day'],
//                $loadedMessageData['finishsending']['year'],
//            );
//            $secondsTogo = $finishSendingBefore - time();
//            $stopSending = $secondsTogo < 0;
//        }
//        $userSelection = $loadedMessageData['userselection'];

        $cacheKey = sprintf('messaging.message.base.%d.%d', $campaign->getId(), 0);
        if (!$this->precacheService->precacheMessage(
            campaign: $campaign,
            loadedMessageData: $loadedMessageData,
            isTest: false
        )) {
            $this->updateMessageStatus($campaign, MessageStatus::Suspended);

            return;
        }

        $this->handleAdminNotifications($campaign, $loadedMessageData, $data->getMessageId());

        // Campaign was already atomically claimed into Prepared status above.
        $excludeListIds = $this->getExcludeListIds($loadedMessageData);
        $this->markExcludedSubscribers($campaign, $data, $excludeListIds);
        $subscribers = $this->subscriberProvider->getSubscribersForMessageOrLists(
            $data,
            $campaign,
            $excludeListIds
        );

        $this->updateMessageStatus($campaign, MessageStatus::InProcess);

        $stoppedEarly = $this->processSubscribersForCampaign($campaign, $subscribers, $cacheKey);

        if ($stoppedEarly && $this->requeueHandler->handle($campaign)) {
            $this->entityManager->flush();
            return;
        }

        $this->updateMessageStatus($campaign, MessageStatus::Sent);
    }

    /**
     * Exclude-list IDs are stored via MessageData as an array keyed by list ID  e.g. [3 => 1, 7 => 1].
     *
     * @return int[]
     */
    private function getExcludeListIds(array $loadedMessageData): array
    {
        if (!$this->useListExclude) {
            return [];
        }

        $excludeList = $loadedMessageData['excludelist'] ?? [];
        if (!is_array($excludeList) || $excludeList === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $key): ?int => is_numeric($key) ? (int) $key : null,
            array_keys($excludeList)
        ), static fn (?int $id): bool => $id !== null));
    }

    /**
     * pre-marking of exclude-list members as "excluded" in usermessage before the main send loop runs,
     * so there's a persisted audit trail for why a subscriber wasn't sent to. Skips
     * subscribers who already have a nontodo UserMessage for this campaign, so a later run
     * can't clobber an already-recorded Sent/NotSent/etc. status from an earlier partial run.
     * Only campaign recipients (i.e. subscribers who'd otherwise be sent this campaign) are
     * marked, since a subscriber on an exclude list who isn't a campaign recipient anyway
     * shouldn't get an exclusion record.
     */
    private function markExcludedSubscribers(
        Message $campaign,
        CampaignProcessorMessage|SyncCampaignProcessorMessage $data,
        array $excludeListIds,
    ): void {
        if ($excludeListIds === []) {
            return;
        }

        $excludedSubscribers = $this->subscriberProvider->getExcludedSubscribers($excludeListIds);
        if ($excludedSubscribers === []) {
            return;
        }

        $sendableSubscribers = $this->subscriberProvider->getSendableSubscribersForMessageOrLists(
            $data,
            $campaign
        );

        foreach ($excludedSubscribers as $subscriber) {
            if (!isset($sendableSubscribers[$subscriber->getEmail()])) {
                continue;
            }

            $existing = $this->userMessageRepository->findByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo) {
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Excluded);
            $this->userMessageRepository->save($userMessage);
        }
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
        if ($status === MessageStatus::InProcess && $message->getMetadata()->getSendStart() === null) {
            $message->getMetadata()->setSendStart(new DateTime());
        }
        if ($status === MessageStatus::Sent) {
            $message->getMetadata()->setSent(new DateTime());
        }
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
        MessagePrecacheDto $precachedContent,
    ): void {
        // todo: check at which point link tracking should be applied (maybe after constructing full text?)
        $processed = $this->messagePreparator->processMessageLinks(
            campaignId: $campaign->getId(),
            cachedMessageDto: $precachedContent,
            subscriber: $subscriber
        );

        try {
            $result = $this->campaignEmailBuilder->buildCampaignEmail(
                messageId: $campaign->getId(),
                data: $processed,
                toEmail: $subscriber->getEmail(),
                skipBlacklistCheck: false,
                inBlast: true,
                htmlPref: $subscriber->hasHtmlEmail(),
            );
            if ($result === null) {
                $status = $subscriber->isBlacklisted() ? UserMessageStatus::Excluded : UserMessageStatus::NotSent;
                $this->updateUserMessageStatus($userMessage, $status);

                return;
            }
            [$email, $sentAs] = $result;
            $this->campaignEmailBuilder->applyCampaignHeaders(email: $email, subscriber: $subscriber);

            $this->rateLimitedCampaignMailer->send($email);
            ($this->mailSizeChecker)($campaign, $email, $subscriber->hasHtmlEmail());
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);
            $this->messageRepository->incrementSentCounts($campaign->getId(), $sentAs);
        } catch (MessageSizeLimitExceededException $e) {
            // stop after the first message if size is exceeded
            $this->updateMessageStatus($campaign, MessageStatus::Suspended);
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);

            throw $e;
        } catch (AttachmentCopyException $e) {
            // stop after the first message if size is exceeded
            $this->updateMessageStatus($campaign, MessageStatus::Suspended);
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::NotSent);

            $data = new MessagePrecacheDto();
            $data->subject = $this->translator->trans('phpList system error');
            $data->content = $this->translator->trans($e->getMessage());

            $email = $this->systemEmailBuilder->buildCampaignEmail(
                messageId: $campaign->getId(),
                data: $data,
                toEmail: $this->configProvider->getValue(ConfigOption::ReportAddress) ?? '',
            );

            $envelope = new Envelope(
                sender: new Address($this->bounceEmail, 'PHPList'),
                recipients: [new Address($email->getTo()[0]->getAddress())],
            );
            $this->mailer->send(message: $email, envelope: $envelope);

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

    private function handleAdminNotifications(Message $campaign, array $loadedMessageData, int $messageId): void
    {
        if (!empty($loadedMessageData['notify_start']) && !isset($loadedMessageData['start_notified'])) {
            $notifications = explode(',', $loadedMessageData['notify_start']);
            foreach ($notifications as $notification) {
                $data = new MessagePrecacheDto();
                $data->subject = $this->translator->trans('Campaign started');
                $data->content = $this->translator->trans(
                    'phplist has started sending the campaign with subject %subject%',
                    ['%subject%' => $loadedMessageData['subject']]
                );

                $email = $this->systemEmailBuilder->buildCampaignEmail(
                    messageId: $campaign->getId(),
                    data: $data,
                    toEmail: $notification
                );

                if (!$email) {
                    continue;
                }

                // todo: check if from name should be from config
                $envelope = new Envelope(
                    sender: new Address($this->bounceEmail, 'PHPList'),
                    recipients: [new Address($email->getTo()[0]->getAddress())],
                );
                $this->mailer->send(message: $email, envelope: $envelope);
            }
            $messageData = new MessageData();
            $messageData->setName('start_notified');
            $messageData->setId($messageId);
            $messageData->setData((new DateTimeImmutable())->format('Y-m-d H:i:s'));

            try {
                $this->entityManager->persist($messageData);
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->debug('Duplicate message ignored', [
                    'exception' => $e,
                ]);
            }
        }
    }

    private function processSubscribersForCampaign(Message $campaign, array $subscribers, string $cacheKey): bool
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
                $this->handleInvalidEmail($userMessage, $subscriber, $campaign);
                $this->entityManager->flush();
                continue;
            }

            $messagePrecacheDto = $this->cache->get($cacheKey);
            if ($messagePrecacheDto === null) {
                throw new MessageCacheMissingException();
            }
            // todo: maybe catch exception and return false to stop early?
            $this->handleEmailSending($campaign, $subscriber, $userMessage, $messagePrecacheDto);
        }

        return $stoppedEarly;
    }
}

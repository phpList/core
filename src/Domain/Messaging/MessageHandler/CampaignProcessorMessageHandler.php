<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Message\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        private readonly UserPersonalizer $userPersonalizer,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly EmailBuilder $emailBuilder,
        private readonly MailSizeChecker $mailSizeChecker,
        private readonly string $messageEnvelope,
    ) {
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
        if (!$this->precacheService->precacheMessage($campaign, $loadedMessageData)) {
            $this->updateMessageStatus($campaign, MessageStatus::Suspended);

            return;
        }

        $this->handleAdminNotifications($campaign, $loadedMessageData, $message->getMessageId());

        $this->updateMessageStatus($campaign, MessageStatus::Prepared);
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);

        $this->updateMessageStatus($campaign, MessageStatus::InProcess);

//        if (USE_LIST_EXCLUDE) {
//            if (VERBOSE) {
//                processQueueOutput(s('looking for users who can be excluded from this mailing'));
//            }
//            if (count($msgdata['excludelist'])) {
//                $query
//                    = ' select userid'
//                    .' from '.$GLOBALS['tables']['listuser']
//                    .' where listid in ('.implode(',', $msgdata['excludelist']).')';
//                if (VERBOSE) {
//                    processQueueOutput('Exclude query '.$query);
//                }
//                $req = Sql_Query($query);
//                while ($row = Sql_Fetch_Row($req)) {
//                    $um = Sql_Query(sprintf('replace into %s (entered,userid,messageid,status)
//                           values(now(),%d,%d,"excluded")',
//                        $tables['usermessage'], $row[0], $messageid));
//                }
//            }
//        }

        $stoppedEarly = $this->processSubscribersForCampaign($campaign, $subscribers, $cacheKey);

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
        if ($status === MessageStatus::InProcess && $message->getMetadata()->getSendStart() === null) {
            $message->getMetadata()->setSendStart(new DateTime());
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
        $processed = $this->messagePreparator->processMessageLinks(
            $campaign->getId(),
            $precachedContent,
            $subscriber
        );
        $processed->textContent = $this->userPersonalizer->personalize(
            $processed->textContent,
            $subscriber->getEmail(),
        );
        $processed->footer = $this->userPersonalizer->personalize($processed->footer, $subscriber->getEmail());

        try {
            $email = $this->rateLimitedCampaignMailer->composeEmail($campaign, $subscriber, $processed);
            $this->mailer->send($email);
            ($this->mailSizeChecker)($campaign, $email, $subscriber->hasHtmlEmail());
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

    private function handleAdminNotifications(Message $campaign, array $loadedMessageData, int $messageId): void
    {
        if (!empty($loadedMessageData['notify_start']) && !isset($loadedMessageData['start_notified'])) {
            $notifications = explode(',', $loadedMessageData['notify_start']);
            foreach ($notifications as $notification) {
                $email = $this->emailBuilder->buildPhplistEmail(
                    messageId: $campaign->getId(),
                    to: $notification,
                    subject: $this->translator->trans('Campaign started'),
                    message: $this->translator->trans(
                        'phplist has started sending the campaign with subject %subject%',
                        ['%subject%' => $loadedMessageData['subject']]
                    ),
                    inBlast: false,
                );

                if (!$email) {
                    continue;
                }

                // todo: check if from name should be from config
                $envelope = new Envelope(
                    sender: new Address($this->messageEnvelope, 'PHPList'),
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

            $messagePrecacheDto = $this->cache->get($cacheKey);
            if ($messagePrecacheDto === null) {
                throw new MessageCacheMissingException();
            }
            $this->handleEmailSending($campaign, $subscriber, $userMessage, $messagePrecacheDto);
        }

        return $stoppedEarly;
    }
}

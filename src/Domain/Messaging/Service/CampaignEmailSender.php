<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Exception\MessageSizeLimitExceededException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\EmailBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Sends a single campaign email to a subscriber and records the resulting UserMessage status,
 * including the side effects (suspend campaign, notify admins, unconfirm subscriber) that
 * specific failure modes require.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
class CampaignEmailSender
{
    public function __construct(
        private readonly EmailBuilder $campaignEmailBuilder,
        private readonly RateLimitedCampaignMailer $rateLimitedCampaignMailer,
        private readonly MailSizeChecker $mailSizeChecker,
        private readonly MessageProcessingPreparator $messagePreparator,
        private readonly MessageRepository $messageRepository,
        private readonly MessageStatusUpdater $messageStatusUpdater,
        private readonly SystemNotificationMailer $notificationMailer,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigProvider $configProvider,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(
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
            $this->messageStatusUpdater->update($campaign, MessageStatus::Suspended);
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);

            throw $e;
        } catch (AttachmentCopyException $e) {
            // stop after the first message if size is exceeded
            $this->messageStatusUpdater->update($campaign, MessageStatus::Suspended);
            $this->updateUserMessageStatus($userMessage, UserMessageStatus::NotSent);

            $this->notificationMailer->send(
                $campaign->getId(),
                $this->configProvider->getValue(ConfigOption::ReportAddress) ?? '',
                $this->translator->trans('phplist system error'),
                $this->translator->trans($e->getMessage()),
            );

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

    public function handleInvalidEmail(UserMessage $userMessage, Subscriber $subscriber, Message $campaign): void
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

    private function unconfirmSubscriber(Subscriber $subscriber): void
    {
        if ($subscriber->isConfirmed()) {
            $subscriber->setConfirmed(false);
            $this->entityManager->flush();
        }
    }

    private function updateUserMessageStatus(UserMessage $userMessage, UserMessageStatus $status): void
    {
        $userMessage->setStatus($status);
        $this->entityManager->flush();
    }
}

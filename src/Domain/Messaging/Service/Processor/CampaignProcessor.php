<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PhpList\Core\Domain\Messaging\Service\RateLimitedCampaignMailer;
use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CampaignProcessor
{
    private RateLimitedCampaignMailer $mailer;
    private EntityManagerInterface $entityManager;
    private SubscriberProvider $subscriberProvider;
    private MessageProcessingPreparator $messagePreparator;
    private LoggerInterface $logger;
    private UserMessageRepository $userMessageRepository;
    private MaxProcessTimeLimiter $timeLimiter;
    private RequeueHandler $requeueHandler;
    private TranslatorInterface $translator;
    private SubscriberHistoryManager $subscriberHistoryManager;

    public function __construct(
        RateLimitedCampaignMailer $mailer,
        EntityManagerInterface $entityManager,
        SubscriberProvider $subscriberProvider,
        MessageProcessingPreparator $messagePreparator,
        LoggerInterface $logger,
        UserMessageRepository $userMessageRepository,
        MaxProcessTimeLimiter $timeLimiter,
        RequeueHandler $requeueHandler,
        TranslatorInterface $translator,
        SubscriberHistoryManager $subscriberHistoryManager,
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->subscriberProvider = $subscriberProvider;
        $this->messagePreparator = $messagePreparator;
        $this->logger = $logger;
        $this->userMessageRepository = $userMessageRepository;
        $this->timeLimiter = $timeLimiter;
        $this->requeueHandler = $requeueHandler;
        $this->translator = $translator;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
    }

    public function process(Message $campaign, ?OutputInterface $output = null): void
    {
        $this->updateMessageStatus($campaign, MessageStatus::Prepared);
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);

        $this->updateMessageStatus($campaign, MessageStatus::InProcess);

        $this->timeLimiter->start();
        $stoppedEarly = false;

        foreach ($subscribers as $subscriber) {
            if ($this->timeLimiter->shouldStop($output)) {
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
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::InvalidEmailAddress);
                $this->unconfirmSubscriber($subscriber);
                $output?->writeln($this->translator->trans('Invalid email, marking unconfirmed: %email%', [
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
                continue;
            }

            $processed = $this->messagePreparator->processMessageLinks($campaign, $subscriber->getId());

            try {
                $email = $this->mailer->composeEmail($processed, $subscriber);
                $this->mailer->send($email);
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);
            } catch (Throwable $e) {
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::NotSent);
                $this->logger->error($e->getMessage(), [
                    'subscriber_id' => $subscriber->getId(),
                    'campaign_id' => $campaign->getId(),
                ]);
                $output?->writeln($this->translator->trans('Failed to send to: %email%', [
                    '%email%' => $subscriber->getEmail(),
                ]));
            }
        }

        if ($stoppedEarly && $this->requeueHandler->handle($campaign, $output)) {
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
}

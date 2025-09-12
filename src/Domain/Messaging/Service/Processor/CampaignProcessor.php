<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\SendRateLimiter;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

class CampaignProcessor
{
    private MailerInterface $mailer;
    private EntityManagerInterface $entityManager;
    private SubscriberProvider $subscriberProvider;
    private MessageProcessingPreparator $messagePreparator;
    private LoggerInterface $logger;
    private SendRateLimiter $rateLimiter;
    private UserMessageRepository $userMessageRepository;

    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        SubscriberProvider $subscriberProvider,
        MessageProcessingPreparator $messagePreparator,
        LoggerInterface $logger,
        SendRateLimiter $rateLimiter,
        UserMessageRepository $userMessageRepository
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->subscriberProvider = $subscriberProvider;
        $this->messagePreparator = $messagePreparator;
        $this->logger = $logger;
        $this->rateLimiter = $rateLimiter;
        $this->userMessageRepository = $userMessageRepository;
    }

    public function process(Message $campaign, ?OutputInterface $output = null): void
    {
        $this->updateMessageStatus($campaign, Message\MessageStatus::Prepared);
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);

        $this->updateMessageStatus($campaign, Message\MessageStatus::InProcess);

        foreach ($subscribers as $subscriber) {
            $existing = $this->userMessageRepository->findOneByUserAndMessage($subscriber, $campaign);
            if ($existing && $existing->getStatus() !== UserMessageStatus::Todo->value) {
                continue;
            }

            $userMessage = $existing ?? new UserMessage($subscriber, $campaign);
            $userMessage->setStatus(UserMessageStatus::Active);
            $this->entityManager->persist($userMessage);
            $this->entityManager->flush();

            $this->rateLimiter->awaitTurn($output);

            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::InvalidEmailAddress);
                continue;
            }

            $this->messagePreparator->processMessageLinks($campaign, $subscriber->getId());

            $email = (new Email())
                ->from('news@example.com')
                ->to($subscriber->getEmail())
                ->subject($campaign->getContent()->getSubject())
                ->text($campaign->getContent()->getTextMessage())
                ->html($campaign->getContent()->getText());

            try {
                $this->mailer->send($email);
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::Sent);
                $this->rateLimiter->afterSend();
            } catch (Throwable $e) {
                $this->updateUserMessageStatus($userMessage, UserMessageStatus::NotSent);
                $this->logger->error($e->getMessage(), [
                    'subscriber_id' => $subscriber->getId(),
                    'campaign_id' => $campaign->getId(),
                ]);
                $output?->writeln('Failed to send to: ' . $subscriber->getEmail());
            }
        }

        $this->updateMessageStatus($campaign, Message\MessageStatus::Sent);
    }

    private function updateMessageStatus(Message $message, Message\MessageStatus $status): void
    {
        $message->getMetadata()->setStatus($status);
        $this->entityManager->flush();
    }

    private function updateUserMessageStatus(UserMessage $userMessage, Message\UserMessageStatus $status): void
    {
        $userMessage->setStatus($status);
        $this->entityManager->flush();
    }}

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Common\IspRestrictionsProvider;
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
    private ?IspRestrictionsProvider $ispRestrictionsProvider;
    private ?int $mailqueueBatchSize;
    private ?int $mailqueueBatchPeriod;
    private ?int $mailqueueThrottle;

    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        SubscriberProvider $subscriberProvider,
        MessageProcessingPreparator $messagePreparator,
        LoggerInterface $logger,
        ?IspRestrictionsProvider $ispRestrictionsProvider = null,
        ?int $mailqueueBatchSize = null,
        ?int $mailqueueBatchPeriod = null,
        ?int $mailqueueThrottle = null,
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->subscriberProvider = $subscriberProvider;
        $this->messagePreparator = $messagePreparator;
        $this->logger = $logger;
        $this->ispRestrictionsProvider = $ispRestrictionsProvider;
        $this->mailqueueBatchSize = $mailqueueBatchSize;
        $this->mailqueueBatchPeriod = $mailqueueBatchPeriod;
        $this->mailqueueThrottle = $mailqueueThrottle;
    }

    public function process(Message $campaign, ?OutputInterface $output = null): void
    {
        $this->updateMessageStatus($campaign, Message\MessageStatus::Prepared);
        $ispRestrictions = $this->ispRestrictionsProvider->load();
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);

        $cfgBatch = ($this->mailqueueBatchSize ?? 0);
        $ispMax = isset($ispRestrictions->maxBatch) ? (int)$ispRestrictions->maxBatch : null;

        $cfgPeriod = ($this->mailqueueBatchPeriod ?? 0);
        $ispMinPeriod = ($ispRestrictions->minBatchPeriod ?? 0);

        $cfgThrottle = ($this->mailqueueThrottle ?? 0);
        $ispMinThrottle = (int)($ispRestrictions->minThrottle ?? 0);

        if ($cfgBatch <= 0) {
            $batchSize = $ispMax !== null ? max(0, $ispMax) : 0;
        } else {
            $batchSize = $ispMax !== null ? min($cfgBatch, max(1, $ispMax)) : $cfgBatch;
        }

        $batchPeriod = max(0, $cfgPeriod, $ispMinPeriod);

        $throttleSec = max(0, $cfgThrottle, $ispMinThrottle);

        $sentInBatch = 0;
        $batchStart = microtime(true);

        $this->updateMessageStatus($campaign, Message\MessageStatus::InProcess);

        foreach ($subscribers as $subscriber) {
            if ($batchSize > 0 && $batchPeriod > 0 && $sentInBatch >= $batchSize) {
                $elapsed = microtime(true) - $batchStart;
                $remaining = (int)ceil($batchPeriod - $elapsed);
                if ($remaining > 0) {
                    $output?->writeln(sprintf(
                        'Batch limit reached, sleeping %ds to respect MAILQUEUE_BATCH_PERIOD',
                        $remaining
                    ));
                    sleep($remaining);
                }
                $batchStart = microtime(true);
                $sentInBatch = 0;
            }

            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
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
                $sentInBatch++;
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage(), [
                    'subscriber_id' => $subscriber->getId(),
                    'campaign_id' => $campaign->getId(),
                ]);
                $output?->writeln('Failed to send to: ' . $subscriber->getEmail());
            }

            if ($throttleSec > 0) {
                sleep($throttleSec);
            }
        }

        $this->updateMessageStatus($campaign, Message\MessageStatus::Sent);
    }

    private function updateMessageStatus(Message $message, Message\MessageStatus $status): void
    {
        $message->getMetadata()->setStatus($status);
        $this->entityManager->flush();
    }
}

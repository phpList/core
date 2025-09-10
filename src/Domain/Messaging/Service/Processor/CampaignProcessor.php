<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
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

    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        SubscriberProvider $subscriberProvider,
        MessageProcessingPreparator $messagePreparator,
        LoggerInterface $logger,
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->subscriberProvider = $subscriberProvider;
        $this->messagePreparator = $messagePreparator;
        $this->logger = $logger;
    }

    public function process(Message $campaign, ?OutputInterface $output = null): void
    {
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);
        // phpcs:ignore Generic.Commenting.Todo
        // @todo check $ISPrestrictions logic
        foreach ($subscribers as $subscriber) {
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

                // phpcs:ignore Generic.Commenting.Todo
                // @todo log somewhere that this subscriber got email
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage(), [
                    'subscriber_id' => $subscriber->getId(),
                    'campaign_id' => $campaign->getId(),
                ]);
                $output?->writeln('Failed to send to: ' . $subscriber->getEmail());
            }

            usleep(100000);
        }

        $campaign->getMetadata()->setStatus(Message\MessageStatus::Sent);
        $this->entityManager->flush();
    }
}

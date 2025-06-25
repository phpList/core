<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

class ProcessQueueCommand extends Command
{
    protected static $defaultName = 'phplist:process-queue';

    private MessageRepository $messageRepository;
    private MailerInterface $mailer;
    private LockFactory $lockFactory;
    private EntityManagerInterface $entityManager;
    private SubscriberProvider $subscriberProvider;
    private MessageProcessingPreparator $messageProcessingPreparator;

    public function __construct(
        MessageRepository $messageRepository,
        MailerInterface   $mailer,
        LockFactory       $lockFactory,
        EntityManagerInterface $entityManager,
        SubscriberProvider $subscriberProvider,
        MessageProcessingPreparator $messageProcessingPreparator
    ) {
        parent::__construct();
        $this->messageRepository = $messageRepository;
        $this->mailer = $mailer;
        $this->lockFactory = $lockFactory;
        $this->entityManager = $entityManager;
        $this->subscriberProvider = $subscriberProvider;
        $this->messageProcessingPreparator = $messageProcessingPreparator;
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('queue_processor');
        if (!$lock->acquire()) {
            $output->writeln('Queue is already being processed by another instance.');

            return Command::FAILURE;
        }

        try {
            $this->messageProcessingPreparator->ensureSubscribersHaveUuid($output);
            $this->messageProcessingPreparator->ensureCampaignsHaveUuid($output);

            $campaigns = $this->messageRepository->findBy(['status' => 'submitted']);

            foreach ($campaigns as $campaign) {
                $this->processCampaign($campaign, $output);
            }
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }

    private function processCampaign(Message $campaign, OutputInterface $output): void
    {
        $subscribers = $this->subscriberProvider->getSubscribersForMessage($campaign);
        // phpcs:ignore Generic.Commenting.Todo
        // @todo check $ISPrestrictions logic
        foreach ($subscribers as $subscriber) {
            if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                continue;
            }

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
                $output->writeln('Failed to send to: ' . $subscriber->getEmail());
            }

            usleep(100000);
        }

        $campaign->getMetadata()->setStatus('sent');
        $this->entityManager->flush();
    }
}

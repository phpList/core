<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Console\Output\OutputInterface;

class MessageProcessingPreparator
{
    private EntityManagerInterface $entityManager;
    private SubscriberRepository $subscriberRepository;
    private MessageRepository $messageRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubscriberRepository $subscriberRepository,
        MessageRepository $messageRepository
    ) {
        $this->entityManager = $entityManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->messageRepository = $messageRepository;
    }

    public function ensureSubscribersHaveUuid(OutputInterface $output): void
    {
        $subscribersWithoutUuid = $this->subscriberRepository->findSubscribersWithoutUuid();
        
        $numSubscribers = count($subscribersWithoutUuid);
        if ($numSubscribers > 0) {
            $output->writeln(sprintf('Giving a UUID to %d subscribers, this may take a while', $numSubscribers));
            foreach ($subscribersWithoutUuid as $subscriber) {
                $subscriber->setUniqueId(bin2hex(random_bytes(16)));
            }
            $this->entityManager->flush();
        }
    }

    public function ensureCampaignsHaveUuid(OutputInterface $output): void
    {
        $campaignsWithoutUuid = $this->messageRepository->findCampaignsWithoutUuid();
        
        $numCampaigns = count($campaignsWithoutUuid);
        if ($numCampaigns > 0) {
            $output->writeln(sprintf('Giving a UUID to %d campaigns', $numCampaigns));
            foreach ($campaignsWithoutUuid as $campaign) {
                $campaign->setUuid(bin2hex(random_bytes(18)));
            }
            $this->entityManager->flush();
        }
    }
}

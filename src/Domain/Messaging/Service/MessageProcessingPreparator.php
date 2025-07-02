<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Console\Output\OutputInterface;

class MessageProcessingPreparator
{
    // phpcs:ignore Generic.Commenting.Todo
    // @todo: create functionality to track
    public const LINT_TRACK_ENDPOINT = '/api/v2/link-track';
    private EntityManagerInterface $entityManager;
    private SubscriberRepository $subscriberRepository;
    private MessageRepository $messageRepository;
    private LinkTrackService $linkTrackService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubscriberRepository $subscriberRepository,
        MessageRepository $messageRepository,
        LinkTrackService $linkTrackService
    ) {
        $this->entityManager = $entityManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->messageRepository = $messageRepository;
        $this->linkTrackService = $linkTrackService;
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

    /**
     * Process message content to extract URLs and replace them with link track URLs
     */
    public function processMessageLinks(Message $message, int $userId): Message
    {
        if (!$this->linkTrackService->isExtractAndSaveLinksApplicable()) {
            return $message;
        }

        $savedLinks = $this->linkTrackService->extractAndSaveLinks($message, $userId);

        if (empty($savedLinks)) {
            return $message;
        }

        $content = $message->getContent();
        $htmlText = $content->getText();
        $footer = $content->getFooter();

        if ($htmlText !== null) {
            $htmlText = $this->replaceLinks($savedLinks, $htmlText);
            $content->setText($htmlText);
        }

        if ($footer !== null) {
            $footer = $this->replaceLinks($savedLinks, $footer);
            $content->setFooter($footer);
        }

        return $message;
    }

    private function replaceLinks(array $savedLinks, string $htmlText): string
    {
        foreach ($savedLinks as $linkTrack) {
            $originalUrl = $linkTrack->getUrl();
            $trackUrl = '/' . self::LINT_TRACK_ENDPOINT . '?id=' . $linkTrack->getId();
            $htmlText = str_replace('href="' . $originalUrl . '"', 'href="' . $trackUrl . '"', $htmlText);
        }

        return $htmlText;
    }
}

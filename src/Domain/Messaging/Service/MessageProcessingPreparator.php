<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageProcessingPreparator
{
    // @todo: create functionality to track
    public const LINK_TRACK_ENDPOINT = '/api/v2/link-track';

    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly MessageRepository $messageRepository,
        private readonly LinkTrackService $linkTrackService,
        private readonly TranslatorInterface $translator,
        private readonly UserPersonalizer $userPersonalizer,
    ) {
    }

    public function ensureSubscribersHaveUuid(OutputInterface $output): void
    {
        $subscribersWithoutUuid = $this->subscriberRepository->findSubscribersWithoutUuid();

        $numSubscribers = count($subscribersWithoutUuid);
        if ($numSubscribers > 0) {
            $output->writeln($this->translator->trans('Giving a UUID to %count% subscribers, this may take a while', [
                '%count%' => $numSubscribers
            ]));
            foreach ($subscribersWithoutUuid as $subscriber) {
                $subscriber->setUniqueId(bin2hex(random_bytes(16)));
            }
        }
    }

    public function ensureCampaignsHaveUuid(OutputInterface $output): void
    {
        $campaignsWithoutUuid = $this->messageRepository->findCampaignsWithoutUuid();

        $numCampaigns = count($campaignsWithoutUuid);
        if ($numCampaigns > 0) {
            $output->writeln($this->translator->trans('Giving a UUID to %count% campaigns', [
                '%count%' => $numCampaigns
            ]));
            foreach ($campaignsWithoutUuid as $campaign) {
                $campaign->setUuid(bin2hex(random_bytes(18)));
            }
        }
    }

    /**
     * Process message content to extract URLs and replace them with link track URLs
     */
    public function processMessageLinks(Message $message, Subscriber $subscriber): Message
    {
        if (!$this->linkTrackService->isExtractAndSaveLinksApplicable()) {
            return $message;
        }

        $savedLinks = $this->linkTrackService->extractAndSaveLinks($message, $subscriber->getId());

        if (empty($savedLinks)) {
            return $message;
        }

        $content = $message->getContent();
        $htmlText = $content->getText();
        $footer = $content->getFooter();
        // todo: check other configured data that should be used in mail formatting/creation
        if ($htmlText !== null) {
            $htmlText = $this->replaceLinks($savedLinks, $htmlText);
            $htmlText = $this->userPersonalizer->personalize($htmlText, $subscriber->getEmail());
            $content->setText($htmlText);
        }

        if ($footer !== null) {
            $footer = $this->replaceLinks($savedLinks, $footer);
            $footer = $this->userPersonalizer->personalize($footer, $subscriber->getEmail());
            $content->setFooter($footer);
        }

        return $message;
    }

    private function replaceLinks(array $savedLinks, string $htmlText): string
    {
        foreach ($savedLinks as $linkTrack) {
            $originalUrl = $linkTrack->getUrl();
            $trackUrl = self::LINK_TRACK_ENDPOINT . '?id=' . $linkTrack->getId();
            $htmlText = str_replace('href="' . $originalUrl . '"', 'href="' . $trackUrl . '"', $htmlText);
        }

        return $htmlText;
    }
}

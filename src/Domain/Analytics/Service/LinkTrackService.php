<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use InvalidArgumentException;
use PhpList\Core\Core\ConfigProvider;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;
use PhpList\Core\Domain\Messaging\Model\Message;

class LinkTrackService
{
    private LinkTrackRepository $linkTrackRepository;
    private ConfigProvider $configProvider;

    public function __construct(LinkTrackRepository $linkTrackRepository, ConfigProvider $configProvider)
    {
        $this->linkTrackRepository = $linkTrackRepository;
        $this->configProvider = $configProvider;
    }

    public function getUrlById(int $id): ?string
    {
        $linkTrack = $this->linkTrackRepository->find($id);
        return $linkTrack?->getUrl();
    }

    public function isExtractAndSaveLinksApplicable(): bool
    {
        return (bool)$this->configProvider->get('click_track', false);
    }

    /**
     * Extract links from message content and save them to the LinkTrackRepository
     *
     * @return LinkTrack[] The saved LinkTrack entities
     * @throws InvalidArgumentException if the message doesn't have an ID
     */
    public function extractAndSaveLinks(Message $message, int $userId): array
    {
        if (!$this->isExtractAndSaveLinksApplicable()) {
            return [];
        }

        $content = $message->getContent();
        $messageId = $message->getId();

        if ($messageId === null) {
            throw new InvalidArgumentException('Message must have an ID');
        }

        $links = $this->extractLinksFromHtml($content->getText() ?? '');

        if ($content->getFooter() !== null) {
            $links = array_merge($links, $this->extractLinksFromHtml($content->getFooter()));
        }

        $links = array_unique($links);

        $savedLinks = [];

        foreach ($links as $url) {
            $existingLinkTrack = $this->linkTrackRepository->findByUrlUserIdAndMessageId($url, $userId, $messageId);
            if ($existingLinkTrack !== null) {
                $savedLinks[] = $existingLinkTrack;
                continue;
            }
            $linkTrack = new LinkTrack();
            $linkTrack->setMessageId($messageId);
            $linkTrack->setUserId($userId);
            $linkTrack->setUrl($url);

            $this->linkTrackRepository->save($linkTrack);
            $savedLinks[] = $linkTrack;
        }

        return $savedLinks;
    }

    /**
     * Extract links from HTML content
     *
     * @return string[] The extracted links
     */
    private function extractLinksFromHtml(string $html): array
    {
        $links = [];

        $pattern = '/<a\s+[^>]*href=(["\'])([^"\']+)\1[^>]*>/i';
        if (preg_match_all($pattern, $html, $matches)) {
            $links = $matches[2];
        }

        return $links;
    }
}

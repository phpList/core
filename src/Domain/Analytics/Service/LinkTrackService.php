<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Exception\MissingMessageIdException;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;

class LinkTrackService
{
    private LinkTrackRepository $linkTrackRepository;
    private ParameterProvider $paramProvider;

    public function __construct(LinkTrackRepository $linkTrackRepository, ParameterProvider $paramProvider)
    {
        $this->linkTrackRepository = $linkTrackRepository;
        $this->paramProvider = $paramProvider;
    }

    public function getUrlById(int $id): ?string
    {
        $linkTrack = $this->linkTrackRepository->find($id);
        return $linkTrack?->getUrl();
    }

    public function isExtractAndSaveLinksApplicable(): bool
    {
        return (bool)$this->paramProvider->get('click_track', false);
    }

    /**
     * Extract links from message content and save them to the LinkTrackRepository
     *
     * @return LinkTrack[] The saved LinkTrack entities
     * @throws MissingMessageIdException
     */
    public function extractAndSaveLinks(MessagePrecacheDto $content, int $userId, ?int $messageId = null): array
    {
        // todo: in case of forwarded message, we need to use 'forwarded' instead of  user id
        if (!$this->isExtractAndSaveLinksApplicable()) {
            return [];
        }

        if ($messageId === null) {
            throw new MissingMessageIdException();
        }

        $links = $this->extractLinksFromHtml($content->content ?? '');

        if ($content->htmlFooter) {
            $links = array_merge($links, $this->extractLinksFromHtml($content->htmlFooter));
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

            $this->linkTrackRepository->persist($linkTrack);
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

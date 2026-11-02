<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

/**
 * Replaces the links of a campaign email with click-tracking links, like phplist3's sendemaillib.php.
 *
 * Each distinct URL is stored once as a LinkTrackForward. The replacement link carries a token that identifies
 * the forward, the campaign and the subscriber (see LinkTrackTokenService); LinkClickService resolves it again.
 */
class LinkTrackService
{
    private const MAX_URL_LENGTH = 255;
    private const HTML_ANCHOR_PATTERN = '/(<a\s(?:[^>]*?[\s"\'])?)href=(["\'])(.*?)\2([^>]*>)(.*?)<\/a>/is';
    private const TEXT_URL_PATTERN = '#https?://[^\s<>},]+#i';
    private const PLACEHOLDER_PATTERN = '/\[[A-Z][A-Z0-9_]*(?::[^\]]*)?\]/';

    /** @var array<string, array{id: int, uuid: string}> forwards by url hash, kept for the worker's lifetime */
    private array $forwards = [];

    public function __construct(
        private readonly LinkTrackRepository $linkTrackRepository,
        private readonly LinkTrackForwardRepository $linkTrackForwardRepository,
        private readonly LinkTrackMlRepository $linkTrackMlRepository,
        private readonly MessageRepository $messageRepository,
        private readonly LinkTrackTokenService $tokenService,
        private readonly ParameterProvider $paramProvider,
    ) {
    }

    public function getUrlById(int $id): ?string
    {
        $linkTrack = $this->linkTrackRepository->find($id);
        return $linkTrack?->getUrl();
    }

    public function isClickTrackingEnabled(): bool
    {
        return (bool)$this->paramProvider->get('click_track', false);
    }

    /**
     * Replaces the links of the final HTML and text bodies with click-tracking links for this subscriber. It has to
     * run once all placeholders are expanded, so that template links and links coming from placeholders (unsubscribe,
     * preferences, ...) are tracked too, like phplist3 does.
     *
     * Links are left alone when they are not http(s)/ftp, are longer than the forward url column, still contain
     * placeholders (the redirect could not expand them), or are shown as text that itself contains a URL (the
     * rewrite would look like phishing).
     *
     * @return array{0: string, 1: string} the tracked HTML and text
     */
    public function trackLinks(string $html, string $text, int $campaignId, Subscriber $subscriber): array
    {
        $campaign = $this->isClickTrackingEnabled() ? $this->messageRepository->findById($campaignId) : null;
        if ($campaign === null) {
            return [$html, $text];
        }

        $messageUuid = (string) $campaign->getUuid();
        $subscriberUuid = $subscriber->getUuid();
        if (!$this->isClickTrackingEnabled() || $messageUuid === '' || $subscriberUuid === '') {
            return [$html, $text];
        }

        $forwardIds = [];
        $trackUrl = function (string $url, string $type) use ($messageUuid, $subscriberUuid, &$forwardIds): ?string {
            return $this->createTrackingUrl($url, $type, $messageUuid, $subscriberUuid, $forwardIds);
        };

        $html = $this->rewriteHtml($html, $trackUrl);
        $text = $this->rewriteText($text, $trackUrl);

        foreach (array_keys($forwardIds) as $forwardId) {
            $this->linkTrackMlRepository->incrementTotal($campaignId, $forwardId);
        }

        return [$html, $text];
    }

    /** @param callable(string, string): ?string $trackUrl */
    private function rewriteHtml(string $html, callable $trackUrl): string
    {
        if ($html === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            self::HTML_ANCHOR_PATTERN,
            function (array $match) use ($trackUrl): string {
                $linkText = strip_tags($match[5]);
                if (stripos($linkText, 'http://') !== false || stripos($linkText, 'https://') !== false) {
                    return $match[0];
                }

                $href = html_entity_decode(trim($match[3]), ENT_QUOTES | ENT_HTML5);
                $url = $trackUrl($href, LinkTrackToken::TYPE_HTML);
                if ($url === null) {
                    return $match[0];
                }

                return $match[1] . 'href=' . $match[2] . htmlspecialchars($url, ENT_QUOTES) . $match[2]
                    . $match[4] . $match[5] . '</a>';
            },
            $html
        );
    }

    /** @param callable(string, string): ?string $trackUrl */
    private function rewriteText(string $text, callable $trackUrl): string
    {
        if ($text === '') {
            return $text;
        }

        return (string) preg_replace_callback(
            self::TEXT_URL_PATTERN,
            function (array $match) use ($trackUrl): string {
                $trailing = str_ends_with($match[0], '.') ? '.' : '';
                $link = substr($match[0], 0, strlen($match[0]) - strlen($trailing));
                $url = $trackUrl($link, LinkTrackToken::TYPE_TEXT);

                return $url === null ? $match[0] : $url . $trailing;
            },
            $text
        );
    }

    /** @param array<int, true> $forwardIds collects the ids of the forwards used in the email */
    private function createTrackingUrl(
        string $url,
        string $type,
        string $messageUuid,
        string $subscriberUuid,
        array &$forwardIds,
    ): ?string {
        $personalise = false;
        $cleanUrl = $this->cleanUrl($url, $personalise);

        if (!preg_match('~^(https?|ftp)://~i', $cleanUrl)
            || strlen($cleanUrl) > self::MAX_URL_LENGTH
            || preg_match(self::PLACEHOLDER_PATTERN, $cleanUrl)
            || str_contains($cleanUrl, LinkTrackTokenService::ENDPOINT_PATH . '?tid=')
        ) {
            return null;
        }

        $forward = $this->getForward($cleanUrl, $personalise);
        $forwardIds[$forward['id']] = true;

        return $this->tokenService->createUrl(
            new LinkTrackToken($type, $forward['uuid'], $messageUuid, $subscriberUuid)
        );
    }

    /** @return array{id: int, uuid: string} */
    private function getForward(string $cleanUrl, bool $personalise): array
    {
        $urlHash = md5($cleanUrl);
        if (!isset($this->forwards[$urlHash])) {
            $forward = $this->linkTrackForwardRepository->findOrCreateByUrl($cleanUrl, $personalise);
            $this->forwards[$urlHash] = ['id' => (int) $forward->getId(), 'uuid' => (string) $forward->getUuid()];
        }

        return $this->forwards[$urlHash];
    }

    /**
     * Removes the personal `uid` and `PHPSESSID` query parameters, which must not end up in the stored url.
     * $personalise is set when a `uid` was present: the redirect then adds the subscriber's uid again.
     */
    private function cleanUrl(string $url, bool &$personalise): string
    {
        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $queryPosition = strpos($url, '?');
        if ($queryPosition === false) {
            return $url . $fragment;
        }

        $kept = [];
        foreach (explode('&', substr($url, $queryPosition + 1)) as $pair) {
            $name = strtolower(urldecode(explode('=', $pair, 2)[0]));
            if ($name === 'uid') {
                $personalise = true;
            } elseif ($name !== 'phpsessid' && $pair !== '') {
                $kept[] = $pair;
            }
        }

        return substr($url, 0, $queryPosition) . ($kept === [] ? '' : '?' . implode('&', $kept)) . $fragment;
    }
}

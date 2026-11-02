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
        private readonly LinkRewriter $linkRewriter,
        private readonly AnalyticsTrackingService $analyticsTrackingService,
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
     * Without click tracking, the analytics parameters (when given) are added straight onto the links instead, like
     * phplist3 does. With click tracking they are added when the link is followed, see LinkClickService.
     *
     * Forwarded messages are not click tracked, as their receivers are not necessarily subscribers.
     *
     * @param array<string, mixed>|null $analyticsParameters the campaign's analytics settings, null when it has
     *     no analytics tracking
     * @return array{0: string, 1: string} the tracked HTML and text
     */
    public function trackLinks(
        string $html,
        string $text,
        int $campaignId,
        Subscriber $subscriber,
        ?array $analyticsParameters = null,
        bool $forwarded = false,
    ): array {
        if (!$this->isClickTrackingEnabled()) {
            return $analyticsParameters === null
                ? [$html, $text]
                : $this->analyticsTrackingService->addToBodies($html, $text, $analyticsParameters);
        }

        $campaign = $forwarded ? null : $this->messageRepository->findById($campaignId);
        if ($campaign === null) {
            return [$html, $text];
        }

        $messageUuid = (string) $campaign->getUuid();
        $subscriberUuid = $subscriber->getUuid();
        if ($messageUuid === '' || $subscriberUuid === '') {
            return [$html, $text];
        }

        $forwardIds = [];
        $trackUrl = function (string $url, string $type) use ($messageUuid, $subscriberUuid, &$forwardIds): ?string {
            return $this->createTrackingUrl($url, $type, $messageUuid, $subscriberUuid, $forwardIds);
        };

        $html = $this->linkRewriter->rewriteHtml(
            $html,
            fn (string $href): ?string => $trackUrl($href, LinkTrackToken::TYPE_HTML)
        );
        $text = $this->linkRewriter->rewriteText(
            $text,
            fn (string $url): ?string => $trackUrl($url, LinkTrackToken::TYPE_TEXT)
        );

        foreach (array_keys($forwardIds) as $forwardId) {
            $this->linkTrackMlRepository->incrementTotal($campaignId, $forwardId);
        }

        return [$html, $text];
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

<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Domain\Analytics\Service\Query\AnalyticsQueryInterface;
use PhpList\Core\Domain\Analytics\Service\Query\GoogleAnalyticsQuery;
use PhpList\Core\Domain\Analytics\Service\Query\MatomoAnalyticsQuery;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;

/**
 * Adds the analytics query parameters (Google Analytics utm_*, Matomo pk_*) to the links of a campaign that has
 * "add analytics tracking code" set, like phplist3's analytics.php.
 */
class AnalyticsTrackingService
{
    public const FORMAT_HTML = 'HTML';
    public const FORMAT_TEXT = 'text';

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly MessageDataLoader $messageDataLoader,
        private readonly LinkRewriter $linkRewriter,
    ) {
    }

    /**
     * For a link that is followed: the campaign's own settings decide whether parameters are added.
     */
    public function addToClickedUrl(string $url, Message $campaign, bool $isHtml): string
    {
        $messageData = ($this->messageDataLoader)($campaign);
        if (empty($messageData['google_track'])) {
            return $url;
        }

        return $this->addToUrl($url, $isHtml ? self::FORMAT_HTML : self::FORMAT_TEXT, $messageData);
    }

    /**
     * For a message that is sent without click tracking: adds the parameters to all links.
     *
     * @param array<string, mixed> $messageData the campaign's analytics settings, see MessagePrecacheDto
     * @return array{0: string, 1: string} the HTML and the text
     */
    public function addToBodies(string $html, string $text, array $messageData): array
    {
        $html = $this->linkRewriter->rewriteHtml(
            $html,
            fn (string $url): string => $this->addToUrl($url, self::FORMAT_HTML, $messageData)
        );
        $text = $this->linkRewriter->rewriteText(
            $text,
            fn (string $url): string => $this->addToUrl($url, self::FORMAT_TEXT, $messageData)
        );

        return [$html, $text];
    }

    /**
     * Only http and https urls get parameters.
     *
     * @param array<string, mixed> $messageData
     */
    public function addToUrl(string $url, string $format, array $messageData): string
    {
        if (preg_match('/^https?:/i', $url) !== 1) {
            return $url;
        }

        $query = $this->getQuery();
        // a value the campaign never set gets its default, as phplist3's form would have saved it; a value that
        // was set to empty stays empty, which leaves that parameter out
        foreach ($query->editableParameters($messageData) as $name => $default) {
            if (!array_key_exists($name, $messageData)) {
                $messageData[$name] = $default;
            }
        }

        return $this->withParameters(
            $url,
            $query->trackingParameters($format, $messageData),
            $query->prefix()
        );
    }

    /**
     * Adds the parameters to the url, taking off tracking parameters that are already on it. Empty parameters are
     * not added. A fragment stays at the end.
     *
     * @param array<string, mixed> $trackingParameters
     */
    public function withParameters(string $url, array $trackingParameters, string $prefix): string
    {
        $fragment = '';
        $position = strpos($url, '#');
        if ($position !== false) {
            $fragment = substr($url, $position);
            $url = substr($url, 0, $position);
        }

        if (str_contains($url, $prefix)) {
            // the expression can leave a trailing ? or & behind
            $pattern = sprintf('/%s.+?=[^&]+(&|$)/', preg_quote($prefix, '/'));
            $url = rtrim((string) preg_replace($pattern, '', $url), '?&');
        }

        $trackingCode = http_build_query(
            array_filter($trackingParameters, static fn ($value): bool => $value !== null && $value !== '')
        );
        if ($trackingCode === '') {
            return $url . $fragment;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . $trackingCode . $fragment;
    }

    private function getQuery(): AnalyticsQueryInterface
    {
        return $this->configProvider->getValue(ConfigOption::AnalyticTracker) === 'matomo'
            ? new MatomoAnalyticsQuery()
            : new GoogleAnalyticsQuery();
    }
}

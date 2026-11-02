<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

/**
 * Finds the links in an HTML or text body and replaces them with the result of a callback.
 */
class LinkRewriter
{
    private const HTML_ANCHOR_PATTERN = '/(<a\s(?:[^>]*?[\s"\'])?)href=(["\'])(.*?)\2([^>]*>)(.*?)<\/a>/is';
    private const TEXT_URL_PATTERN = '#https?://[^\s<>},]+#i';

    /**
     * Rewrites the href of every anchor. Anchors whose visible text itself contains a URL are left alone, as
     * pointing them elsewhere would look like phishing.
     *
     * @param callable(string): ?string $rewrite gets the decoded href, returns the new url or null to keep the link
     */
    public function rewriteHtml(string $html, callable $rewrite): string
    {
        if ($html === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            self::HTML_ANCHOR_PATTERN,
            function (array $match) use ($rewrite): string {
                $linkText = strip_tags($match[5]);
                if (stripos($linkText, 'http://') !== false || stripos($linkText, 'https://') !== false) {
                    return $match[0];
                }

                $href = html_entity_decode(trim($match[3]), ENT_QUOTES | ENT_HTML5);
                $url = $rewrite($href);
                if ($url === null) {
                    return $match[0];
                }

                return $match[1] . 'href=' . $match[2] . htmlspecialchars($url, ENT_QUOTES) . $match[2]
                    . $match[4] . $match[5] . '</a>';
            },
            $html
        );
    }

    /**
     * Rewrites every http(s) URL in the text, leaving a trailing dot (the end of a sentence) where it is.
     *
     * @param callable(string): ?string $rewrite gets the url, returns the new url or null to keep it
     */
    public function rewriteText(string $text, callable $rewrite): string
    {
        if ($text === '') {
            return $text;
        }

        return (string) preg_replace_callback(
            self::TEXT_URL_PATTERN,
            function (array $match) use ($rewrite): string {
                $trailing = str_ends_with($match[0], '.') ? '.' : '';
                $link = substr($match[0], 0, strlen($match[0]) - strlen($trailing));
                $url = $rewrite($link);

                return $url === null ? $match[0] : $url . $trailing;
            },
            $text
        );
    }
}

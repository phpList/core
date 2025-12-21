<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;

class Html2Text
{
    private const WORD_WRAP = 70;

    public function __construct(private readonly ConfigProvider $configProvider)
    {
    }

    public function __invoke(string $html): string
    {
        $text = preg_replace("/\r/", '', $html);

        $text = preg_replace("/<script[^>]*>(.*?)<\/script\s*>/is", '', $text);
        $text = preg_replace("/<style[^>]*>(.*?)<\/style\s*>/is", '', $text);

        $text = preg_replace(
            "/<a[^>]*href=([\"\'])(.*)\\1[^>]*>(.*)<\/a>/Umis",
            "[URLTEXT]\\3[ENDURLTEXT][LINK]\\2[ENDLINK]\n",
            $text
        );
        $text = preg_replace("/<b>(.*?)<\/b\s*>/is", '*\\1*', $text);
        $text = preg_replace("/<h[\d]>(.*?)<\/h[\d]\s*>/is", "**\\1**\n", $text);
        $text = preg_replace("/<i>(.*?)<\/i\s*>/is", '/\\1/', $text);
        $text = preg_replace("/<\/tr\s*?>/i", "<\/tr>\n\n", $text);
        $text = preg_replace("/<\/p\s*?>/i", "<\/p>\n\n", $text);
        $text = preg_replace('/<br[^>]*?>/i', "<br>\n", $text);
        $text = preg_replace("/<br[^>]*?\/>/i", "<br\/>\n", $text);
        $text = preg_replace('/<table/i', "\n\n<table", $text);
        $text = strip_tags($text);

        // find all URLs and replace them back
        preg_match_all('~\[URLTEXT\](.*)\[ENDURLTEXT\]\[LINK\](.*)\[ENDLINK\]~Umis', $text, $links);
        foreach ($links[0] as $matchIndex => $fullMatch) {
            $linkText = $links[1][$matchIndex];
            $linkUrl = $links[2][$matchIndex];
            // check if the text linked is a repetition of the URL
            if (trim($linkText) == trim($linkUrl) ||
                'https://'.trim($linkText) == trim($linkUrl) ||
                'http://'.trim($linkText) == trim($linkUrl)
            ) {
                $linkReplace = $linkUrl;
            } else {
                //# if link is an anchor only, take it out
                if (str_starts_with($linkUrl, '#')) {
                    $linkReplace = $linkText;
                } else {
                    $linkReplace = $linkText.' <'.$linkUrl.'>';
                }
            }
            $text = str_replace($fullMatch, $linkReplace, $text);
        }
        $text = preg_replace(
            "/<a href=[\"\'](.*?)[\"\'][^>]*>(.*?)<\/a>/is",
            '[URLTEXT]\\2[ENDURLTEXT][LINK]\\1[ENDLINK]',
            $text,
            500
        );

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = preg_replace('/###NL###/', "\n", $text);
        $text = preg_replace("/\n /", "\n", $text);
        $text = preg_replace("/\t/", ' ', $text);

        // reduce whitespace
        while (preg_match('/  /', $text)) {
            $text = preg_replace('/  /', ' ', $text);
        }
        while (preg_match("/\n\s*\n\s*\n/", $text)) {
            $text = preg_replace("/\n\s*\n\s*\n/", "\n\n", $text);
        }
        $wordWrap = $this->configProvider->getValue(ConfigOption::WordWrap) ?? self::WORD_WRAP;

        return wordwrap($text, (int) $wordWrap);
    }
}

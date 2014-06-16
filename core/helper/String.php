<?php
/**
 * User: SaWey
 * Date: 15/12/13
 */

namespace phpList\helper;

use phpList\phpList;

/**
 * Class StringFunctions
 * Class containing string helper functions
 * @package phpList
 */
class String
{
    /**
     * Wrapper function for phpList::DB()->Sql_Escape();
     * @param string $var
     * @return string sqlEscaped string
     */
    public static function sqlEscape($var)
    {
        return phpList::DB()->sqlEscape($var);
    }

    /**
     * Normalize text
     * @param string $var
     * @return string normalized var
     */
    public static function normalize($var)
    {
        $var = str_replace(" ", "_", $var);
        $var = str_replace(";", "", $var);
        return $var;
    }

    /**
     * Clean the input string
     * @param string $value
     * @return string
     */
    public static function clean($value)
    {
        $value = trim($value);
        $value = preg_replace("/\r/", "", $value);
        $value = preg_replace("/\n/", "", $value);
        $value = str_replace('"', "&quot;", $value);
        $value = str_replace("'", "&rsquo;", $value);
        $value = str_replace("`", "&lsquo;", $value);
        $value = stripslashes($value);
        return $value;
    }

    /**
     * Clean out chars that make preg choke
     * primarily used for parsing the placeholders in emails.
     * @param string $name
     * @return string
     */
    public static function cleanAttributeName($name) {
        return str_replace(array('(', ')', '/', '\\', '*', '.'), '', $name);
    }

    public static function HTML2Text($text)
    {
        # strip HTML, and turn links into the full URL
        $text = preg_replace("/\r/", "", $text);

        #$text = preg_replace("/\n/","###NL###",$text);
        $text = preg_replace("/<script[^>]*>(.*?)<\/script\s*>/is", "", $text);
        $text = preg_replace("/<style[^>]*>(.*?)<\/style\s*>/is", "", $text);

        # would prefer to use < and > but the strip tags below would erase that.
        #  $text = preg_replace("/<a href=\"(.*?)\"[^>]*>(.*?)<\/a>/is","\\2\n{\\1}",$text,100);

        #  $text = preg_replace("/<a href=\"(.*?)\"[^>]*>(.*?)<\/a>/is","[URLTEXT]\\2[/URLTEXT][LINK]\\1[/LINK]",$text,100);

        $text = preg_replace(
            "/<a[^>]*href=[\"\'](.*)[\"\'][^>]*>(.*)<\/a>/Umis",
            "[URLTEXT]\\2[ENDURLTEXT][LINK]\\1[ENDLINK]\n",
            $text
        );
        $text = preg_replace("/<b>(.*?)<\/b\s*>/is", "*\\1*", $text);
        $text = preg_replace("/<h[\d]>(.*?)<\/h[\d]\s*>/is", "**\\1**\n", $text);
        #  $text = preg_replace("/\s+/"," ",$text);
        $text = preg_replace("/<i>(.*?)<\/i\s*>/is", "/\\1/", $text);
        $text = preg_replace("/<\/tr\s*?>/i", "<\/tr>\n\n", $text);
        $text = preg_replace("/<\/p\s*?>/i", "<\/p>\n\n", $text);
        $text = preg_replace("/<br[^>]*?>/i", "<br>\n", $text);
        $text = preg_replace("/<br[^>]*?\/>/i", "<br\/>\n", $text);
        $text = preg_replace("/<table/i", "\n\n<table", $text);
        $text = strip_tags($text);

        # find all URLs and replace them back
        preg_match_all('~\[URLTEXT\](.*)\[ENDURLTEXT\]\[LINK\](.*)\[ENDLINK\]~Umis', $text, $links);
        foreach ($links[0] as $matchindex => $fullmatch) {
            $linktext = $links[1][$matchindex];
            $linkurl = $links[2][$matchindex];
            # check if the text linked is a repetition of the URL
            if (trim($linktext) == trim($linkurl) ||
                'http://' . trim($linktext) == trim($linkurl)
            ) {
                $linkreplace = $linkurl;
            } else {
                ## if link is an anchor only, take it out
                if (strpos($linkurl, '#') !== false) {
                    $linkreplace = $linktext;
                } else {
                    $linkreplace = $linktext . ' <' . $linkurl . '>';
                }
            }
            #  $text = preg_replace('~'.preg_quote($fullmatch).'~',$linkreplace,$text);
            $text = str_replace($fullmatch, $linkreplace, $text);
        }
        $text = preg_replace(
            "/<a href=[\"\'](.*?)[\"\'][^>]*>(.*?)<\/a>/is",
            "[URLTEXT]\\2[ENDURLTEXT][LINK]\\1[ENDLINK]",
            $text,
            500
        );

        $text = String::replaceChars($text);

        $text = preg_replace("/###NL###/", "\n", $text);
        $text = preg_replace("/\n /", "\n", $text);
        $text = preg_replace("/\t/", " ", $text);

        # reduce whitespace
        while (preg_match("/  /", $text)) {
            $text = preg_replace("/  /", " ", $text);
        }
        while (preg_match("/\n\s*\n\s*\n/", $text)) {
            $text = preg_replace("/\n\s*\n\s*\n/", "\n\n", $text);
        }
        $text = wordwrap($text, 70);

        return $text;
    }

    public static function replaceChars($text)
    {
        // $document should contain an HTML document.
        // This will remove HTML tags, javascript sections
        // and white space. It will also convert some
        // common HTML entities to their text equivalent.

        $search = array(
            "'&(quot|#34);'i", // Replace html entities
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&rsquo;'i",
            "'&ndash;'i",
            "'&#(\d+);'e"
        ); // evaluate as php

        $replace = array(
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            "'",
            "-",
            "chr(\\1)"
        );

        $text = preg_replace($search, $replace, $text);


        # eze
        # $text = html_entity_decode ( $text , ENT_QUOTES , $GLOBALS['strCharSet'] );
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return $text;
    }

    public static function addAbsoluteResources($text, $url)
    {
        $parts = parse_url($url);
        $tags = array(
            'src\s*=\s*',
            'href\s*=\s*',
            'action\s*=\s*',
            'background\s*=\s*',
            '@import\s+',
            '@import\s+url\('
        );
        foreach ($tags as $tag) {
#   preg_match_all('/'.preg_quote($tag).'"([^"|\#]*)"/Uim', $text, $foundtags);
# we're only handling nicely formatted src="something" and not src=something, ie quotes are required
# bit of a nightmare to not handle it with quotes.
            preg_match_all('/(' . $tag . ')"([^"|\#]*)"/Uim', $text, $foundtags);
            for ($i = 0; $i < count($foundtags[0]); $i++) {
                $match = $foundtags[2][$i];
                $tagmatch = $foundtags[1][$i];
#      print "$match<br/>";
                if (preg_match("#^(http|javascript|https|ftp|mailto):#i", $match)) {
                    # scheme exists, leave it alone
                } elseif (preg_match("#\[.*\]#U", $match)) {
                    # placeholders used, leave alone as well
                } elseif (preg_match("/^\//", $match)) {
                    # starts with /
                    $text = preg_replace(
                        '#' . preg_quote($foundtags[0][$i]) . '#im',
                        $tagmatch . '"' . $parts["scheme"] . '://' . $parts["host"] . $match . '"',
                        $text,
                        1
                    );
                } else {
                    $path = '';
                    if (isset($parts['path'])) {
                        $path = $parts["path"];
                    }
                    if (!preg_match('#/$#', $path)) {
                        $pathparts = explode('/', $path);
                        array_pop($pathparts);
                        $path = join('/', $pathparts);
                        $path .= '/';
                    }
                    $text = preg_replace(
                        '#' . preg_quote($foundtags[0][$i]) . '#im',
                        $tagmatch . '"' . $parts["scheme"] . '://' . $parts["host"] . $path . $match . '"',
                        $text,
                        1
                    );
                }
            }
        }

        # $text = preg_replace('#PHPSESSID=[^\s]+
        return $text;
    }

    public static function removeJavascript($content) {
        $content = preg_replace('/<script[^>]*>(.*?)<\/script\s*>/mis','',$content);
        return $content;
    }

    public static function stripComments($content) {
        $content = preg_replace('/<!--(.*?)-->/mis','',$content);
        return $content;
    }

    public static function compressContent($content) {

        ## this needs loads more testing across systems to be sure
        return $content;

        /*
        $content = preg_replace("/\n/",' ',$content);
        $content = preg_replace("/\r/",'',$content);
        $content = removeJavascript($content);
        $content = stripComments($content);

        ## find some clean way to remove double spacing
        $content = preg_replace("/\t/",' ',$content);
        while (preg_match("/  /",$content)) {
            $content = preg_replace("/  /",' ',$content);
        }
        return $content;
         */
    }

} 
<?php


/**
 * Add a shortcut that seems common in other apps
 * function s($text)
 * @param $text string the text to find
 * @params 2-n variable - parameters to pass on to the sprintf of the text
 * @return string translated text with parameters filled in
 *
 *
 * eg s("This is a %s with a %d and a %0.2f","text",6,1.98765);
 *
 * will look for the translation of the string and substitute the parameters
 *
 **/
function s($text)
{
    $translation = \phpList\helper\Language::getDirect($text);

    ## allow overloading with sprintf paramaters
    if (func_num_args() > 1) {
        $args = func_get_args();
        array_shift($args);
        $translation = vsprintf($translation, $args);
    }
    return $translation;
}

/**
 * function snbr
 * similar to function s, but without overloading params
 * will return the translated text with spaces turned to &nbsp; so that they won't wrap
 * mostly useful for buttons
 */
function snbr($text)
{
    $translation = s($text);
    $translation = str_replace(' ', '&nbsp;', $translation);
    return $translation;
}

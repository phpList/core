<?php

namespace phpList\helper;

use phpList\Config;

class Language
{
    public $defaultlanguage = 'en';
    public $language = 'en';
    public $basedir = '';
    private $hasGettext = false;
    private $hasDB = false;
    private $_languages = [];

    protected $config;
    protected $db;

    public function __construct(Database $db, Config $config)
    {
        $this->config = $config;
        $this->db = $db;

        $this->basedir = dirname(__FILE__) . '/locale/';
        $this->defaultlanguage = $this->config->get('DEFAULT_SYSTEM_LANGUAGE');
        $this->language = $this->config->get('DEFAULT_SYSTEM_LANGUAGE');

        $languages = $this->getLanguages();
        if (isset($_SESSION['adminlanguage']) && isset($languages[$_SESSION['adminlanguage']['iso']])) {
            $this->language = $_SESSION['adminlanguage']['iso'];
        }
        if (function_exists('gettext')) {
            $this->hasGettext = true;
        }
        if (isset($_SESSION['hasI18Ntable'])) {
            $this->hasDB = $_SESSION['hasI18Ntable'];
        } elseif ($this->db->checkForTable('i18n')) {
            $_SESSION['hasI18Ntable'] = true;
            $this->hasDB = true;
        } else {
            $_SESSION['hasI18Ntable'] = false;
        }

        $this->config->runAfterLanguageInitialised($this);
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        $landir = dirname(__FILE__) . '/locale/';
        if (empty($this->_languages) && is_dir($landir)) {
            ## pick up languages from the lan directory
            $d = opendir($landir);
            while ($lancode = readdir($d)) {
                if (!in_array($landir, array_keys($this->_languages)) && is_dir($landir . '/' . $lancode) && is_file(
                    $landir . '/' . $lancode . '/language_info'
                )
                ) {
                    $lan_info = file_get_contents($landir . '/' . $lancode . '/language_info');
                    $lines = explode("\n", $lan_info);
                    $lan = [];
                    foreach ($lines as $line) {
                        // use utf8 matching
                        if (preg_match('/(\w+)=([\p{L}\p{N}&; \-\(\)]+)/u', $line, $regs)) {
                            $lan[$regs[1]] = $regs[2];
                        }
                    }
                    if (!isset($lan['gettext'])) {
                        $lan['gettext'] = $lancode;
                    }
                    if (!empty($lan['name']) && !empty($lan['charset'])) {
                        $this->_languages[$lancode] = [
                            $lan['name'],
                            $lan['charset'],
                            $lan['charset'],
                            $lan['gettext'],
                        ];
                    }
                }
            }

            ## pick up other languages from DB
            if ($this->db->tableExists('i18n')) {
                $query = $this->db->query(
                    sprintf(
                        'SELECT lan,translation FROM %s WHERE original = "language-name" AND lan NOT IN ("%s")',
                        $this->config->getTableName('i18n'),
                        join('","', array_keys($this->_languages))
                    )
                );
                $result = $this->db->query($query);
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    $this->_languages[$row['lan']] = [$row['translation'], 'UTF-8', 'UTF-8', $row['lan']];
                }
            }
            uasort($this->_languages, 'lanSort');
        }
        return $this->_languages;
    }

    public function gettext($text)
    {
        bindtextdomain('phplist', './locale');
        textdomain('phplist');

        /* gettext is a bit messy, at least on my Ubuntu 10.10 machine
         *
         * if eg language is "nl" it won't find it. It'll need to be "nl_NL";
         * also the Ubuntu system needs to have the language installed, even if phpList has it
         * it won't find it, if it's not on the system
         *
         * So, to e.g. get "nl" gettext support in phpList (on ubuntu, but presumably other linuxes), you'd have to do
         * cd /usr/share/locales
         * ./install-language-pack nl_NL
         * dpkg-reconfigure locales
         *
         * but when you use "nl_NL", the language .mo can still be in "nl".
         * However, it needs "nl/LC_MESSAGES/phplist.mo s, put a symlink LC_MESSAGES to itself
         *
         * the "utf-8" strangely enough needs to be added but can be spelled all kinds
         * of ways, eg "UTF8", "utf-8"
         *
         *
         * AND then of course the lovely Accept-Language vs gettext
         * https://bugs.php.net/bug.php?id=25051
         *
         * Accept-Language is lowercase and with - and gettext is country uppercase and with underscore
         *
         * More ppl have come across that: http://grep.be/articles/php-accept
         *
        */

        ## so, to get the mapping from "nl" to "nl_NL", use a gettext map in the related directory
        if (is_file(dirname(__FILE__) . '/locale/' . $this->language . '/gettext_code')) {
            $lan_map = file_get_contents(dirname(__FILE__) . '/locale/' . $this->language . '/gettext_code');
            $lan_map = trim($lan_map);
        } else {
            ## try to do "fr_FR", or "de_DE", might work in most cases
            ## hmm, not for eg fa_IR or zh_CN so they'll need the above file
            # http://www.gnu.org/software/gettext/manual/gettext.html#Language-Codes
            $lan_map = $this->language . '_' . strtoupper($this->language);
        }

        putenv('LANGUAGE=' . $lan_map . '.utf-8');
        setlocale(LC_ALL, $lan_map . '.utf-8');
        bind_textdomain_codeset('phplist', 'UTF-8');
        $gt = gettext($text);
        return ($gt && $gt != $text) ? $gt : '';
    }

    public function databaseTranslation($text)
    {
        if (!$this->hasDB) {
            return '';
        }
        $st = $this->db->prepare(sprintf(
            'select translation from %s where original = ? and lan = ?',
            $this->config->getTableName('i18n')
        ));
        $st->bindValue(1, $text);
        $st->bindValue(2, $this->language);
        $st->execute();

        return stripslashes($st->fetch());
    }

    public function pageTitle($page)
    {
        ## try gettext and otherwise continue
        if ($this->hasGettext) {
            $gettext = $this->gettext($page);
            if (!empty($gettext)) {
                return $gettext;
            }
        }
        $page_title = '';
        $dbTitle = $this->databaseTranslation('pagetitle:' . $page);
        if ($dbTitle) {
            $page_title = $dbTitle;
        } elseif (is_file(dirname(__FILE__) . '/locale/' . $this->language . '/pagetitles.php')) {
            include dirname(__FILE__) . '/locale/' . $this->language . '/pagetitles.php';
        } elseif (is_file(dirname(__FILE__) . '/lan/' . $this->language . '/pagetitles.php')) {
            include dirname(__FILE__) . '/lan/' . $this->language . '/pagetitles.php';
        }
        if (preg_match('/pi=([\w]+)/', $page, $regs)) {
            ## @@TODO call plugin to ask for title
            $title = $regs[1] . ' - ' . $page;
        } elseif (!empty($page_title)) {
            $title = $page_title;
        } else {
            $title = $page;
        }
        return $title;
    }

    public function pageTitleHover($page)
    {
        $dbTitle = $this->databaseTranslation('pagetitlehover:' . $page);
        if ($dbTitle) {
            $hoverText = $dbTitle;
        } else {
            $hoverText = $this->pageTitle($page);
            ## is this returns itself, wipe it, so the linktext is used instead
            if ($hoverText == $page) {
                $hoverText = '';
            }
        }
        if (!empty($hoverText)) {
            return $hoverText;
        }
        return '';
    }

    public function formatText($text)
    {
        # we've decided to spell phplist with uc L
        #todo: check if this is needed, maybe just correct all static text
        $text = str_ireplace('phplist', 'phpList', $text);
        $text = str_replace("\n", '', $text);

        if (DEBUG) {
            $text = '<span style="color:#A704FF">' . $text . '</span>';
        }
        return $text;
    }

    /**
     * obsolete
     */
    public function missingText($text)
    {
        if (DEBUG) {
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
            } else {
                $page = 'home';
            }
            $prefix = '';
            if (!empty($_GET['pi'])) {
                $pl = $_GET['pi'];
                $pl = preg_replace('/\W/', '', $pl);
                $prefix = $pl . '_';
            }

            $line = "'" . str_replace("'", "\'", $text) . "' => '" . str_replace("'", "\'", $text) . "',";

            $text = '<span style="color: #FF1717">' . $text . '</span>'; #MISSING TEXT
        }
        return $text;
    }

    public function appendText($file, $text)
    {
        return;
    }

    public function initFSTranslations($language = '')
    {
        if (empty($language)) {
            $language = $this->language;
        }
        $translations = parsePO(file_get_contents(dirname(__FILE__) . '/locale/' . $language . '/phplist.po'));
        $time = filemtime(dirname(__FILE__) . '/locale/' . $language . '/phplist.po');
        $this->updateDBtranslations($translations, $time, $language);
    }

    public function updateDBtranslations($translations, $time, $language = '')
    {
        if (empty($language)) {
            $language = $this->language;
        }
        if (sizeof($translations)) {
            foreach ($translations as $orig => $trans) {
                $this->db->replaceQuery(
                    $this->config->getTableName('i18n'),
                    ['lan' => $language, 'original' => $orig, 'translation' => $trans],
                    ''
                );
            }
        }
        $this->config->setDBConfig($this->db, 'lastlanguageupdate-' . $language, $time, 0);
    }

    public function getTranslation($text, $page, $basedir)
    {
        ## try DB, as it will be the latest
        if ($this->hasDB) {
            $db_trans = $this->databaseTranslation($text);
            if (!empty($db_trans)) {
                return $this->formatText($db_trans);
            } elseif (is_file(dirname(__FILE__) . '/locale/' . $this->language . '/phplist.po')) {
                if (function_exists('getConfig')) {
                    $lastUpdate = getConfig('lastlanguageupdate-' . $this->language);
                    $thisUpdate = filemtime(dirname(__FILE__) . '/locale/' . $this->language . '/phplist.po');
                    if ($thisUpdate > $lastUpdate && !empty($_SESSION['adminloggedin'])) {
                        ## we can't translate this, as it'll be recursive
                        $GLOBALS['pagefooter']['transupdate'] = '<script type="text/javascript">initialiseTranslation("Initialising phpList in your language, please wait.");</script>';
                    }
                }
                #$this->updateDBtranslations($translations,$time);
            }
        }

        ## next try gettext, although before that works, it requires loads of setting up
        ## but who knows
        if ($this->hasGettext) {
            $gettext = $this->gettext($text);
            if (!empty($gettext)) {
                return $this->formatText($gettext);
            }
        }

        $lan = $this->language;

        if (trim($text) == '') {
            return '';
        }
        if (strip_tags($text) == '') {
            return $text;
        }
        if (isset($lan[$text])) {
            return $this->formatText($lan[$text]);
        }
        if (isset($lan[strtolower($text)])) {
            return $this->formatText($lan[strtolower($text)]);
        }
        if (isset($lan[strtoupper($text)])) {
            return $this->formatText($lan[strtoupper($text)]);
        }

        return '';
    }

    /**
     * Get the translated text
     *
     * @param $text
     *
     * @return mixed|string
     */
    public function get($text)
    {
        if (trim($text) == '') {
            return '';
        }
        if (strip_tags($text) == '') {
            return $text;
        }
        $translation = '';

        $this->basedir = dirname(__FILE__) . '/lan/';
        if (isset($_GET['origpage']) && !empty($_GET['ajaxed'])) { ## used in ajaxed requests
            $page = basename($_GET['origpage']);
        } elseif (isset($_GET['page'])) {
            $page = basename($_GET['page']);
        } else {
            $page = 'home';
        }
        $page = preg_replace('/\W/', '', $page);

        ## if a plugin did not return the translation, find it in core
        if (empty($translation)) {
            $translation = $this->getTranslation($text, $page, $this->basedir);
        }

        # spelling mistake, retry with old spelling
        if ($text == 'over threshold, subscriber marked unconfirmed' && empty($translation)) {
            return $this->get('over treshold, subscriber marked unconfirmed');
        }

        if (!empty($translation)) {
            return $translation;
        } else {
            return $this->missingText($text);
        }
    }
}

<?php
/**
 * User: SaWey
 * Date: 19/12/13
 */

namespace phpList;


class Output {
    private static $_instance;
    private $shadecount = 0;

    private function __construct(){}

    /**
     * @return Output
     */
    public static function instance()
    {
        if (!Output::$_instance instanceof self) {
            Output::$_instance = new self();
        }
        return Output::$_instance;
    }

    /**
     * Output when on command line
     * @param string $message
     */
    public static function cl_output($message)
    {
        if (Config::get('commandline')) {
            @ob_end_clean();
            print Config::get('installation_name') . ' - ' . strip_tags($message) . "\n";
            @ob_start();
        }
    }

    /**
     * @param string|array $message
     * @param Timer $timer
     * @param int $logit
     * @param string $target
     */
    public static function output($message, $logit = 1, $target = 'summary')
    {
        if (is_array($message)) {
            $tmp = '';
            foreach ($message as $key => $val) {
                $tmp .= $key . '=' . $val . '; ';
            }
            $message = $tmp;
        }
        if (Config::get('commandline')) {
            Output::cl_output(
                strip_tags($message) . ' [' . Timer::get('PQC')->interval(true) . '] (' .
                Config::get('pagestats')["number_of_queries"] . ')'
            );
            $infostring = '[' . date('D j M Y H:i', time()) . '] [CL]';
        } else {
            $infostring = '[' . date('D j M Y H:i', time()) . '] [' . $_SERVER['REMOTE_ADDR'] . "]";
            #print "$infostring $message<br/>\n";
            $lines = explode("\n", $message);
            foreach ($lines as $line) {
                $line = preg_replace('/"/', '\"', $line);

                ## contribution in forums, http://forums.phplist.com/viewtopic.php?p=14648
                //Replace the "&rsquo;" which is not replaced by html_decode
                $line = preg_replace("/&rsquo;/", "'", $line);
                //Decode HTML chars
                $line = html_entity_decode($line, ENT_QUOTES, 'UTF-8');

                print "\n" . '<div class="output shade' . Output::instance()->shadecount . '">' . $line . '</div>';
                $line = str_replace("'", "\'", $line); // #16880 - avoid JS error
                printf(
                    '<script type="text/javascript">
                                                var parentJQuery = window.parent.jQuery;
                                                parentJQuery("#processqueue%s").append(\'<div class="output shade%s">%s</div>\');
                                                parentJQuery("#processqueue%s").animate({scrollTop:100000}, "slow");
                                              </script>',
                    $target,
                    (Output::instance()->shadecount) ? 1 : 0,
                    $line,
                    $target
                );

                Output::instance()->shadecount = !Output::instance()->shadecount;

                for ($i = 0; $i < 10000; $i++) {
                    print '  ';
                    if ($i % 100 == 0) print "\n";
                }
                @ob_flush();
                flush();
            }
            flush();
        }

        Logger::addToReport($infostring . ' ' . $message);
        if ($logit) {
            Logger::logEvent($message, 'processqueue');
        }

        flush();
    }
} 
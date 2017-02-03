<?php

namespace phpList\helper\Logger;

use phpList\Config;

class FileWriter implements LoggerWriter
{
    private $logfile = '/tmp/phplist.log';

    /**
     * FileWriter constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        if ($config->get('LOG_FOLDER') && $config->get('LOG_FILENAME')) {
            $this->logfile = $config->get('LOG_FOLDER') . $config->get('LOG_FILENAME');
        }
    }

    public function log($level, $message, array $context = [])
    {
        @$fp = fopen($this->logfile, 'a');

        $line = '[' . date('d M Y, H:i:s') . '] ' . $message;
        foreach ($context as $key => $item) {
            $line = $line . " | {$key} -  {$item} | ";
        }
        $line = $line . "\n";

        @fwrite($fp, $line);
        @fclose($fp);
    }
}

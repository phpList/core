<?php

namespace phpList\helper\Logger;

use phpList\Config;

class DatabaseWriter
{
    public function __construct(Config $config)
    {
        //
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

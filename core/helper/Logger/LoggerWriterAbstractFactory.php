<?php

namespace phpList\helper\Logger;

use phpList\Config;

class LoggerWriterAbstractFactory
{

    private $config;

    private $fileLogger = "file";
    private $databaseLogger = "database";

    /**
     * LoggerWriterAbstractFactory constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getLoggerWriter()
    {
        switch ($this->config->get("LOG_WRITER")) {
            case $this->fileLogger:
                return $this->makeFileLoggerWriter();
                break;

            case $this->databaseLogger:
                return $this->makeDatabaseLoggerWriter();
                break;

            default:
                throw new NotSuchWriterException();
                break;
        }
    }

    private function makeFileLoggerWriter()
    {
        return new FileWriter($this->config);
    }

    private function makeDatabaseLoggerWriter()
    {
        return new DatabaseWriter($this->config);
    }
}

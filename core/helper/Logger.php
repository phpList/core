<?php
namespace phpList\helper;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    private $report;

    public function __construct(){}

    private function logToDatabase($message, $page = 'unknown page')
    {
        $this->logToFile($message, $page);
        /* TODO: logger can't depend on database which depends on logger
        @$this->db->query(
            sprintf(
                'INSERT INTO %s (entered,page,entry)
                VALUES(CURRENT_TIMESTAMP, "%s", "%s")',
                $this->config->getTableName('eventlog', $page, $message)
            ),
            1
        );*/
    }

    private function logToFile($message, $page = 'unknown page')
    {
        //todo: change to config var?
        $logfile = './debug.log';
        $fp = @fopen($logfile, 'a');
        $line = '[' . date('d M Y, H:i:s') . '] ' . $page . ' - ' . $message . "\n";
        @fwrite($fp, $line);
        @fclose($fp);
    }

    //todo: remove below functions
    public function addToReport($text)
    {
        $this->report .= "\n$text";
    }

    public function getReport()
    {
        return $this->report;
    }


    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        $this->log(LogLevel::EMERGENCY, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function notice($message, array $context = array())
    {
        if(isset($context['page'])){
            $this->logToDatabase($message, $context['page']);
        }else{
            $this->logToDatabase($message);
        }

        //$this->log(LogLevel::NOTICE, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        if(isset($context['page'])){
            $this->logToFile($message, $context['page']);
        }else{
            $this->logToFile($message);
        }
        //$this->log(LogLevel::DEBUG, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        if(isset($context['page'])){
            $this->logToFile($message, $context['page']);
        }else{
            $this->logToFile($message);
        }
    }
}
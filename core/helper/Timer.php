<?php
namespace phpList\helper;

class Timer
{
    /**
     * @var Timer
     */
    private static $_instance;
    private $timers = array();
    private $start;
    private $previous = 0;

    function __construct()
    {
        $now = gettimeofday();
        $this->start = $now['sec'] * 1000000 + $now['usec'];
    }

    public function elapsed($seconds = false)
    {
        $now = gettimeofday();
        $end = $now['sec'] * 1000000 + $now['usec'];
        $elapsed = $end - $this->start;
        if ($seconds) {
            return sprintf('%0.10f', $elapsed / 1000000);
        } else {
            return sprintf('%0.10f', $elapsed);
        }
    }

    public function interval($seconds = false)
    {
        $now = gettimeofday();
        $end = $now['sec'] * 1000000 + $now['usec'];
        if (!$this->previous) {
            $elapsed = $end - $this->start;
        } else {
            $elapsed = $end - $this->previous;
        }
        $this->previous = $end;

        if ($seconds) {
            return sprintf('%0.10f', $elapsed / 1000000);
        } else {
            return sprintf('%0.10f', $elapsed);
        }
    }

    /**
     * Purpously not checking initialisation to save some time
     * @param string $timer the name of the timer to get
     * @return Timer
     */
    public static function get($timer)
    {
        return Timer::$_instance->timers[$timer];
    }

    /**
     * Start a timer
     * @param string $timer
     */
    public static function start($timer)
    {
        if (!Timer::$_instance instanceof self) {
            Timer::$_instance = new self();
        }
        if(!isset(Timer::$_instance->timers[$timer])){
            //create new timer instance with specified name
            Timer::$_instance->timers[$timer] = new Timer();
        }else{
            //reset timer if it already exists
            $now = gettimeofday();
            $t = Timer::$_instance->timers[$timer];
            $t->start = $now['sec'] * 1000000 + $now['usec'];
        }
    }
} 
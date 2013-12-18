<?php

namespace phpList;


class Timer
{
    var $start;
    var $previous = 0;

    function __construct()
    {
        $now = gettimeofday();
        $this->start = $now["sec"] * 1000000 + $now["usec"];
    }

    public function elapsed($seconds = false)
    {
        $now = gettimeofday();
        $end = $now["sec"] * 1000000 + $now["usec"];
        $elapsed = $end - $this->start;
        if ($seconds) {
            return sprintf('%0.10f', $elapsed / 1000000);
        } else {
            return sprintf('%0.10f', $elapsed);
        }
    }

    public function interval($seconds = 0)
    {
        $now = gettimeofday();
        $end = $now["sec"] * 1000000 + $now["usec"];
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
} 
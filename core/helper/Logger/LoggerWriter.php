<?php

namespace phpList\helper\Logger;


interface LoggerWriter
{

    public function log($level, $message, array $context = array());

}
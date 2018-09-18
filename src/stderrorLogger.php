<?php

namespace ahat\ScormPlayer;

class StderrorLogger implements LoggerInterface
{
    public function log( $message, $addNewline = true )
    {
        error_log( $message . ( $addNewline ? "\n" : '' ), 4 );
    }
}
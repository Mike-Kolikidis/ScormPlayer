<?php

namespace ahat\ScormPlayer;

/**
 * This class does nothing. It uses for uniformity purposes. It is returned by the LoggerProvider
 * when logging is disabled.
 */
class NullLogger implements LoggerInterface
{
    public function log( $message, $addNewline = true )
    {

    }
}
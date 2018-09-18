<?php

namespace ahat\ScormPlayer;

interface LoggerInterface
{
    public function log( $message, $addNewline = true );
}
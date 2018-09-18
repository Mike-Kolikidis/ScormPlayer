<?php

namespace ahat\ScormPlayer;

use ahat\ScormPlayer\StderrorLogger;
use ahat\ScormPlayer\CustomLogger;
use ahat\ScormPlayer\NullLogger;
use ahat\ScormPlayer\Configuration;

class LoggerProvider
{
    public static function getLogger()
    {
        if( !Configuration::$log ) {
            return new NullLogger;
        }

        if( Configuration::$logCustom ) {
            return new CustomLogger( Configuration::$customLogFile );
        }

        return new StderrorLogger;
    }
}
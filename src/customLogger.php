<?php

namespace ahat\ScormPlayer;

/**
 * Logger that writes to a custom file. Note that the file must exist and have appropriate permissions.
 * For example, for linux if the web server runs as www-data and the custom log file is /tmp/scorm.log
 * do touch /tmp/scorm.log && chgrp www-data /tmp/scorm.log && chmod g+w /tmp/scorm.log
 */
class CustomLogger implements LoggerInterface
{
    private $logFile;

    public function __construct( $logFile )
    {
        $this->logFile = $logFile;
    }

    public function log( $message, $addNewline = true )
    {
        error_log( $message . ( $addNewline ? "\n" : '' ), 3, $this->logFile );
    }
}
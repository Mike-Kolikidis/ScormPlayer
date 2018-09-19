<?php

namespace ahat\ScormPlayer;

class Configuration
{
    /**
     * These are the files that are serverd directly by the proxy. They are defined by file extension and the
     * content type to add to the response header.
     * NOTE: we serve json files directly instead of redirecting to a signed url because of cross-origin problems.
     * Adding the allow cross-origin header before redirecting does not help.
     * NOTE: html must come before htm or else the served object's name will change to .htm The same holds for any 
     * other extension, longer names must come before similar shorter ones e.g. json before js
     */
    public static $SERVED_FILES = array( 
        'html' => 'Content-type: text/html; charset=UTF-8', 
        'htm' => 'Content-type: text/html; charset=UTF-8', 
        'css' => 'Content-type: text/css; charset=UTF-8',
        'json' => 'Content-type: text/javascript; charset=UTF-8',
        'js' => 'Content-type: text/javascript; charset=UTF-8'
    );

    /**
     * If false, no logging will be performed
     */
    public static $log = false;

    /**
     * If true logs will be saved in the $customLogFile instead of the standard error.log for php
     */
    public static $logCustom = true;

    /**
     * The custom log file to use if $logCustom is true
     */
    public static $customLogFile = '/tmp/scorm.log';

    /**
     * The time period expresses as a string, after which JWT expires.
     * Examples '1 day', '2 weeks', '3 months', '4 years', '1 year + 1 day', '1 day + 12 hours', '3600 seconds'
     * See http://php.net/manual/en/dateinterval.createfromdatestring.php
     */
    public static $JWT_EXPIRATION_PERIOD = "1 day";

    /**
     * Goggle settings
     */
    public static $GOOGLE_CLOUD_STORAGE_BUCKET = 'scorm-214819.appspot.com';
    public static $GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE = '../packages-management@scorm-214819.iam.gserviceaccount.com.json';
    public static $GOOGLE_APPLICATION_CREDENTIALS = '../Scorm-9d50eec8f95f.json';

}
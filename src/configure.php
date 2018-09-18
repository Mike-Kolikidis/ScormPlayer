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
    public static $log = true;

    /**
     * If true logs will be saved in the $customLogFile instead of the standard error.log for php
     */
    public static $logCustom = true;

    /**
     * The custom log file to use if $logCustom is true
     */
    public static $customLogFile = '/tmp/scorm.log';

    public static $GOOGLE_CLOUD_STORAGE_BUCKET = 'scorm-214819.appspot.com';
    public static $GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE = '/home/antonis/Projects/learnworlds/ScormPlayer/packages-management@scorm-214819.iam.gserviceaccount.com.json';
    public static $GOOGLE_APPLICATION_CREDENTIALS = '/home/antonis/Projects/learnworlds/ScormPlayer/Scorm-9d50eec8f95f.json';

}
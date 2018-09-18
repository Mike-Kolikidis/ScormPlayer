<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use ahat\ScormPlayer\Proxy;

putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET=scorm-214819.appspot.com' );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE=/home/antonis/Projects/learnworlds/ScormPlayer/packages-management@scorm-214819.iam.gserviceaccount.com.json' );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=/home/antonis/Projects/learnworlds/ScormPlayer/Scorm-9d50eec8f95f.json' );

$requestHeaders = [
    'Content-type: text/html; charset=UTF-8'
];

$proxy = new Proxy( Request::createFromGlobals() );

$proxy->run();

// NOTE: the following lines will not run if the proxy decides that this must be a redirect

foreach ( $requestHeaders as $header ) {
    header( $header );
}

http_response_code( $proxy->getStatus() );

echo $proxy->getContent();

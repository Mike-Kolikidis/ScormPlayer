<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use ahat\ScormPlayer\Proxy;
use ahat\ScormPlayer\Configuration;

putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET='. Configuration::$GOOGLE_CLOUD_STORAGE_BUCKET );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE='. Configuration::$GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . Configuration::$GOOGLE_APPLICATION_CREDENTIALS );

$proxy = new Proxy( Request::createFromGlobals() );

$proxy->run();

if( $proxy->isRedirect() )
{
    ob_start();
    http_response_code( 302 );
    header('Location: '.$proxy->getRedirectUrl() );
    ob_end_flush();
    die();
}

// NOTE: the following lines will not run if the proxy decides that this must be a redirect
foreach ( $proxy->getHeaders() as $header ) {
    header( $header );
}

// Add allow cross-origin although this does not help with CROSS error for json files. Therefore these files are served directly
header( 'Access-Control-Allow-Origin: https://storage.googleapis.com' );

http_response_code( $proxy->getStatus() );

$errorPages = array( 400 => '400.html', 401 => '401.html', 403 => '403.html', 404 => '404.html',  );
if( array_key_exists( $proxy->getStatus(), $errorPages ) )
{
    include( $errorPages[ $proxy->getStatus() ] );
    die;
}

echo $proxy->getContent();

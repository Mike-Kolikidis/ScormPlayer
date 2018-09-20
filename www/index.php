<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use ahat\ScormPlayer\Proxy;
use ahat\ScormPlayer\Configuration;

putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET='. Configuration::$GOOGLE_CLOUD_STORAGE_BUCKET );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE='. Configuration::$GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . Configuration::$GOOGLE_APPLICATION_CREDENTIALS );

$request = Request::createFromGlobals();
$proxy = new Proxy( $request );

$proxy->run();

http_response_code( $proxy->getStatus() );

$errorPages = array( 400 => '400.html', 401 => '401.html', 403 => '403.html', 404 => '404.html',  );
if( array_key_exists( $proxy->getStatus(), $errorPages ) )
{
    include( $errorPages[ $proxy->getStatus() ] );
    die;
}

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

// Change the document domain so that it is the same as in the container frame minus the port, or else
// we get error "iframe from different origin trying to access another iframe".
// This is important because the embedded iframe where the scorm packages are loaded, attempts to access the LMS scripts in the container iframe
// where the demo (or actual) application exists.
// See https://stackoverflow.com/questions/23362842/access-control-allow-origin-not-working-for-iframe-withing-the-same-domain/23363050#23363050
// and Changing Origin in https://developer.mozilla.org/en-US/docs/Web/Security/Same-origin_policy
// Note: "document.domain = document.domain;" only gets rid of the port
$content = $proxy->getContent();
$url = $request->get( 'url' );
if( strpos( $url, '.html' ) !== false || strpos( $url, '.htm' ) !== false )
{
    $content = str_ireplace( '</head>', '<script>document.domain="'. parse_url( $_SERVER['HTTP_HOST'], PHP_URL_HOST) . '";</script></head>', $content );
}

echo $content;

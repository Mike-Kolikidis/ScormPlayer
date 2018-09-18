<?php

namespace ahat\ScormPlayer;

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Google\Cloud\Storage\StorageClient;
use Firebase\Auth\Token\Exception\InvalidToken;
use Kreait\Firebase\ServiceAccount;
use DateTime;

use ahat\ScormPlayer\Verifier;
use ahat\ScormPlayer\Configuration;
use ahat\ScormPlayer\LoggerProvider;

//debugging only
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;

class Proxy 
{
    private $request;
    private $content;
    private $headers;
    private $status;
    private $logger;

    public function __construct( Request $request )
    {
        $this->request = $request;
        $this->headers = array();
        $this->logger = LoggerProvider::getLogger();
    }

    public function run()
    {
        $objectName = $this->request->get( 'url' );
        if( !isset( $objectName ) ) {
            $this->status = 400;
            return;
        }

        $redirect = true;
        foreach( Configuration::$SERVED_FILES as $ext => $header ) {
            $this->logger->log( "Checking $objectName for ext $ext:" );
            if( strpos( $objectName, '.'.$ext ) !== false ) { // anything other than !== false will not work
                $objectName = substr( $objectName, 0, strpos( $objectName, '.'.$ext  ) ). '.' . $ext;
                $this->logger->log( "Ext $ext found. Objectname => $objectName" );
                
                $this->headers[] = $header;
                $this->logger->log( "Added header $header" );
                $redirect = false; // If this is one of the types of files we are serving, cancel the redirect
                
                break;
            }
        }        

        if( is_null( $objectName ) ) {
            $this->status = 400;
            $this->logger->log( "No object specified" );
            return;
        }

        $verifiedToken = $this->checkJWT();

        if( is_null( $verifiedToken ) ) {
            $this->logger->log( "JWT not verified" );
            return;
        }

        $folderId = '';
        $scormId = '';
        if( !is_null( $this->request->get( 'jwt' ) ) ) {
            $parts = explode( '/', $objectName, 3 );
            $folderId = $parts[0];
            $scormId = $parts[1];
            $filename = $parts[2];

            if( !$this->checkObjectPermissions( $verifiedToken, $folderId, $scormId ) ) {
                $this->logger->log( "$folderId/$scormId not allowed by JWT" );
                return;
            }

            //start new session
            $_SESSION[ 'folderId' ] = $folderId;
            $_SESSION[ 'scormId' ] = $scormId;
            $_SESSION[ 'jwt' ] = $this->request->get( 'jwt' );
        }
        else {
            //since scorm packages include only relative paths, subsequent requests will need to have
            //folderId and scormId prepended to the object's name
            $folderId = $verifiedToken->getClaim( 'claims' )->folderId;
            $scormId = $verifiedToken->getClaim( 'claims' )->scormId;

            $objectName = $folderId . '/' . $scormId . $objectName;
            $this->logger->log( "Will look for object $objectName" );
        }
        
        $storage = new StorageClient();
        $bucket = $storage->bucket( getenv( 'GOOGLE_CLOUD_STORAGE_BUCKET' ) );

        $object = $bucket->object( $objectName );

        if( !$object->exists() ) {
            $this->status = 404;
            $this->logger->log( "Object $objectName does not exist" );
            return;
        }

        if( $redirect )
        {
            $date = new DateTime( 'tomorrow' );
            $redirectUrl = $object->signedUrl( $date->getTimestamp(), ['method' => 'GET' ] );
            $this->logger->log( "Redirecting for url: $redirectUrl" );
            $this->redirect( $redirectUrl );
        }


        $this->logger->log( "Directly serving object: $objectName" );
        $this->content = $object->downloadAsString();
        $this->status = 200;
    }

    private function checkJWT()
    {        
        $jwt = null;

        if( !is_null( $this->request->get( 'jwt' ) ) ) {
            $jwt = $this->request->get( 'jwt' );
            $this->logger->log( "From request jwt: $jwt" );
        }
        else {
            $jwt = $_SESSION[ 'jwt' ];
            $this->logger->log( "From session jwt: $jwt" );
        }

        //If there is no jwt => ERROR
        if( is_null( $jwt ) ) {
            $this->status = 401;
            $this->headers[] = 'WWW-Authenticate: Bearer';
            $this->logger->log( "No JWT found in qeuerystring or session" );
            return null;
        }

        
        // $token = (new Parser())->parse($jwt);
        // $dbg = var_export($token, true);
        // $this->logger->log( "Verifying token: $dbg\n");

        $serviceAccount = ServiceAccount::fromJsonFile( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) );
        $publicCertificate = $this->getPublicCertificate( 
            $this->getKeyId( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ),   
            getenv( 'GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE' )
        );

        try {
            $verifier = new Verifier( $serviceAccount->getClientEmail(), $publicCertificate );
            return $verifier->verifyIdToken( $jwt );
        } catch ( InvalidToken $e ) {
            $this->logger->log( "Invalid jwt ". $e->getMessage() );
            $this->status = 403;
            $this->headers[] = 'WWW-Authenticate: Bearer';
            return null;
        }
    }

    private function checkObjectPermissions( $verifiedToken, $folderId, $scormId )
    {
        $claims = $verifiedToken->getClaim( 'claims' );

        if( $folderId == $claims->folderId && $scormId == $claims->scormId ) {
            return true;
        }

        $this->logger->log( "Invalid $folderId/$scormId against claims " . $claims->folderId . '/' . $claims->scormId );
        $this->status = 403;
        $this->headers[] = 'WWW-Authenticate: Bearer';
        return false;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getStatus()
    {
        return $this->status;
    }
    
    public function getContent()
    {
        return $this->content;
    }

    private function redirect( $url ) 
    {
        ob_start();
        header('Location: '.$url);
        ob_end_flush();
        die();
    }

    private function getKeyId( $jsonFile )
    { 
        $string = file_get_contents( $jsonFile );
        $data = json_decode( $string, true );
        return $data[ 'private_key_id' ];
    }

    private function getPublicCertificate( $keyId, $jsonFile )
    {
        $string = file_get_contents( $jsonFile );
        $data = json_decode($string, true);
        return $data[ $keyId ];
    }
}
<?php

namespace ahat\ScormPlayer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Google\Cloud\Storage\StorageClient;
use Firebase\JWT\JWT;
use Firebase\Auth\Token\Exception\InvalidToken;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use DateTime;

use ahat\ScormPlayer\Verifier;

//debugging only
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;

class Proxy 
{
    private static $SERVED_FILES = array( 'html', 'htm', 'css', 'json', 'js' ); //NOTE: html must come before htm or else the served object's name will change to .htm
    private $request;
    private $content;
    private $headers = array();
    private $status;

    public function __construct( Request $request )
    {
        $this->request = $request;
    }

    public function run()
    {
        $objectName = $this->request->get( 'url' );
        if( !isset( $objectName ) ) {
            $this->status = 400;
            return;
        }


        $redirect = true;
        foreach( Proxy::$SERVED_FILES as $ext ) {
            // file_put_contents("php://stdout", "\nChecking ext $ext: " );
            error_log( "Checking $objectName for ext $ext: ", 4 );
            error_log( "Checking $objectName for ext $ext: ", 3, '/tmp/scorm.log' );
            if( strpos( $objectName, '.'.$ext ) !== false ) { // anything other than !== false will not work
                $objectName = substr( $objectName, 0, strpos( $objectName, '.'.$ext  ) ). '.' . $ext;
                // file_put_contents("php://stdout", "\nobjectName => $objectName\n" );
                error_log( "Ext $ext found. Objectname => $objectName\n", 4 );
                error_log( "Ext $ext found. Objectname => $objectName\n", 3, '/tmp/scorm.log' );
                // If this is one of the types of files we are serving, cancel the redirect
                $redirect = false;
                
                break;
            }
        }        

        // file_put_contents("php://stdout", "\nObjectName: $objectName\n");

        if( is_null( $objectName ) ) {
            $this->status = 400;
            return;
        }

        $verifiedToken = $this->checkJWT();

        // file_put_contents("php://stdout", "verifiedToken: $verifiedToken\n");

        if( is_null( $verifiedToken ) ) {            
            return;
        }

        $folderId = '';
        $scormId = '';
        // if( !$this->request->hasPreviousSession() ) {
        // if( !isset( $_SESSION[ 'jwt' ] ) ) {
        if( !is_null( $this->request->get( 'jwt' ) ) ) {
            $parts = explode( '/', $objectName, 3 );
            $folderId = $parts[0];
            $scormId = $parts[1];
            $filename = $parts[2];

            if( !$this->checkObjectPermissions( $verifiedToken, $folderId, $scormId ) ) {
                return;
            }

            $_SESSION[ 'folderId' ] = $folderId;
            $_SESSION[ 'scormId' ] = $scormId;
            $_SESSION[ 'jwt' ] = $this->request->get( 'jwt' );
            //start new session
            // $session = new Session();
            // $session->start();
            // $session->set( 'folderId', $folderId );
            // $session->set( 'scormId', $scormId );
            // $session->set( 'jwt', $this->request->get( 'jwt' ) );
            // $session->save();
    
            // $this->request->setSession( $session );
        }
        else {
            //since scorm packages include only relative paths, subsequent requests will need to have
            //folderId and scormId prepended to the object's name
            $folderId = $verifiedToken->getClaim( 'claims' )->folderId;
            $scormId = $verifiedToken->getClaim( 'claims' )->scormId;

            $objectName = $folderId . '/' . $scormId . $objectName;

            error_log( "Will look for object $objectName\n", 4 );
            error_log( "Will look for object $objectName\n", 3, '/tmp/scorm.log' );
        }
        
        $storage = new StorageClient();
        $bucket = $storage->bucket( getenv( 'GOOGLE_CLOUD_STORAGE_BUCKET' ) );

        // foreach ($bucket->objects() as $object) {
        //     file_put_contents("php://stdout", "\n\tObject: $object->name()\n" );
        // }

        $object = $bucket->object( $objectName );

        if( !$object->exists() ) {
            $this->status = 404;
            error_log( "Object $objectName does not exist\n", 4 );
            error_log( "Object $objectName does not exist\n", 3, '/tmp/scorm.log' );
            return;
        }

        if( $redirect )
        {
            $date = new DateTime( 'tomorrow' );
            $redirectUrl = $object->signedUrl( $date->getTimestamp(), ['method' => 'GET' ] );
            error_log( "Redirecting for url: $redirectUrl\n", 4 );
            error_log( "Redirecting for url: $redirectUrl\n", 3, '/tmp/scorm.log' );
            $this->redirect( $redirectUrl );
        }


        error_log( "Directly serving object :$objectName\n", 4 );
        error_log( "Directly serving object :$objectName\n", 3, '/tmp/scorm.log' );
        $this->content = $object->downloadAsString();
        // file_put_contents("php://stdout", "\nContents of $objectName:\n".$this->content."\n");
        $this->status = 200;
    }

    private function checkJWT()
    {        
        $jwt = null;

        // if( !$this->request->hasPreviousSession() ) {
        // if( !isset( $_SESSION[ 'jwt' ] ) ) {
        if( !is_null( $this->request->get( 'jwt' ) ) ) {
            $jwt = $this->request->get( 'jwt' );
            error_log( "From request jwt: $jwt\n", 4 );
            error_log( "From request jwt: $jwt\n", 3, '/tmp/scorm.log' );
        }
        else {
            // $jwt = $this->request->getSession()->get( 'jwt' );
            $jwt = $_SESSION[ 'jwt' ];
            error_log( "From session jwt: $jwt\n", 4 );
            error_log( "From session jwt: $jwt\n", 3, '/tmp/scorm.log' );
        }

        //If there is no jwt => ERROR
        if( is_null( $jwt ) ) {
            $this->status = 401;
            $this->headers[] = 'WWW-Authenticate: Bearer';
            return null;
        }

        
        $token = (new Parser())->parse($jwt);
        // $dbg = var_export($token, true);
        // file_put_contents("php://stdout", "\nVerifying jwt: \n\t$jwt\ntoken: $dbg\n");

        $serviceAccount = ServiceAccount::fromJsonFile( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) );
        $publicCertificate = $this->getPublicCertificate( 
            $this->getKeyId( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ),   
            getenv( 'GOOGLE_APPLICATION_CREDENTIALS_PUBLIC_CERTIFICATE' )
        );

        // file_put_contents("php://stdout", "\nPublic Certificate: \n\t$publicCertificate\n");
        try {
            // return $firebase->getAuth()->verifyIdToken( $jwt );
            $verifier = new Verifier( $serviceAccount->getClientEmail(), $publicCertificate );
            return $verifier->verifyIdToken( $jwt );
        } catch ( InvalidToken $e ) {
            // file_put_contents("php://stdout", "\nInvalid token: ".$e->getMessage()."\n");
            error_log( "Invalid jwt ".$e->getMessage()."\n", 4 );
            error_log( "Invalid jwt ".$e->getMessage()."\n", 3, '/tmp/scorm.log' );
            $this->status = 403;
            $this->headers[] = 'WWW-Authenticate: Bearer';
            return null;
        }
    }

    private function checkObjectPermissions( $verifiedToken, $folderId, $scormId )
    {
        $claims = $verifiedToken->getClaim( 'claims' );
        $dbg = var_export( $claims, true );
        // file_put_contents("php://stdout", "\nClaims: $dbg\n");

        if( $folderId == $claims->folderId && $scormId == $claims->scormId ) {
            return true;
        }

        error_log( "Invalid $folderId/$scormId against claims " . $claims->folderId . '/' . $claims->scormId ."\n", 4 );
        error_log( "Invalid $folderId/$scormId against claims " . $claims->folderId . '/' . $claims->scormId ."\n", 3, '/tmp/scorm.log' );
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
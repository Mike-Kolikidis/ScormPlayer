<?php

require_once __DIR__ . '/../vendor/autoload.php';

// use Google\Cloud\Storage\StorageClient;
use Firebase\Auth\Token\Generator as CustomTokenGenerator;
use Kreait\Firebase\Value\Uid;
use Kreait\Firebase\ServiceAccount;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ahat\ScormDemo\Configuration;
use ahat\ScormDemo\GCSClass;

putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET='. Configuration::$GOOGLE_CLOUD_STORAGE_BUCKET );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . Configuration::$GOOGLE_APPLICATION_CREDENTIALS );

$serviceAccount = ServiceAccount::fromJsonFile( Configuration::$GOOGLE_APPLICATION_CREDENTIALS );
$uid = 'anything here';
$uid = $uid instanceof Uid ? $uid : new Uid($uid);
$tokenGenerator = new CustomTokenGenerator(
    $serviceAccount->getClientEmail(),
    $serviceAccount->getPrivateKey());

$gcs = new GCSClass( getenv( 'GOOGLE_CLOUD_STORAGE_BUCKET' ) );

//get the folders
$folders = $gcs->listFolders();
foreach( $folders as $folder )
{
    //get the packages in the folder
    $packages = $gcs->listPackages( $folder );

    foreach( $packages as $package )
    { 
        // create a jwt for each package
        $additionalClaims = [
            'folderId' => $folder,
            'scormId' => $package
        ];
        $customToken = $tokenGenerator->createCustomToken($uid, $additionalClaims);
        $jwt = (string) $customToken;
                
        //find the launcher
        $launcher = '';
        $scorm = "$folder/$package/imsmanifest.xml";
        $captivate = "$folder/$package/project.txt";
        if( $gcs->objectExists( $scorm ) )
        {
            $manifest = $gcs->downloadObjectAsString( $scorm );
            $xml = simplexml_load_string( $manifest );
            $launcher = (string) $xml->resources->resource->attributes()->href;
        }
        elseif( $gcs->objectExists( $captivate ) )
        {
            $project = $gcs->downloadObjectAsString( $captivate );
            $json = json_decode( $project, true );
            $launcher = $json['metadata']['launchFile'];
        }

        $url = Configuration::$PROXY_ADDRESS . "?url=$folder/$package/$launcher&jwt=$jwt";
        ?>
        <a href="<?php echo $url; ?>"><?php echo "$folder/$package"?></a>
        <?php
    
    }
}

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
  <title>Untitled 1</title>

</head>
<script type="text/javascript" src="SCORMRuntime.js"></script>

<body>

<?php

require_once __DIR__ . '/../vendor/autoload.php';

// use Google\Cloud\Storage\StorageClient;
use Firebase\Auth\Token\Generator as CustomTokenGenerator;
use Kreait\Firebase\Value\Uid;
use Kreait\Firebase\ServiceAccount;

use ahat\ScormDemo\Configuration;
use ahat\ScormDemo\GCSClass;

putenv( 'GOOGLE_CLOUD_STORAGE_BUCKET='. Configuration::$GOOGLE_CLOUD_STORAGE_BUCKET );
putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . Configuration::$GOOGLE_APPLICATION_CREDENTIALS );

$serviceAccount = ServiceAccount::fromJsonFile( __DIR__ . '/' . Configuration::$GOOGLE_APPLICATION_CREDENTIALS );
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
        <a href="#" onclick="document.getElementById('scorm').src='<?php echo $url; ?>'"><?php echo "$folder/$package"?></a></br>
        <?php

        // create an invalid jwt to demonstrate access denied. 'invalid folder', 'invalid package'
        // must not be the actual names of the current folder and package or else this will be a valid jwt
        $additionalClaims = [
            'folderId' => 'invalid folder',
            'scormId' => 'invalid package'
        ];
        $customToken = $tokenGenerator->createCustomToken($uid, $additionalClaims);
        $jwt = (string) $customToken;
        $invalidUrl = Configuration::$PROXY_ADDRESS . "?url=$folder/$package/$launcher&jwt=$jwt";
        ?>
        <a href="#" onclick="document.getElementById('scorm').src='<?php echo $invalidUrl; ?>'"><?php echo "$folder/$package"?></a> (invalid)</br>
        <?php
    
    }
}

?>

  <hr>
  <iframe id="scorm" src="about:blank" width="1000" height="450" frameborder="0" scrolling="no"></iframe>

</body>

</html>


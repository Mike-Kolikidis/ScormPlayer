<?php

namespace ahat\ScormDemo;

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RecursiveTreeIterator;
use DateTime;

class GCSClass
{

    private $bucketName = null;

    public function __construct( $bucketName )
    {
        $this->bucketName = $bucketName;
    }


    /**
     * Lists all the objects contained in the bucket specified in the contructor of this class
     * 
     * @return array of strings The names of the objects in the bucket
     */
    public function listBucket()
    {
        $contents = array();
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        foreach ($bucket->objects() as $object) {
            printf( 'Object: %s' . PHP_EOL, $object->name() );
            $contents[] = $object->name();
        }
        return $contents;
    }

    /**
     * Returns an array with folder names (i.e. 1st level subfolders in the bucket).
     * Folders are matches returned by a regular expression searching for strings between the start of the filenaem
     * first subdirectory delimit '/'
     * 
     * @return array of strings, the names of the packages (1st level subfolders) of $folderId
     */
    public function listFolders()
    {
        $folders = array();

        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );

        foreach ($bucket->objects() as $object) {
            // printf( 'Object: %s' . PHP_EOL, $object->name() );
            $str = $object->name();
            if ( preg_match('/(.*?)\//', $str, $match ) == 1 ) {
                if ( !in_array( $match[1], $folders ) ) {
                    $folders[] = $match[1];
                    // echo $match[1] . "\n";
                }
            }
        }

        return $folders;
    }


    /**
     * Returns an array of the files contained in a folder. This includes all files in all subfolders
     * with their full path
     * 
     * @param string $folderId The GCS folder to list
     * 
     * @return array of filenames
     */
    public function listFolder( $folderId )
    {
        $files = array();
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $options = [ 'prefix' => $folderId ];
        foreach ($bucket->objects($options) as $object) {
            // printf( 'Object: %s' . PHP_EOL, $object->name() );
            $files[] = $object->name();
        }

        return $files;
    }


    /**
     * Returns an array with package names (i.e. 1st level subfolders of $folderId ).
     * Packages are matches returned by a regular expression searching for strings between the
     * first two subdirectory delimits '/'
     * 
     * @param string $folderId The name of the GCS folder where to look for packages (i.e. subfolders)
     * 
     * @return array of strings, the names of the packages (1st level subfolders) of $folderId
     */
    public function listPackages( $folderId )
    {
        $files = $this->listFolder( $folderId );

        $packages = array();

        foreach( $files as $file ) {
            // echo "$file\n";
            $str = $file;
            if ( preg_match('/\/(.*?)\//', $str, $match ) == 1 ) {
                if ( !in_array( $match[1], $packages ) ) {
                    $packages[] = $match[1];
                    // echo $match[1] . "\n";
                }
            }
        }

        return $packages;
    }

    /**
     * Uploads an unzipped package (i.e. a local folder) to GCS
     * 
     * @param string $folderId The root folder where to store the package
     * @param string $packageName The package name (the path of the local folder) to upload
     * 
     * @return integer Number of objects uploaded
     */
    public function uploadPackage( $folderId, $scormId, $packageName )
    {
        $storage = new StorageClient();
        
        $bucket = $storage->bucket( $this->bucketName );

        $path = realpath( $packageName );

        $objects = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ) );
        $count = 0;
        foreach($objects as $name => $object){
            $count ++;
            $file = fopen( $name, 'r' );
            $name = substr( $name, strlen( $path ) );
            $objectName = $folderId . DIRECTORY_SEPARATOR . $scormId . $name;
            // echo "$objectName\n";
            $object = $bucket->upload($file, [ 'name' => $objectName ]);            
        }

        return $count;
    }

    /**
     * Removes a package given a folder and a package (i.e. subfolder) name. In order to completely
     * remove a folder pass the empty string '' as $packageName
     * 
     * @param string $folderId The GCS folder where the package subfolder is located
     * @param string $packageName The package name i.e. GCS subfolder to remove
     * 
     * @return integer Number of objects deleted
     */
    public function removePackage( $folderId, $scormId )
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $options = ['prefix' => $folderId . '/' . $scormId ];

        $count = 0;
        foreach ( $bucket->objects( $options ) as $object ) {
            $count ++;
            // printf( 'Object: %s' . PHP_EOL, $object->name() );
            $object->delete();
        }

        return $count;
    }


    public function replacePackage( $folderId, $scormId, $newPackage )
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $object = $bucket->object( $folderId . DIRECTORY_SEPARATOR . $scormId );
        $object->delete();

        $this->uploadPackage( $folderId, $scormId, $newPackage );
    }

    public function signedUrl( $folderId, $scormId, $file, $method = 'GET' )
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $object = $bucket->object( $folderId . '/' . $scormId . '/'. $file );
        if( is_null( $object ) ) {
            return null;
        }
        $date = new DateTime( 'tomorrow' );
        return $object->signedUrl( $date->getTimestamp(), ['method' => $method ] );
    }

    public function objectExists( $objectName )
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $object = $bucket->object( $objectName );

        return $object->exists();
    }

    public function downloadObjectAsString( $objectName )
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket( $this->bucketName );
        $object = $bucket->object( $objectName );

        return $object->downloadAsString();
    }
}
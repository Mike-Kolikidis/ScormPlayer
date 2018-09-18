<?php
namespace ahat\ScormPlayer\Tests;

use PHPUnit\Framework\TestCase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Lcobucci\JWT\Parser;
use Firebase\Auth\Token\Generator as CustomTokenGenerator;
use Kreait\Firebase\Value\Uid;

use ahat\ScormPlayer\Verifier;

class ConnectTest extends TestCase
{
    protected function setUp()
    {
        $this->stack = [];
    }

    public function testConnect()
    {
        $firebase = (new Factory)
            ->withDefaultStorageBucket( getenv( 'GOOGLE_CLOUD_STORAGE_BUCKET' ) )
            ->create();
        $storage = $firebase->getStorage();
        // var_dump( $result );
        $this->assertNotNull( $storage, 'Connection to Firebase storage failed' );
    }

    public function testCustomToken()
    {
        $folderId = 'test1';
        $scormId = 'id1';

        // $firebase = (new Factory)
        //     ->withDefaultStorageBucket( getenv( 'GOOGLE_CLOUD_STORAGE_BUCKET' ) )
        //     ->create();

        $serviceAccount = ServiceAccount::fromJsonFile( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) );

        $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            // ->asUser('my-service-worker')
            ->create();

        $uid = 'anything here';
        $additionalClaims = [
            'folderId' => $folderId,
            'scormId' => $scormId
        ];

        // replaced
        // $customToken = $firebase->getAuth()->createCustomToken($uid, $additionalClaims);

        $uid = $uid instanceof Uid ? $uid : new Uid($uid);

        $tokenGenerator = new CustomTokenGenerator(
            $serviceAccount->getClientEmail(),
            $serviceAccount->getPrivateKey());
        
        $customToken = $tokenGenerator->createCustomToken($uid, $additionalClaims);
        

        $customTokenString = (string) $customToken;

        echo "Custom token for $folderId/$scormId: $customTokenString\n";

        $token = (new Parser())->parse($customTokenString);
        $dbg = var_export($token, true);
        echo "Parsed token: $dbg\n";


        $verifier = new Verifier( $serviceAccount->getClientEmail(), $serviceAccount->getPrivateKey() );

        $this->assertTrue( 1 == 1 );
    }
}

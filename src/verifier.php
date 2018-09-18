<?php

namespace ahat\ScormPlayer;

use Firebase\Auth\Token\Domain\KeyStore;
use Firebase\Auth\Token\Exception\ExpiredToken;
use Firebase\Auth\Token\Exception\InvalidToken;
use Firebase\Auth\Token\Exception\IssuedInTheFuture;
use Firebase\Auth\Token\Exception\UnknownKey;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;

/**
 * This class verifies a JWT token and has been copied from Firebase. The original class was inappropriate because 
 * a) it was validating the issuer against a static google http address
 * b) it was trying to verify the signature of JWT with a key retrieved from a keystore although the FirebaseFactory
 * did not instantiate it with one.
 */
class Verifier 
{
    /**
     * @var string
     */
    private $account;

    /**
     * @var key
     */
    private $key;

    /**
     * @var Signer
     */
    private $signer;

    public function __construct(string $account, string $key, Signer $signer = null)
    {
        $this->key = $key;

        $this->account = $account;

        $this->signer = $signer ?? new Sha256();
    }

    public function verifyIdToken($token): Token
    {
        if (!($token instanceof Token)) {
            $token = (new Parser())->parse($token);
            // $dbg = var_export( $token, true );
            // file_put_contents("php://stdout", "\nVerifier converted token to : $dbg\n" );
        }

        $this->verifyExpiry($token);
        $this->verifyIssuedAt($token);
        $this->verifyIssuer($token);        
        $this->verifySignature($token, $this->key);

        return $token;
    }

    private function verifyExpiry(Token $token)
    {
        if (!$token->hasClaim('exp')) {
            throw new InvalidToken($token, 'The claim "exp" is missing.');
        }

        if ($token->isExpired()) {
            throw new ExpiredToken($token);
        }
    }

    private function verifyIssuedAt(Token $token)
    {
        if (!$token->hasClaim('iat')) {
            throw new InvalidToken($token, 'The claim "iat" is missing.');
        }

        if ($token->getClaim('iat') > time()) {
            throw new IssuedInTheFuture($token);
        }
    }

    private function verifyIssuer(Token $token)
    {
        if (!$token->hasClaim('iss')) {
            throw new InvalidToken($token, 'The claim "iss" is missing.');
        }

        if ($token->getClaim('iss') !== $this->account ) {
            throw new InvalidToken($token, 'This token has an invalid issuer.');
        }
    }

    private function verifySignature(Token $token, string $key)
    {
        try {
            $isVerified = $token->verify($this->signer, $key);
        } catch (\Throwable $e) {
            throw new InvalidToken($token, $e->getMessage());
        }

        if (!$isVerified) {
            throw new InvalidToken($token, 'This token has an invalid signature.');
        }
    }
}

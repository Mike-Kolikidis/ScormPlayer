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
        // $dbg = var_export($key, true);
        // file_put_contents("php://stdout", "\nVerifier key: $dbg\n" );

        $this->key = $key;

        // $dbg = var_export($signer, true);
        // file_put_contents("php://stdout", "\nVerifier signer: $dbg\n" );

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
        // file_put_contents("php://stdout", "\nVerifier issuer: ". $token->getClaim('iss') .", account: ". $this->account ."\n" );

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

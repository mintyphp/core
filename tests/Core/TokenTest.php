<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    private Token $token;
    private string $secret = 'test_secret_key_for_jwt_tokens';

    protected function setUp(): void
    {
        $this->token = new Token(
            'HS256',
            $this->secret,
            5, // leeway
            3600, // ttl (1 hour)
            'test-audience',
            'test-issuer',
            'HS256,HS384,HS512', // allowed algorithms
            'test-audience', // allowed audiences
            'test-issuer' // allowed issuers
        );
    }

    public function testTokenConstruction(): void
    {
        $this->assertInstanceOf(Token::class, $this->token);
    }

    public function testGetTokenGeneratesValidJwt(): void
    {
        $claims = ['sub' => 'user123', 'name' => 'Test User'];
        $jwt = $this->token->getToken($claims);

        $this->assertIsString($jwt);
        $this->assertStringContainsString('.', $jwt);

        // JWT should have 3 parts
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
    }

    public function testGetTokenAddsIssuerAndAudience(): void
    {
        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        $verifiedClaims = $this->token->getClaims($jwt);

        $this->assertIsArray($verifiedClaims);
        $this->assertEquals('test-issuer', $verifiedClaims['iss']);
        $this->assertEquals('test-audience', $verifiedClaims['aud']);
    }

    public function testGetTokenAddsTimestamps(): void
    {
        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        $verifiedClaims = $this->token->getClaims($jwt);

        $this->assertIsArray($verifiedClaims);
        $this->assertArrayHasKey('iat', $verifiedClaims);
        $this->assertArrayHasKey('exp', $verifiedClaims);
        $this->assertIsInt($verifiedClaims['iat']);
        $this->assertIsInt($verifiedClaims['exp']);
        $this->assertGreaterThan($verifiedClaims['iat'], $verifiedClaims['exp']);
    }

    public function testGetClaimsVerifiesValidToken(): void
    {
        $claims = ['sub' => 'user123', 'name' => 'Test User'];
        $jwt = $this->token->getToken($claims);

        $verifiedClaims = $this->token->getClaims($jwt);

        $this->assertIsArray($verifiedClaims);
        $this->assertEquals('user123', $verifiedClaims['sub']);
        $this->assertEquals('Test User', $verifiedClaims['name']);
    }

    public function testGetClaimsRejectsInvalidToken(): void
    {
        $invalidToken = 'invalid.token.here';

        $result = $this->token->getClaims($invalidToken);

        $this->assertEmpty($result);
    }

    public function testGetClaimsRejectsEmptyToken(): void
    {
        $result = $this->token->getClaims('');

        $this->assertEmpty($result);
    }

    public function testGetClaimsRejectsTamperedToken(): void
    {
        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        // Tamper with the token
        $parts = explode('.', $jwt);
        $parts[1] = base64_encode('{"sub":"hacker"}');
        $tamperedJwt = implode('.', $parts);

        $result = $this->token->getClaims($tamperedJwt);

        $this->assertEmpty($result);
    }

    public function testGetClaimsRejectsWrongSecret(): void
    {
        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        // Create token instance with different secret
        $tokenWithDifferentSecret = new Token(
            'HS256',
            'different_secret',
            5,
            3600,
            'test-audience',
            'test-issuer',
            'HS256',
            'test-audience',
            'test-issuer'
        );

        $result = $tokenWithDifferentSecret->getClaims($jwt);

        $this->assertEmpty($result);
    }

    public function testDifferentAlgorithms(): void
    {
        $algorithms = ['HS256', 'HS384', 'HS512'];

        foreach ($algorithms as $algorithm) {
            $token = new Token($algorithm, $this->secret, 5, 3600, false, false, $algorithm, '', '');
            $claims = ['sub' => 'user123'];
            $jwt = $token->getToken($claims);

            $this->assertIsString($jwt);

            $verifiedClaims = $token->getClaims($jwt);
            $this->assertIsArray($verifiedClaims);
            $this->assertEquals('user123', $verifiedClaims['sub']);
        }
    }

    public function testRequiresSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token secret cannot be empty');
        new Token('HS256', '', 5, 3600);
    }

    public function testClaimsRespectCustomAudienceAndIssuer(): void
    {
        // Create token with more permissive allowed values
        $permissiveToken = new Token(
            'HS256',
            $this->secret,
            5,
            3600,
            'test-audience',
            'test-issuer',
            'HS256',
            'test-audience,custom-audience', // Allow both
            'test-issuer,custom-issuer' // Allow both
        );

        $claims = [
            'sub' => 'user123',
            'aud' => 'custom-audience',
            'iss' => 'custom-issuer'
        ];

        $jwt = $permissiveToken->getToken($claims);

        $verifiedClaims = $permissiveToken->getClaims($jwt);

        $this->assertIsArray($verifiedClaims);
        $this->assertEquals('custom-audience', $verifiedClaims['aud']);
        $this->assertEquals('custom-issuer', $verifiedClaims['iss']);
    }
    public function testTokenRejectsWrongAudience(): void
    {
        $tokenWithDifferentAudience = new Token(
            'HS256',
            $this->secret,
            5,
            3600,
            'test-audience',
            'test-issuer',
            'HS256',
            'different-audience', // Different allowed audience
            'test-issuer'
        );

        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        $result = $tokenWithDifferentAudience->getClaims($jwt);

        $this->assertEmpty($result);
    }

    public function testTokenRejectsWrongIssuer(): void
    {
        $tokenWithDifferentIssuer = new Token(
            'HS256',
            $this->secret,
            5,
            3600,
            'test-audience',
            'test-issuer',
            'HS256',
            'test-audience',
            'different-issuer' // Different allowed issuer
        );

        $claims = ['sub' => 'user123'];
        $jwt = $this->token->getToken($claims);

        $result = $tokenWithDifferentIssuer->getClaims($jwt);

        $this->assertEmpty($result);
    }
}

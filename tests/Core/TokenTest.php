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

    public function testDefaultToken(): void
    {
        $token = new Token('NONE', 'secret', 5, 30);
        $tokenStr = "eyJhbGciOiJOT05FIiwidHlwIjoiSldUIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals([], $claims);
    }

    public function testJwtIoHs256Example(): void
    {
        $token = new Token('HS256', 'your-256-bit-secret', 5, 30);
        $tokenStr = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.dyt0CoTl4WoVjAHI9Q_CwSKhl6d_9rhM3NrXuJttkao";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoHs384Example(): void
    {
        $token = new Token('HS384', 'your-384-bit-secret', 5, 30);
        $tokenStr = "eyJhbGciOiJIUzM4NCIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.n3K7zPIJXnJevaaDZZMF_WdlobKG_XzHHBLE7m3mRdoNGZDDhVFhO7jWtEdbNhn7";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoHs512Example(): void
    {
        $token = new Token('HS512', 'your-512-bit-secret', 5, 30);
        $tokenStr = "eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.TeElPMEAJP7Oprhi971yOYEKzcvn2_XxkcEzvg8ZTmdyVftF6BQH51J5vDcZVJKviVZu4a6q0xjW7T_AnChtEg";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs256Example(): void
    {
        $token = new Token(
            'RS256',
            "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----",
            5,
            30
        );
        $tokenStr = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.EkN-DOsnsuRjRO6BxXemmJDm3HbxrbRzXglbN2S4sOkopdU4IsDxTI8jO19W_A4K8ZPJijNLis4EZsHeY559a4DFOd50_OqgHGuERTqYZyuhtF39yxJPAjUESwxk2J5k_4zM3O-vtd1Ghyo4IbqKKSy6J9mTniYJPenn5-HIirE";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs256ExampleWithRs512Required(): void
    {
        $token = new Token(
            'RS256',
            "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----",
            5,
            30,
            '',
            '',
            'HS512,RS512' // Only allow HS512 and RS512
        );
        $tokenStr = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.EkN-DOsnsuRjRO6BxXemmJDm3HbxrbRzXglbN2S4sOkopdU4IsDxTI8jO19W_A4K8ZPJijNLis4EZsHeY559a4DFOd50_OqgHGuERTqYZyuhtF39yxJPAjUESwxk2J5k_4zM3O-vtd1Ghyo4IbqKKSy6J9mTniYJPenn5-HIirE";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals([], $claims);
    }

    public function testJwtIoRs384Example(): void
    {
        $token = new Token(
            'RS384',
            "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----",
            5,
            30
        );
        $tokenStr = "eyJhbGciOiJSUzM4NCIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.Ffs4IGK8GkxrSxp7I8IcuHy_uUSskg2zBwScCGhg6T1o4hkdZ5ytJNRj04kD8FEnUrnnUiGKgHL0MWrwmgz6Kmi6fxDSKKbiVlESPkUrgBTMaIlOheDbemy19lxUJYqd7A2exNXtCW_UoSs8f3ZdYujNrbZWW8kWgLQuk4oa-0I";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs512Example(): void
    {
        $token = new Token(
            'RS512',
            "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----",
            5,
            30
        );
        $tokenStr = "eyJhbGciOiJSUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.yN0Dw5rVJ75rdJXKpflhwASRr4DHwlgmRY4HVMdotCdyg8fOB2sLRehLY9g9isBnIuOA0aK7qWpj9cc7G8eYmaFdm95_moOJKxCgH0Rn2d2-wygdjBvMrSpkxsKMdbc2tKP0rI3ZYalQ7Q86RagZNZ_JpA2V3j3JPKTQwKFGSTw";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testNoneAlgorithm(): void
    {
        $token = new Token('none', 'secret', 5, 30);
        $tokenStr = "eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.";
        $claims = $token->getClaims($tokenStr);
        $this->assertEquals([], $claims);
    }

    public function testTokenGenerationAndVerificationHs256(): void
    {
        $token = new Token('HS256', 'secret', 5, 30);
        $claims = array('customer_id' => 4, 'user_id' => 2);
        $tokenStr = $token->getToken($claims);
        $verifiedClaims = $token->getClaims($tokenStr);
        $this->assertNotFalse($verifiedClaims);
        $this->assertEquals(4, $verifiedClaims['customer_id']);
        $this->assertEquals(2, $verifiedClaims['user_id']);
    }

    public function testTokenGenerationAndVerificationRs256(): void
    {
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\nMIICWwIBAAKBgQDdlatRjRjogo3WojgGHFHYLugdUWAY9iR3fy4arWNA1KoS8kVw\n33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQsHUfQrSDv+MuSUMAe8jzKE4qW\n+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5Do2kQ+X5xK9cipRgEKwIDAQAB\nAoGAD+onAtVye4ic7VR7V50DF9bOnwRwNXrARcDhq9LWNRrRGElESYYTQ6EbatXS\n3MCyjjX2eMhu/aF5YhXBwkppwxg+EOmXeh+MzL7Zh284OuPbkglAaGhV9bb6/5Cp\nuGb1esyPbYW+Ty2PC0GSZfIXkXs76jXAu9TOBvD0ybc2YlkCQQDywg2R/7t3Q2OE\n2+yo382CLJdrlSLVROWKwb4tb2PjhY4XAwV8d1vy0RenxTB+K5Mu57uVSTHtrMK0\nGAtFr833AkEA6avx20OHo61Yela/4k5kQDtjEf1N0LfI+BcWZtxsS3jDM3i1Hp0K\nSu5rsCPb8acJo5RO26gGVrfAsDcIXKC+bQJAZZ2XIpsitLyPpuiMOvBbzPavd4gY\n6Z8KWrfYzJoI/Q9FuBo6rKwl4BFoToD7WIUS+hpkagwWiz+6zLoX1dbOZwJACmH5\nfSSjAkLRi54PKJ8TFUeOP15h9sQzydI8zJU+upvDEKZsZc/UhT/SySDOxQ4G/523\nY0sz/OZtSWcol/UMgQJALesy++GdvoIDLfJX5GBQpuFgFenRiRDabxrE9MNUZ2aP\nFaFp+DyAe+b4nDwuJaW2LURbr8AEZga7oQj0uYxcYw==\n-----END RSA PRIVATE KEY-----";
        $publicKey = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----";

        $tokenSign = new Token('RS256', $privateKey, 5, 30);
        $claims = array('customer_id' => 4, 'user_id' => 2);
        $tokenStr = $tokenSign->getToken($claims);

        $tokenVerify = new Token('RS256', $publicKey, 5, 30);
        $verifiedClaims = $tokenVerify->getClaims($tokenStr);
        $this->assertNotFalse($verifiedClaims);
        $this->assertEquals(4, $verifiedClaims['customer_id']);
        $this->assertEquals(2, $verifiedClaims['user_id']);
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
            $token = new Token($algorithm, $this->secret, 5, 3600, '', '', $algorithm, '', '');
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

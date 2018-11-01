<?php
namespace MintyPHP\Tests;

use MintyPHP\Token;

class TokenTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultToken()
    {
        Token::$secret = 'secret';
        Token::$algorithm = 'HS256';
        $token = "eyJhbGciOiJOT05FIiwidHlwIjoiSldUIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.";
        $claims = Token::getClaims($token);
        $this->assertEquals(false, $claims);
    }

    public function testJwtIoHs256Example()
    {
        Token::$secret = 'your-256-bit-secret';
        Token::$algorithm = 'HS256';
        $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.dyt0CoTl4WoVjAHI9Q_CwSKhl6d_9rhM3NrXuJttkao";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoHs384Example()
    {
        Token::$secret = 'your-384-bit-secret';
        Token::$algorithm = 'HS384';
        $token = "eyJhbGciOiJIUzM4NCIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.n3K7zPIJXnJevaaDZZMF_WdlobKG_XzHHBLE7m3mRdoNGZDDhVFhO7jWtEdbNhn7";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoHs512Example()
    {
        Token::$secret = 'your-512-bit-secret';
        Token::$algorithm = 'HS512';
        $token = "eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.TeElPMEAJP7Oprhi971yOYEKzcvn2_XxkcEzvg8ZTmdyVftF6BQH51J5vDcZVJKviVZu4a6q0xjW7T_AnChtEg";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs256Example()
    {
        Token::$secret = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----";
        Token::$algorithm = 'RS256';
        $token = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.EkN-DOsnsuRjRO6BxXemmJDm3HbxrbRzXglbN2S4sOkopdU4IsDxTI8jO19W_A4K8ZPJijNLis4EZsHeY559a4DFOd50_OqgHGuERTqYZyuhtF39yxJPAjUESwxk2J5k_4zM3O-vtd1Ghyo4IbqKKSy6J9mTniYJPenn5-HIirE";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs384Example()
    {
        Token::$secret = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----";
        Token::$algorithm = 'RS384';
        $token = "eyJhbGciOiJSUzM4NCIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.Ffs4IGK8GkxrSxp7I8IcuHy_uUSskg2zBwScCGhg6T1o4hkdZ5ytJNRj04kD8FEnUrnnUiGKgHL0MWrwmgz6Kmi6fxDSKKbiVlESPkUrgBTMaIlOheDbemy19lxUJYqd7A2exNXtCW_UoSs8f3ZdYujNrbZWW8kWgLQuk4oa-0I";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testJwtIoRs512Example()
    {
        Token::$secret = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----";
        Token::$algorithm = 'RS512';
        $token = "eyJhbGciOiJSUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.yN0Dw5rVJ75rdJXKpflhwASRr4DHwlgmRY4HVMdotCdyg8fOB2sLRehLY9g9isBnIuOA0aK7qWpj9cc7G8eYmaFdm95_moOJKxCgH0Rn2d2-wygdjBvMrSpkxsKMdbc2tKP0rI3ZYalQ7Q86RagZNZ_JpA2V3j3JPKTQwKFGSTw";
        $claims = Token::getClaims($token);
        $this->assertEquals(array('sub' => '1234567890', 'name' => 'John Doe', 'admin' => true), $claims);
    }

    public function testNoneAlgorithm()
    {
        Token::$secret = 'secret';
        Token::$algorithm = 'none';
        $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJub25lIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.";
        $claims = Token::getClaims($token);
        $this->assertEquals(false, $claims);
    }

    public function testTokenGenerationAndVerificationHs256()
    {
        Token::$secret = 'secret';
        Token::$algorithm = 'HS256';
        $claims = array('customer_id' => 4, 'user_id' => 2);
        $token = Token::getToken($claims);
        $claims = Token::getClaims($token);
        $this->assertNotFalse($claims);
        $this->assertEquals(4, $claims['customer_id']);
        $this->assertEquals(2, $claims['user_id']);
    }

    public function testTokenGenerationAndVerificationRs256()
    {
        Token::$algorithm = 'RS256';
        Token::$secret = "-----BEGIN RSA PRIVATE KEY-----\nMIICWwIBAAKBgQDdlatRjRjogo3WojgGHFHYLugdUWAY9iR3fy4arWNA1KoS8kVw\n33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQsHUfQrSDv+MuSUMAe8jzKE4qW\n+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5Do2kQ+X5xK9cipRgEKwIDAQAB\nAoGAD+onAtVye4ic7VR7V50DF9bOnwRwNXrARcDhq9LWNRrRGElESYYTQ6EbatXS\n3MCyjjX2eMhu/aF5YhXBwkppwxg+EOmXeh+MzL7Zh284OuPbkglAaGhV9bb6/5Cp\nuGb1esyPbYW+Ty2PC0GSZfIXkXs76jXAu9TOBvD0ybc2YlkCQQDywg2R/7t3Q2OE\n2+yo382CLJdrlSLVROWKwb4tb2PjhY4XAwV8d1vy0RenxTB+K5Mu57uVSTHtrMK0\nGAtFr833AkEA6avx20OHo61Yela/4k5kQDtjEf1N0LfI+BcWZtxsS3jDM3i1Hp0K\nSu5rsCPb8acJo5RO26gGVrfAsDcIXKC+bQJAZZ2XIpsitLyPpuiMOvBbzPavd4gY\n6Z8KWrfYzJoI/Q9FuBo6rKwl4BFoToD7WIUS+hpkagwWiz+6zLoX1dbOZwJACmH5\nfSSjAkLRi54PKJ8TFUeOP15h9sQzydI8zJU+upvDEKZsZc/UhT/SySDOxQ4G/523\nY0sz/OZtSWcol/UMgQJALesy++GdvoIDLfJX5GBQpuFgFenRiRDabxrE9MNUZ2aP\nFaFp+DyAe+b4nDwuJaW2LURbr8AEZga7oQj0uYxcYw==\n-----END RSA PRIVATE KEY-----";
        $claims = array('customer_id' => 4, 'user_id' => 2);
        $token = Token::getToken($claims);
        Token::$secret = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDdlatRjRjogo3WojgGHFHYLugd\nUWAY9iR3fy4arWNA1KoS8kVw33cJibXr8bvwUAUparCwlvdbH6dvEOfou0/gCFQs\nHUfQrSDv+MuSUMAe8jzKE4qW+jK+xQU9a03GUnKHkkle+Q0pX/g6jXZ7r1/xAK5D\no2kQ+X5xK9cipRgEKwIDAQAB\n-----END PUBLIC KEY-----";
        $claims = Token::getClaims($token);
        $this->assertNotFalse($claims);
        $this->assertEquals(4, $claims['customer_id']);
        $this->assertEquals(2, $claims['user_id']);
    }

}

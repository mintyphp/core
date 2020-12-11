<?php
namespace MintyPHP\Tests;

use MintyPHP\Totp;

class TotpTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultToken()
    {
        Totp::$timestamp = 319690800;
        $match = Totp::verify('JDDK4U6G3BJLEZ7Y', '762124');
        $this->assertEquals(true, $match);
        $secret = Totp::generateSecret();
        $this->assertEquals(16, strlen($secret));
        $match = Totp::generateURI('TQdev', 'maurits@vdschee.nl', '1234567890123456');
        $this->assertEquals('otpauth://totp/TQdev%3Amaurits%40vdschee.nl?issuer=TQdev&secret=1234567890123456', $match);
    }

}

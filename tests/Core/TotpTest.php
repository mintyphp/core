<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
	public function testDefaultToken(): void
	{
		// Instantiate Totp manually with default configuration
		$totp = new Totp(30, 'sha1', 6, 10);

		// set a fixed timestamp for testing
		$totp->timestamp = 319690800;
		$match = $totp->verify('JDDK4U6G3BJLEZ7Y', '762124');
		$this->assertEquals(true, $match);
		$secret = $totp->generateSecret();
		$this->assertEquals(16, strlen($secret));
		$match = $totp->generateURI('TQdev', 'maurits@vdschee.nl', '1234567890123456');
		$this->assertEquals('otpauth://totp/TQdev%3Amaurits%40vdschee.nl?issuer=TQdev&secret=1234567890123456', $match);
	}
}

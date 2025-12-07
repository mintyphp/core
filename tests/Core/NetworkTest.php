<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Network;

class NetworkTest extends \PHPUnit\Framework\TestCase
{
    private Network $network;

    protected function setUp(): void
    {
        $this->network = new Network();
    }

    // IPv4 Tests

    public function testIp4MatchExactMatch(): void
    {
        $this->assertTrue($this->network->ip4Match('192.168.1.1', '192.168.1.1'));
        $this->assertTrue($this->network->ip4Match('10.0.0.1', '10.0.0.1'));
        $this->assertTrue($this->network->ip4Match('172.16.0.1', '172.16.0.1'));
    }

    public function testIp4MatchExactMismatch(): void
    {
        $this->assertFalse($this->network->ip4Match('192.168.1.1', '192.168.1.2'));
        $this->assertFalse($this->network->ip4Match('10.0.0.1', '10.0.0.2'));
    }

    public function testIp4MatchWithSlash32(): void
    {
        $this->assertTrue($this->network->ip4Match('192.168.1.100', '192.168.1.100/32'));
        $this->assertFalse($this->network->ip4Match('192.168.1.100', '192.168.1.101/32'));
    }

    public function testIp4MatchWithSlash24(): void
    {
        // /24 means 192.168.1.0 - 192.168.1.255
        $this->assertTrue($this->network->ip4Match('192.168.1.1', '192.168.1.0/24'));
        $this->assertTrue($this->network->ip4Match('192.168.1.100', '192.168.1.0/24'));
        $this->assertTrue($this->network->ip4Match('192.168.1.255', '192.168.1.0/24'));
        $this->assertFalse($this->network->ip4Match('192.168.2.1', '192.168.1.0/24'));
        $this->assertFalse($this->network->ip4Match('192.168.0.255', '192.168.1.0/24'));
    }

    public function testIp4MatchWithSlash16(): void
    {
        // /16 means 10.20.0.0 - 10.20.255.255
        $this->assertTrue($this->network->ip4Match('10.20.0.1', '10.20.0.0/16'));
        $this->assertTrue($this->network->ip4Match('10.20.100.200', '10.20.0.0/16'));
        $this->assertTrue($this->network->ip4Match('10.20.255.255', '10.20.0.0/16'));
        $this->assertFalse($this->network->ip4Match('10.21.0.1', '10.20.0.0/16'));
        $this->assertFalse($this->network->ip4Match('10.19.255.255', '10.20.0.0/16'));
    }

    public function testIp4MatchWithSlash8(): void
    {
        // /8 means 10.0.0.0 - 10.255.255.255
        $this->assertTrue($this->network->ip4Match('10.0.0.1', '10.0.0.0/8'));
        $this->assertTrue($this->network->ip4Match('10.128.64.32', '10.0.0.0/8'));
        $this->assertTrue($this->network->ip4Match('10.255.255.255', '10.0.0.0/8'));
        $this->assertFalse($this->network->ip4Match('11.0.0.1', '10.0.0.0/8'));
        $this->assertFalse($this->network->ip4Match('9.255.255.255', '10.0.0.0/8'));
    }

    public function testIp4MatchWithOddCIDR(): void
    {
        // /28 means 16 addresses (192.168.1.0 - 192.168.1.15)
        $this->assertTrue($this->network->ip4Match('192.168.1.0', '192.168.1.0/28'));
        $this->assertTrue($this->network->ip4Match('192.168.1.8', '192.168.1.0/28'));
        $this->assertTrue($this->network->ip4Match('192.168.1.15', '192.168.1.0/28'));
        $this->assertFalse($this->network->ip4Match('192.168.1.16', '192.168.1.0/28'));

        // /30 means 4 addresses (172.16.0.0 - 172.16.0.3)
        $this->assertTrue($this->network->ip4Match('172.16.0.0', '172.16.0.0/30'));
        $this->assertTrue($this->network->ip4Match('172.16.0.3', '172.16.0.0/30'));
        $this->assertFalse($this->network->ip4Match('172.16.0.4', '172.16.0.0/30'));
    }

    public function testIp4MatchPrivateRanges(): void
    {
        // 192.168.0.0/16
        $this->assertTrue($this->network->ip4Match('192.168.50.100', '192.168.0.0/16'));

        // 172.16.0.0/12 (172.16.0.0 - 172.31.255.255)
        $this->assertTrue($this->network->ip4Match('172.16.0.1', '172.16.0.0/12'));
        $this->assertTrue($this->network->ip4Match('172.31.255.255', '172.16.0.0/12'));
        $this->assertFalse($this->network->ip4Match('172.32.0.1', '172.16.0.0/12'));

        // 10.0.0.0/8
        $this->assertTrue($this->network->ip4Match('10.123.45.67', '10.0.0.0/8'));
    }

    public function testIp4MatchPublicIPs(): void
    {
        $this->assertTrue($this->network->ip4Match('8.8.8.8', '8.8.8.0/24'));
        $this->assertTrue($this->network->ip4Match('1.1.1.1', '1.1.1.0/24'));
        $this->assertFalse($this->network->ip4Match('8.8.4.4', '8.8.8.0/24'));
    }    // IPv6 Tests

    public function testIp6MatchExactMatch(): void
    {
        $this->assertTrue($this->network->ip6Match('2001:db8::1', '2001:db8::1'));
        $this->assertTrue($this->network->ip6Match('fe80::1', 'fe80::1'));
        $this->assertTrue($this->network->ip6Match('::1', '::1'));
    }

    public function testIp6MatchExactMismatch(): void
    {
        $this->assertFalse($this->network->ip6Match('2001:db8::1', '2001:db8::2'));
        $this->assertFalse($this->network->ip6Match('fe80::1', 'fe80::2'));
    }

    public function testIp6MatchWithSlash128(): void
    {
        $this->assertTrue($this->network->ip6Match('2001:db8::1', '2001:db8::1/128'));
        $this->assertFalse($this->network->ip6Match('2001:db8::1', '2001:db8::2/128'));
    }

    public function testIp6MatchWithSlash64(): void
    {
        // /64 means same first 64 bits
        $this->assertTrue($this->network->ip6Match('2001:db8:abcd:1234::1', '2001:db8:abcd:1234::/64'));
        $this->assertTrue($this->network->ip6Match('2001:db8:abcd:1234::ffff', '2001:db8:abcd:1234::/64'));
        $this->assertTrue($this->network->ip6Match('2001:db8:abcd:1234:5678:90ab:cdef:1234', '2001:db8:abcd:1234::/64'));
        $this->assertFalse($this->network->ip6Match('2001:db8:abcd:1235::1', '2001:db8:abcd:1234::/64'));
    }

    public function testIp6MatchWithSlash48(): void
    {
        // /48 prefix
        $this->assertTrue($this->network->ip6Match('2001:db8:1234::1', '2001:db8:1234::/48'));
        $this->assertTrue($this->network->ip6Match('2001:db8:1234:ffff::1', '2001:db8:1234::/48'));
        $this->assertFalse($this->network->ip6Match('2001:db8:1235::1', '2001:db8:1234::/48'));
    }

    public function testIp6MatchWithSlash32(): void
    {
        // /32 prefix (common for ISP allocations)
        $this->assertTrue($this->network->ip6Match('2001:db8::1', '2001:db8::/32'));
        $this->assertTrue($this->network->ip6Match('2001:db8:ffff:ffff:ffff:ffff:ffff:ffff', '2001:db8::/32'));
        $this->assertFalse($this->network->ip6Match('2001:db9::1', '2001:db8::/32'));
    }

    public function testIp6MatchLinkLocal(): void
    {
        // Link-local addresses (fe80::/10)
        $this->assertTrue($this->network->ip6Match('fe80::1', 'fe80::/10'));
        $this->assertTrue($this->network->ip6Match('fe80::abcd:ef12:3456:7890', 'fe80::/10'));
        $this->assertTrue($this->network->ip6Match('febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'fe80::/10'));
        $this->assertFalse($this->network->ip6Match('fec0::1', 'fe80::/10'));
    }

    public function testIp6MatchUniqueLocal(): void
    {
        // Unique local addresses (fc00::/7)
        $this->assertTrue($this->network->ip6Match('fc00::1', 'fc00::/7'));
        $this->assertTrue($this->network->ip6Match('fd00::1', 'fc00::/7'));
        $this->assertTrue($this->network->ip6Match('fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'fc00::/7'));
        $this->assertFalse($this->network->ip6Match('fe00::1', 'fc00::/7'));
    }

    public function testIp6MatchLoopback(): void
    {
        $this->assertTrue($this->network->ip6Match('::1', '::1/128'));
        $this->assertFalse($this->network->ip6Match('::2', '::1/128'));
    }

    public function testIp6MatchDocumentation(): void
    {
        // 2001:db8::/32 is reserved for documentation
        $this->assertTrue($this->network->ip6Match('2001:db8::', '2001:db8::/32'));
        $this->assertTrue($this->network->ip6Match('2001:db8:1234:5678::1', '2001:db8::/32'));
    }

    public function testIp6MatchVariousNotations(): void
    {
        // Test compressed notation
        $this->assertTrue($this->network->ip6Match('2001:db8:0:0:0:0:0:1', '2001:db8::1'));
        $this->assertTrue($this->network->ip6Match('2001:db8::1', '2001:db8:0:0:0:0:0:1'));

        // Test with /64
        $this->assertTrue($this->network->ip6Match('2001:0db8:0000:0000:0001:0000:0000:0001', '2001:db8::/64'));
    }

    public function testIp6MatchOddCIDR(): void
    {
        // /96 prefix
        $this->assertTrue($this->network->ip6Match('2001:db8::192.0.2.1', '2001:db8::/96'));

        // /56 prefix
        $this->assertTrue($this->network->ip6Match('2001:db8:ab00::1', '2001:db8:ab00::/56'));
        $this->assertTrue($this->network->ip6Match('2001:db8:abff::1', '2001:db8:ab00::/56'));
        $this->assertFalse($this->network->ip6Match('2001:db8:ac00::1', '2001:db8:ab00::/56'));
    }

    public function testIp6MatchInvalidAddresses(): void
    {
        $this->assertFalse($this->network->ip6Match('invalid', '2001:db8::/32'));
        $this->assertFalse($this->network->ip6Match('2001:db8::1', 'invalid'));
        $this->assertFalse($this->network->ip6Match('192.168.1.1', '2001:db8::/32'));
    }

    public function testIp4MatchEdgeCases(): void
    {
        // Test with 0.0.0.0
        $this->assertTrue($this->network->ip4Match('0.0.0.0', '0.0.0.0'));
        $this->assertTrue($this->network->ip4Match('0.0.0.1', '0.0.0.0/24'));

        // Test with 255.255.255.255
        $this->assertTrue($this->network->ip4Match('255.255.255.255', '255.255.255.255'));
        $this->assertTrue($this->network->ip4Match('255.255.255.255', '255.255.255.0/24'));

        // Test /0 (matches everything)
        $this->assertTrue($this->network->ip4Match('192.168.1.1', '0.0.0.0/0'));
        $this->assertTrue($this->network->ip4Match('8.8.8.8', '0.0.0.0/0'));
    }

    public function testIp6MatchEdgeCases(): void
    {
        // Test with all zeros
        $this->assertTrue($this->network->ip6Match('::', '::'));

        // Test /0 (matches everything)
        $this->assertTrue($this->network->ip6Match('2001:db8::1', '::/0'));
        $this->assertTrue($this->network->ip6Match('fe80::1', '::/0'));
    }
}

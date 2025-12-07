<?php

namespace MintyPHP\Core;

class Network
{
    public function isLocalIP(string $ipAddress): bool
    {
        preg_match_all('|inet6? ([^/]+)/|', `ip a`, $matches);
        $ipAddresses = $matches[1];
        return in_array($ipAddress, $ipAddresses);
    }

    public function ip4Match(string $ip4, string $range): bool
    {
        if (strpos($range, '/')) {
            list($subnet, $bits) = explode('/', $range);
        } else {
            $subnet = $range;
            $bits = 32;
        }
        $ip = ip2long($ip4);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        return ($ip & $mask) == ($subnet & $mask);
    }

    public function ip6Match(string $ip6, string $range): bool
    {
        if (strpos($range, '/')) {
            list($subnet, $bits) = explode('/', $range);
        } else {
            $subnet = $range;
            $bits = 128;
        }

        $ip = inet_pton($ip6);
        $subnet = inet_pton($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        // Convert to binary strings for bitwise operations
        $ipBin = '';
        $subnetBin = '';
        for ($i = 0; $i < strlen($ip); $i++) {
            $ipBin .= str_pad(decbin(ord($ip[$i] ?? '0')), 8, '0', STR_PAD_LEFT);
            $subnetBin .= str_pad(decbin(ord($subnet[$i] ?? '0')), 8, '0', STR_PAD_LEFT);
        }

        // Compare only the prefix bits
        return substr($ipBin, 0, $bits) === substr($subnetBin, 0, $bits);
    }
}

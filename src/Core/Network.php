<?php

namespace MintyPHP\Core;

/**
 * Core Network class providing network-related functionalities.
 */
class Network
{
    /**
     * Check if the given IP address is assigned to a local network interface.
     * @param string $ipAddress
     * @return bool
     */
    public function isLocalIP(string $ipAddress): bool
    {
        preg_match_all('|inet6? ([^/]+)/|', `ip a`, $matches);
        $ipAddresses = $matches[1];
        return in_array($ipAddress, $ipAddresses);
    }

    /**
     * Check if an IPv4 address is within a given CIDR range.
     * @param string $ip4
     * @param string $range
     * @return bool
     */
    public function ip4Match(string $ip4, string $range): bool
    {
        if (!filter_var($ip4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (strpos($range, '/')) {
            list($subnet, $bits) = explode('/', $range);
            $bits = (int)$bits;
        } else {
            $subnet = $range;
            $bits = 32;
        }

        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ip = ip2long($ip4);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        return ($ip & $mask) == ($subnet & $mask);
    }

    /**
     * Check if an IPv6 address is within a given CIDR range.
     * @param string $ip6
     * @param string $range
     * @return bool
     */
    public function ip6Match(string $ip6, string $range): bool
    {
        if (!filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        if (strpos($range, '/')) {
            list($subnet, $bits) = explode('/', $range);
            $bits = (int)$bits;
        } else {
            $subnet = $range;
            $bits = 128;
        }

        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
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
            $ipBin .= str_pad(decbin(ord($ip[$i])), 8, '0', STR_PAD_LEFT);
            $subnetBin .= str_pad(decbin(ord($subnet[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Compare only the prefix bits
        return substr($ipBin, 0, $bits) === substr($subnetBin, 0, $bits);
    }
}

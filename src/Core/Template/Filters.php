<?php

namespace MintyPHP\Core\Template;

/**
 * Built-in filter functions for the template engine
 */
class Filters
{
    /**
     * Returns all builtin filter functions
     *
     * @return array<string,callable>
     */
    public static function getBuiltinFilters(): array
    {
        return [
            // Output control
            'raw' => function (mixed $value) {
                if ($value instanceof RawValue) {
                    return $value; // Already raw
                }
                return new RawValue(is_scalar($value) || $value === null ? (string)$value : '');
            },
            'debug' => fn(mixed $value) => new RawValue('<pre>' . (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') . '</pre>'),
            'd' => fn(mixed $value) => $value, // Just returns the value as-is

            // String filters
            'lower' => fn(string $str) => strtolower($str),
            'upper' => fn(string $str) => strtoupper($str),
            'capitalize' => fn(string $str) => ucfirst($str),
            'title' => fn(string $str) => ucwords($str),
            'trim' => fn(string $str) => trim($str),
            'truncate' => function (string $str, int $length = 255, string $end = '...') {
                if (strlen($str) <= $length) {
                    return $str;
                }
                return substr($str, 0, $length - strlen($end)) . $end;
            },
            'replace' => function (string $str, string $old, string $new, ?int $count = null) {
                if ($count === null) {
                    return str_replace($old, $new, $str);
                }
                if (!$old) {
                    return $str;
                }
                $parts = explode($old, $str);
                if (count($parts) <= $count) {
                    return implode($new, $parts);
                }
                $result = implode($new, array_slice($parts, 0, $count + 1));
                $result .= $old . implode($old, array_slice($parts, $count + 1));
                return $result;
            },
            'split' => function (string $str, string $sep = '') {
                if ($sep === '') {
                    return str_split($str);
                }
                return explode($sep, $str);
            },
            'urlencode' => fn(string $str) => urlencode($str),
            'reverse' => function (mixed $value) {
                if (is_string($value)) {
                    return strrev($value);
                }
                if (is_array($value)) {
                    return array_reverse($value);
                }
                return $value;
            },

            // Numeric filters
            'abs' => fn(float|int $num) => abs($num),
            'round' => function (float|int $num, int $precision = 0, string $method = 'common') {
                return match ($method) {
                    'ceil' => ceil($num * pow(10, $precision)) / pow(10, $precision),
                    'floor' => floor($num * pow(10, $precision)) / pow(10, $precision),
                    'down' => $num >= 0 ? floor($num * pow(10, $precision)) / pow(10, $precision) : ceil($num * pow(10, $precision)) / pow(10, $precision),
                    'even', 'banker' => round($num, $precision, PHP_ROUND_HALF_EVEN),
                    'odd' => round($num, $precision, PHP_ROUND_HALF_ODD),
                    'awayzero' => $num >= 0 ? ceil($num * pow(10, $precision)) / pow(10, $precision) : floor($num * pow(10, $precision)) / pow(10, $precision),
                    'tozero' => $num >= 0 ? floor($num * pow(10, $precision)) / pow(10, $precision) : ceil($num * pow(10, $precision)) / pow(10, $precision),
                    default => round($num, $precision),
                };
            },
            'sprintf' => fn(bool|float|int|string|null $value, string $format) => sprintf($format, $value),
            'filesizeformat' => function (float|int $bytes, bool $binary = false) {
                $units = $binary ? ['B', 'KiB', 'MiB', 'GiB', 'TiB'] : ['B', 'kB', 'MB', 'GB', 'TB'];
                $base = $binary ? 1024 : 1000;
                if ($bytes < $base) {
                    return $bytes . ' ' . $units[0];
                }
                $exp = intval(log($bytes) / log($base));
                $exp = min($exp, count($units) - 1);
                return sprintf('%.1f %s', $bytes / pow($base, $exp), $units[$exp]);
            },

            // Array/Collection filters
            'length' => function (mixed $value) {
                if (is_string($value)) {
                    return strlen($value);
                }
                if (is_array($value) || $value instanceof \Countable) {
                    return count($value);
                }
                return 0;
            },
            'count' => function (mixed $value) {
                if (is_string($value)) {
                    return strlen($value);
                }
                if (is_array($value) || $value instanceof \Countable) {
                    return count($value);
                }
                return 0;
            },
            'first' => function (mixed $value, ?int $n = null) {
                if (!is_array($value)) {
                    return '';
                }
                if ($n === null) {
                    return empty($value) ? '' : reset($value);
                }
                return array_slice($value, 0, $n);
            },
            'last' => function (mixed $value, ?int $n = null) {
                if (!is_array($value)) {
                    return '';
                }
                if ($n === null) {
                    return empty($value) ? '' : end($value);
                }
                return array_slice($value, -$n);
            },
            'join' => function (mixed $value, string $sep = '', ?string $attr = null) {
                if (!is_array($value)) {
                    return '';
                }
                if ($attr !== null) {
                    $value = array_map(fn($item) => is_array($item) && isset($item[$attr]) ? $item[$attr] : '', $value);
                }
                return implode($sep, $value);
            },
            'sum' => function (mixed $value, ?string $attr = null) {
                if (!is_array($value)) {
                    return 0;
                }
                if ($attr !== null) {
                    $value = array_map(fn($item) => is_array($item) && isset($item[$attr]) ? $item[$attr] : 0, $value);
                }
                return array_sum($value);
            },

            // Utility filters
            'default' => function (mixed $value, mixed $default, bool $boolean = false) {
                if ($boolean) {
                    return $value ? $value : $default;
                }
                return $value !== null ? $value : $default;
            },
            'attr' => function (mixed $value, string $name) {
                if (is_array($value) && isset($value[$name])) {
                    return $value[$name];
                }
                if (is_object($value) && isset($value->$name)) {
                    return $value->$name;
                }
                return '';
            },
        ];
    }
}

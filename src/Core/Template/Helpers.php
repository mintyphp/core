<?php

namespace MintyPHP\Core\Template;

use MintyPHP\Error\TemplateError;

/**
 * Helper utilities for template processing
 */
class Helpers
{
    /**
     * Explodes a string by a separator, respecting quoted substrings.
     * @param string $separator The separator string.
     * @param string $str The string to explode.
     * @param int $count Maximum number of elements to return (default -1 for no limit).
     * @return array<int,string> The exploded array of strings.
     */
    public static function explode(string $separator, string $str, int $count = -1): array
    {
        $tokens = [];
        $token = '';
        $quote = '"';
        $escape = '\\';
        $escaped = false;
        $quoted = false;
        for ($i = 0; $i < strlen($str); $i++) {
            $c = $str[$i];
            if (!$quoted) {
                if ($c == $quote) {
                    $quoted = true;
                } elseif (substr($str, $i, strlen($separator)) == $separator) {
                    // Special handling for | separator: check if it's part of || operator
                    if ($separator == '|' && $i + 1 < strlen($str) && $str[$i + 1] == '|') {
                        // This is part of || operator, don't split, add both characters
                        $token .= '||';
                        $i++; // Skip the next |
                        continue;
                    }
                    $tokens[] = $token;
                    if (count($tokens) == $count - 1) {
                        $token = substr($str, $i + strlen($separator));
                        break;
                    }
                    $token = '';
                    $i += strlen($separator) - 1;
                    continue;
                }
            } else {
                if (!$escaped) {
                    if ($c == $quote) {
                        $quoted = false;
                    } elseif ($c == $escape) {
                        $escaped = true;
                    }
                } else {
                    $escaped = false;
                }
            }
            $token .= $c;
        }
        $tokens[] = $token;
        return $tokens;
    }

    /**
     * Resolves a dot-notation path to retrieve a value from the data array.
     *
     * Supports nested access like 'user.name.first' to access $data['user']['name']['first'].
     *
     * @param string $path The dot-notation path to resolve (e.g., 'user.name').
     * @param array<string,mixed> $data The data array to search.
     * @return mixed The value at the specified path.
     * @throws \RuntimeException If any part of the path is not found.
     */
    public static function resolvePath(string $path, array $data): mixed
    {
        $current = $data;
        foreach (self::explode('.', $path) as $p) {
            if (!is_array($current) || !array_key_exists($p, $current)) {
                throw new TemplateError("path `$p` not found");
            }
            $current = &$current[$p];
        }
        return $current;
    }

    /**
     * Applies a chain of filter functions to a value.
     *
     * Functions are applied in order, with each function receiving the output of the previous.
     * Function arguments can be literals (strings in quotes, numbers) or paths to data.
     * Example: {{name|upper|substr(0,10)}}
     *
     * @param mixed $value The initial value to transform.
     * @param array<int,string> $parts Array of function calls to apply (e.g., ['upper', 'substr(0,10)']).
     * @param array<string,callable> $functions Available custom functions.
     * @param array<string,mixed> $data The data context for resolving argument paths.
     * @return mixed The final transformed value after applying all functions.
     * @throws \RuntimeException If a referenced function is not found.
     */
    public static function applyFunctions(mixed $value, array $parts, array $functions, array $data): mixed
    {
        foreach ($parts as $part) {
            $function = self::explode('(', rtrim($part, ')'), 2);
            $f = $function[0];
            $arguments = isset($function[1]) ? self::explode(',', $function[1]) : [];
            $arguments = array_map(function ($argument) use ($data) {
                $argument = trim($argument);
                $len = strlen($argument);
                if ($len > 0 && $argument[0] == '"' && $argument[$len - 1] == '"') {
                    $argument = stripcslashes(substr($argument, 1, $len - 2));
                } else if (is_numeric($argument)) {
                    // Keep as is - will be cast as needed
                } else if ($argument === 'true') {
                    $argument = true;
                } else if ($argument === 'false') {
                    $argument = false;
                } else if ($argument === 'null') {
                    $argument = null;
                } else {
                    $argument = Helpers::resolvePath($argument, $data);
                }
                return $argument;
            }, $arguments);
            array_unshift($arguments, $value);
            if (isset($functions[$f])) {
                $value = call_user_func_array($functions[$f], $arguments);
            } else {
                throw new TemplateError("function `$f` not found");
            }
        }
        return $value;
    }
}

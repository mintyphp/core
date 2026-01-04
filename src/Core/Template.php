<?php

namespace MintyPHP\Core;

use MintyPHP\Error\TemplateError;

/**
 * Internal class to mark values that should not be escaped
 */
class RawValue
{
    public function __construct(public string $value) {}
}

/**
 * Internal class representing a token in an expression
 */
class ExpressionToken
{
    public function __construct(
        public string $type,
        public string $value
    ) {}

    public static function number(string $value): self
    {
        return new self('number', $value);
    }

    public static function string(string $value): self
    {
        return new self('string', $value);
    }

    public static function identifier(string $value): self
    {
        return new self('identifier', $value);
    }

    public static function operator(string $value): self
    {
        return new self('operator', $value);
    }

    public static function parenthesis(string $value): self
    {
        return new self('parenthesis', $value);
    }

    public function isOperand(): bool
    {
        return in_array($this->type, ['number', 'string', 'identifier']);
    }

    public function isOperator(): bool
    {
        return $this->type === 'operator';
    }

    public function isParenthesis(): bool
    {
        return $this->type === 'parenthesis';
    }
}

/**
 * Internal class representing an expression with operators
 */
class Expression
{
    private const OPERATORS = [
        'or' => ['precedence' => 1, 'associativity' => 'left'],
        '||' => ['precedence' => 1, 'associativity' => 'left'],
        'and' => ['precedence' => 2, 'associativity' => 'left'],
        '&&' => ['precedence' => 2, 'associativity' => 'left'],
        '==' => ['precedence' => 3, 'associativity' => 'left'],
        '!=' => ['precedence' => 3, 'associativity' => 'left'],
        'is' => ['precedence' => 3, 'associativity' => 'left'],
        '<' => ['precedence' => 4, 'associativity' => 'left'],
        '>' => ['precedence' => 4, 'associativity' => 'left'],
        '<=' => ['precedence' => 4, 'associativity' => 'left'],
        '>=' => ['precedence' => 4, 'associativity' => 'left'],
        '+' => ['precedence' => 5, 'associativity' => 'left'],
        '-' => ['precedence' => 5, 'associativity' => 'left'],
        '*' => ['precedence' => 6, 'associativity' => 'left'],
        '/' => ['precedence' => 6, 'associativity' => 'left'],
        '%' => ['precedence' => 6, 'associativity' => 'left'],
        'not' => ['precedence' => 7, 'associativity' => 'right'],
    ];

    /** @var array<int,ExpressionToken> */
    private array $tokens;

    public function __construct(string $expression)
    {
        $this->tokens = $this->tokenize($expression);
    }

    /**
     * Tokenizes an expression into operators, operands, and parentheses
     * 
     * @param string $expression
     * @return array<int,ExpressionToken>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $expression = trim($expression);
        $i = 0;
        $len = strlen($expression);

        while ($i < $len) {
            $char = $expression[$i];

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Handle parentheses
            if ($char === '(' || $char === ')') {
                $tokens[] = ExpressionToken::parenthesis($char);
                $i++;
                continue;
            }

            // Handle word-based operators (and, or, not)
            if (ctype_alpha($char)) {
                $word = '';
                $start = $i;
                while ($i < $len && ctype_alpha($expression[$i])) {
                    $word .= $expression[$i];
                    $i++;
                }
                if (isset(self::OPERATORS[$word])) {
                    $tokens[] = ExpressionToken::operator($word);
                    continue;
                }
                // Not an operator, reset and handle as identifier
                $i = $start;
            }

            // Handle two-character operators
            if ($i < $len - 1) {
                $twoChar = substr($expression, $i, 2);
                if (isset(self::OPERATORS[$twoChar])) {
                    $tokens[] = ExpressionToken::operator($twoChar);
                    $i += 2;
                    continue;
                }
            }

            // Handle single-character operators
            if (isset(self::OPERATORS[$char])) {
                $tokens[] = ExpressionToken::operator($char);
                $i++;
                continue;
            }

            // Handle numbers
            if (ctype_digit($char) || ($char === '.' && $i < $len - 1 && ctype_digit($expression[$i + 1]))) {
                $num = '';
                while ($i < $len && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $num .= $expression[$i];
                    $i++;
                }
                $tokens[] = ExpressionToken::number($num);
                continue;
            }

            // Handle string literals
            if ($char === '"') {
                $str = '';
                $i++; // Skip opening quote
                $escaped = false;
                while ($i < $len) {
                    if ($escaped) {
                        $str .= $expression[$i];
                        $escaped = false;
                    } elseif ($expression[$i] === '\\') {
                        $escaped = true;
                    } elseif ($expression[$i] === '"') {
                        $i++; // Skip closing quote
                        break;
                    } else {
                        $str .= $expression[$i];
                    }
                    $i++;
                }
                $tokens[] = ExpressionToken::string($str);
                continue;
            }

            // Handle identifiers/paths (with dots for nested access)
            // Also handle function-like constructs for tests: divisibleby(3)
            if (ctype_alpha($char) || $char === '_') {
                $ident = '';
                while ($i < $len && (ctype_alnum($expression[$i]) || $expression[$i] === '_' || $expression[$i] === '.')) {
                    $ident .= $expression[$i];
                    $i++;
                }
                
                // Check if followed by '(' for function-like test (e.g., divisibleby(3))
                // But only in the context of 'is' operator
                $j = $i;
                while ($j < $len && ctype_space($expression[$j])) {
                    $j++;
                }
                if ($j < $len && $expression[$j] === '(') {
                    // This could be a test function, include the parentheses and content
                    $parenDepth = 0;
                    while ($j < $len) {
                        $ident .= $expression[$j];
                        if ($expression[$j] === '(') {
                            $parenDepth++;
                        } elseif ($expression[$j] === ')') {
                            $parenDepth--;
                            if ($parenDepth === 0) {
                                $j++;
                                break;
                            }
                        }
                        $j++;
                    }
                    $i = $j;
                }
                
                $tokens[] = ExpressionToken::identifier($ident);
                continue;
            }

            // Unknown character, skip it
            $i++;
        }

        return $tokens;
    }

    /**
     * Evaluates the expression with given data context
     * 
     * @param array<string,mixed> $data
     * @param callable $resolvePath
     * @return mixed
     */
    public function evaluate(array $data, callable $resolvePath): mixed
    {
        $rpn = $this->toReversePolishNotation();
        return $this->evaluateRPN($rpn, $data, $resolvePath);
    }

    /**
     * Converts infix notation to Reverse Polish Notation using Shunting Yard algorithm
     * 
     * @return array<int,ExpressionToken>
     */
    private function toReversePolishNotation(): array
    {
        $output = [];
        $operators = [];

        foreach ($this->tokens as $token) {
            if ($token->isOperand()) {
                // Operand (number, string, identifier)
                $output[] = $token;
            } elseif ($token->isParenthesis() && $token->value === '(') {
                $operators[] = $token;
            } elseif ($token->isParenthesis() && $token->value === ')') {
                // Pop operators until we find the matching '('
                while (!empty($operators)) {
                    $top = end($operators);
                    if ($top->isParenthesis() && $top->value === '(') {
                        break;
                    }
                    $output[] = array_pop($operators);
                }
                if (!empty($operators)) {
                    array_pop($operators); // Remove the '('
                }
            } elseif ($token->isOperator()) {
                // Operator
                $o1 = $token->value;
                while (!empty($operators)) {
                    $top = end($operators);
                    if ($top->isParenthesis()) {
                        break;
                    }
                    if (!$top->isOperator()) {
                        break;
                    }
                    $o2 = $top->value;
                    $o1Prec = self::OPERATORS[$o1]['precedence'];
                    $o2Prec = self::OPERATORS[$o2]['precedence'];
                    $o1Assoc = self::OPERATORS[$o1]['associativity'];

                    if (($o1Assoc === 'left' && $o1Prec <= $o2Prec) ||
                        ($o1Assoc === 'right' && $o1Prec < $o2Prec)
                    ) {
                        $output[] = array_pop($operators);
                    } else {
                        break;
                    }
                }
                $operators[] = $token;
            }
        }

        // Pop remaining operators
        while (!empty($operators)) {
            $output[] = array_pop($operators);
        }

        return $output;
    }

    /**
     * Evaluates an expression in Reverse Polish Notation
     * 
     * @param array<int,ExpressionToken> $rpn
     * @param array<string,mixed> $data
     * @param callable $resolvePath
     * @return mixed
     */
    private function evaluateRPN(array $rpn, array $data, callable $resolvePath): mixed
    {
        $stack = [];

        foreach ($rpn as $idx => $token) {
            if ($token->isOperand()) {
                // Operand
                if ($token->type === 'number') {
                    $stack[] = strpos($token->value, '.') !== false ?
                        (float)$token->value : (int)$token->value;
                } elseif ($token->type === 'string') {
                    $stack[] = $token->value;
                } elseif ($token->type === 'identifier') {
                    // Check if this identifier will be used with 'is' operator
                    // It could be either left or right operand
                    $isForTest = false;
                    $isLeftOperandOfIs = false;
                    $nextIdx = $idx + 1;
                    
                    // Check if directly followed by 'is' (this is left operand)
                    // In RPN: left, right, is
                    // So we need to check: is the token at idx+2 an 'is' operator?
                    // Actually we need to skip one more token (the right operand)
                    if ($idx + 2 < count($rpn)) {
                        $skipIdx = $idx + 1;
                        // Skip potential 'not' operators
                        while ($skipIdx < count($rpn) && $rpn[$skipIdx]->isOperator() && $rpn[$skipIdx]->value === 'not') {
                            $skipIdx++;
                        }
                        // Check if after skipping we have 'is'
                        if ($skipIdx + 1 < count($rpn) && $rpn[$skipIdx + 1]->isOperator() && $rpn[$skipIdx + 1]->value === 'is') {
                            $isLeftOperandOfIs = true;
                        }
                    }
                    
                    // Check if it's the right operand (test name)
                    // Skip 'not' operators
                    while ($nextIdx < count($rpn) && $rpn[$nextIdx]->isOperator() && $rpn[$nextIdx]->value === 'not') {
                        $nextIdx++;
                    }
                    if ($nextIdx < count($rpn) && $rpn[$nextIdx]->isOperator() && $rpn[$nextIdx]->value === 'is') {
                        $isForTest = true;
                    }
                    
                    if ($isForTest) {
                        // Keep as identifier string for test name, don't resolve
                        $stack[] = ['__test_identifier' => $token->value];
                    } elseif ($isLeftOperandOfIs) {
                        // This is the left operand of 'is', resolve but catch errors
                        try {
                            $stack[] = $resolvePath($token->value, $data);
                        } catch (\Throwable $e) {
                            // For 'is defined' / 'is undefined' tests, undefined variables should be null
                            $stack[] = null;
                        }
                    } else {
                        $stack[] = $resolvePath($token->value, $data);
                    }
                }
            } elseif ($token->isOperator()) {
                // Operator
                $op = $token->value;
                if ($op === 'not') {
                    // Unary operator
                    if (empty($stack)) {
                        throw new TemplateError("not enough operands for 'not'");
                    }
                    $operand = array_pop($stack);
                    
                    // Check if next operator is 'is' - if so, modify the identifier
                    if ($idx + 1 < count($rpn) && $rpn[$idx + 1]->isOperator() && $rpn[$idx + 1]->value === 'is') {
                        // This 'not' is part of "is not X" construct
                        if (is_array($operand) && isset($operand['__test_identifier'])) {
                            // Prepend 'not.' to the test identifier
                            $stack[] = ['__test_identifier' => 'not.' . $operand['__test_identifier']];
                        } else {
                            // Normal not operation
                            $stack[] = !$operand;
                        }
                    } else {
                        // Normal not operation
                        $stack[] = !$operand;
                    }
                } else {
                    // Binary operator
                    if (count($stack) < 2) {
                        throw new TemplateError("not enough operands for '$op'");
                    }
                    /** @var float|int|string $right */
                    $right = array_pop($stack);
                    /** @var float|int|string $left */
                    $left = array_pop($stack);

                    // Special handling for 'is' operator with tests
                    if ($op === 'is') {
                        // Extract test name from marker if present
                        $testName = $right;
                        if (is_array($right) && isset($right['__test_identifier'])) {
                            $testName = $right['__test_identifier'];
                        }
                        $result = $this->applyTest($left, $testName, $data, $resolvePath);
                        $stack[] = $result;
                        continue;
                    }

                    // Check for logical, numeric or string operations and cast accordingly
                    $result = match ($op) {
                        // Logical operators - no casting needed, PHP handles truthiness
                        'or', '||' => $left || $right,
                        'and', '&&' => $left && $right,
                        // Comparison operators - no casting needed, PHP type juggling handles it
                        '==' => $left == $right,
                        '!=' => $left != $right,
                        '<' => $left < $right,
                        '>' => $left > $right,
                        '<=' => $left <= $right,
                        '>=' => $left >= $right,
                        // Arithmetic operators - cast to numeric types
                        '+' => (!is_numeric($left) || !is_numeric($right))
                            ? ((string)$left . (string)$right)  // String concatenation
                            : +$left + +$right,  // Numeric addition
                        '-' => (is_numeric($left) ? +$left : 0) - (is_numeric($right) ? +$right : 0),
                        '*' => (is_numeric($left) ? +$left : 0) * (is_numeric($right) ? +$right : 0),
                        '/' => (is_numeric($right) && $right != 0)
                            ? (is_numeric($left) ? +$left : 0) / +$right
                            : throw new TemplateError("division by zero"),
                        '%' => (is_numeric($right) && $right != 0)
                            ? (int)(is_numeric($left) ? +$left : 0) % (int)+$right
                            : throw new TemplateError("modulo by zero"),
                        default => throw new TemplateError("unknown operator: $op"),
                    };

                    $stack[] = $result;
                }
            }
        }

        if (count($stack) !== 1) {
            throw new TemplateError("malformed expression");
        }

        return array_pop($stack);
    }

    /**
     * Applies a test to a value (for `is` operator)
     *
     * @param mixed $value The value to test
     * @param mixed $testSpec The test specification (identifier like "even", "not.defined", or "divisibleby(3)")
     * @param array<string,mixed> $data Data context
     * @param callable $resolvePath Path resolver function
     * @return bool|int The test result (true/false or 1/0)
     */
    private function applyTest(mixed $value, mixed $testSpec, array $data, callable $resolvePath): bool|int
    {
        $isNegated = false;
        $testName = '';

        if (is_string($testSpec)) {
            // Check if it starts with "not."
            if (str_starts_with($testSpec, 'not.')) {
                $isNegated = true;
                $testName = substr($testSpec, 4);
            } else {
                $testName = $testSpec;
            }
        } else {
            // testSpec was resolved, shouldn't happen in normal cases
            $testName = (string)$testSpec;
        }

        // Handle "divisibleby(n)" test first (before the match)
        if (str_starts_with($testName, 'divisibleby(') && str_ends_with($testName, ')')) {
            $n = substr($testName, 12, -1);
            if (is_numeric($n) && is_numeric($value)) {
                $n = (int)$n;
                $result = $n !== 0 && (int)$value % $n === 0;
            } else {
                $result = false;
            }
            return $isNegated ? !$result : $result;
        }

        // Apply the test
        $result = match ($testName) {
            'defined' => $value !== null,
            'undefined' => $value === null,
            'null' => $value === null,
            'even' => is_numeric($value) && (int)$value % 2 === 0,
            'odd' => is_numeric($value) && (int)$value % 2 !== 0,
            'number' => is_numeric($value),
            'string' => is_string($value),
            'iterable' => is_array($value) || is_string($value) || $value instanceof \Traversable,
            default => throw new TemplateError("unknown test: $testName"),
        };

        return $isNegated ? !$result : $result;
    }

    /**
     * Gets the original expression string for filters
     * 
     * @return string
     */
    public function getFilterExpression(): string
    {
        // For now, if expression contains operators, return empty to skip filters
        // In the future, we could separate expression and filters differently
        foreach ($this->tokens as $token) {
            if ($token->isOperator()) {
                return ''; // Has operators, no filter path
            }
        }
        // Simple identifier, return it
        if (count($this->tokens) === 1 && $this->tokens[0]->type === 'identifier') {
            return $this->tokens[0]->value;
        }
        return '';
    }
}

/**
 * Internal class representing a node in the template syntax tree
 */
class TreeNode
{
    public string $type;
    public Expression|string|null $expression;
    /** @var array<int,TreeNode> */
    public array $children;
    public mixed $value;

    public function __construct(string $type, Expression|string|null $expression)
    {
        $this->type = $type;
        $this->expression = $expression;
        $this->children = [];
        $this->value = null;
    }
}

/**
 * Template engine for MintyPHP
 * 
 * Provides functionality to render templates with variable interpolation,
 * control structures (if/for), and custom filters. Supports HTML escaping
 * and raw output.
 */
class Template
{
    // Default static configuration
    public static string $__escape = 'html';

    /** @var callable|null */
    private $templateLoader = null;

    /**
     * Constructor
     * @param string $escape
     * @param callable|null $templateLoader Function to load templates by name for extends/include
     */
    public function __construct(private string $escape, ?callable $templateLoader = null)
    {
        $this->templateLoader = $templateLoader;
    }

    /**
     * Escapes a string based on the specified escape type.
     *
     * @param string|RawValue $string The string to escape.
     * @return string The escaped string.
     */
    private function escape(string|RawValue $string): string
    {
        if ($string instanceof RawValue) {
            return $string->value;
        }
        return match ($this->escape) {
            'html' => htmlspecialchars($string, ENT_QUOTES, 'UTF-8'),
            default => $string,
        };
    }

    /**
     * Renders a template string with the provided data and custom functions.
     *
     * @param string $template The template string containing placeholders like {{variable}}.
     * @param array<string,mixed> $data Associative array of data to use in the template.
     * @param array<string,callable> $functions Associative array of custom functions available in the template.
     * @return string The rendered template string.
     * @throws \RuntimeException If there is an error during rendering.
     */
    public function render(string $template, array $data, array $functions = []): string
    {
        $tokens = $this->tokenize($template);
        $tree = $this->createSyntaxTree($tokens);
        // Add built-in filters
        $functions = array_merge($this->getBuiltinFilters(), $functions);
        return $this->renderChildren($tree, $data, $functions);
    }

    /**
     * Returns all builtin filter functions
     *
     * @return array<string,callable>
     */
    private function getBuiltinFilters(): array
    {
        return [
            // Output control
            'raw' => function (mixed $value) {
                if ($value instanceof RawValue) {
                    return $value; // Already raw
                }
                return new RawValue((string)$value);
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

    /**
     * Tokenizes a template string by splitting it into literal text and expressions.
     *
     * Expressions are delimited by {{ }} for variables and {% %} for control structures.
     * The resulting array alternates between literal text (even indices) and expressions (odd indices).
     * Control structure tokens are prefixed with '@' to distinguish them.
     *
     * @param string $template The template string to tokenize.
     * @return array<int,string> Array of tokens alternating between literals and expressions.
     */
    private function tokenize(string $template): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($template);
        $literal = '';

        while ($i < $len) {
            // Check for comment {#
            if ($i < $len - 1 && $template[$i] === '{' && $template[$i + 1] === '#') {
                // Check if this comment is on a line with only whitespace (standalone line)
                $lineStart = strrpos($literal, "\n");
                $beforeTag = '';
                $isStandaloneLine = false;

                if ($lineStart === false) {
                    // No newline found - check if we're at the start of the template
                    $beforeTag = $literal;
                    // Only treat as standalone if literal is empty (true start) or only whitespace AND we're at position 0 in template
                    $isStandaloneLine = $literal === '' || ($i === strlen($beforeTag) && trim($beforeTag) === '');
                } else {
                    // Get content after the last newline
                    $beforeTag = substr($literal, $lineStart + 1);
                    // This is standalone if everything after the newline is whitespace
                    $isStandaloneLine = trim($beforeTag) === '';
                }

                // If standalone, remove just the whitespace on this line (not the newline)
                if ($isStandaloneLine && $lineStart !== false) {
                    $literal = substr($literal, 0, $lineStart + 1);
                } elseif ($isStandaloneLine && $lineStart === false) {
                    $literal = '';
                }

                // Skip the comment - just find the closing #} and discard everything
                $i += 2;
                while ($i < $len - 1) {
                    if ($template[$i] === '#' && $template[$i + 1] === '}') {
                        $i += 2;

                        // If this is a standalone line, consume the trailing newline
                        if ($isStandaloneLine && $i < $len && $template[$i] === "\n") {
                            $i++;
                        } elseif ($isStandaloneLine && $i < $len - 1 && $template[$i] === "\r" && $template[$i + 1] === "\n") {
                            $i += 2;
                        }
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Check for control structure {%
            if ($i < $len - 1 && $template[$i] === '{' && $template[$i + 1] === '%') {
                // Check if this control structure is on a line with only whitespace
                // A line is "standalone" if it starts after a newline (or at template start when literal is empty)
                // and contains only whitespace before the tag
                $lineStart = strrpos($literal, "\n");
                $beforeTag = '';
                $isStandaloneLine = false;

                if ($lineStart === false) {
                    // No newline found - check if we're at the start of the template
                    $beforeTag = $literal;
                    // Only treat as standalone if literal is empty (true start) or only whitespace AND we're at position 0 in template
                    $isStandaloneLine = $literal === '' || ($i === strlen($beforeTag) && trim($beforeTag) === '');
                } else {
                    // Get content after the last newline
                    $beforeTag = substr($literal, $lineStart + 1);
                    // This is standalone if everything after the newline is whitespace
                    $isStandaloneLine = trim($beforeTag) === '';
                }

                // If standalone, remove just the whitespace on this line (not the newline)
                if ($isStandaloneLine && $lineStart !== false) {
                    $literal = substr($literal, 0, $lineStart + 1);
                } elseif ($isStandaloneLine && $lineStart === false) {
                    $literal = '';
                }

                $tokens[] = $literal;
                $literal = '';
                $i += 2;
                $expr = '';
                $quoted = false;
                $escaped = false;
                while ($i < $len - 1) {
                    $char = $template[$i];
                    if (!$escaped) {
                        if ($char === '"') {
                            $quoted = !$quoted;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif (!$quoted && $char === '%' && $template[$i + 1] === '}') {
                            $tokens[] = '@' . trim($expr);
                            $i += 2;

                            // If this is a standalone line, consume the trailing newline
                            if ($isStandaloneLine && $i < $len && $template[$i] === "\n") {
                                $i++;
                            } elseif ($isStandaloneLine && $i < $len - 1 && $template[$i] === "\r" && $template[$i + 1] === "\n") {
                                $i += 2;
                            }
                            break;
                        }
                    } else {
                        $escaped = false;
                    }
                    $expr .= $char;
                    $i++;
                }
                continue;
            }

            // Check for variable {{
            if ($i < $len - 1 && $template[$i] === '{' && $template[$i + 1] === '{') {
                $tokens[] = $literal;
                $literal = '';
                $i += 2;
                $expr = '';
                $quoted = false;
                $escaped = false;
                while ($i < $len - 1) {
                    $char = $template[$i];
                    if (!$escaped) {
                        if ($char === '"') {
                            $quoted = !$quoted;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif (!$quoted && $char === '}' && $template[$i + 1] === '}') {
                            $tokens[] = trim($expr);
                            $i += 2;
                            break;
                        }
                    } else {
                        $escaped = false;
                    }
                    $expr .= $char;
                    $i++;
                }
                continue;
            }

            // Regular character
            $literal .= $template[$i];
            $i++;
        }

        $tokens[] = $literal;
        return $tokens;
    }

    /**
     * Explodes a string by a separator, respecting quoted substrings.
     * @param string $separator The separator string.
     * @param string $str The string to explode.
     * @param int $count Maximum number of elements to return (default -1 for no limit).
     * @return array<int,string> The exploded array of strings.
     */
    private function explode(string $separator, string $str, int $count = -1): array
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
     * Creates an abstract syntax tree from tokens.
     *
     * Processes the token array and builds a hierarchical tree structure representing
     * the template's control flow (if/else/for blocks) and variable interpolations.
     *
     * @param array<int,string> $tokens Array of tokens from tokenize().
     * @return TreeNode The root node of the syntax tree.
     */
    private function createSyntaxTree(array &$tokens): TreeNode
    {
        $root = new TreeNode('root', null);
        $current = $root;
        $stack = [];
        foreach ($tokens as $i => $token) {
            if ($i % 2 == 1) {
                // Control structures are prefixed with @
                $isControl = str_starts_with($token, '@');
                if ($isControl) {
                    $token = substr($token, 1); // Remove @ prefix
                }

                if ($token == 'endif') {
                    $type = 'endif';
                    $expression = null;
                } elseif ($token == 'endfor') {
                    $type = 'endfor';
                    $expression = null;
                } elseif ($token == 'endblock') {
                    $type = 'endblock';
                    $expression = null;
                } elseif ($token == 'else') {
                    $type = 'else';
                    $expression = null;
                } elseif (str_starts_with($token, 'elseif ')) {
                    $type = 'elseif';
                    $expression = trim(substr($token, 7));
                } elseif (str_starts_with($token, 'if ')) {
                    $type = 'if';
                    $expression = trim(substr($token, 3));
                } elseif (str_starts_with($token, 'for ')) {
                    $type = 'for';
                    $expression = trim(substr($token, 4));
                } elseif (str_starts_with($token, 'block ')) {
                    $type = 'block';
                    $expression = trim(substr($token, 6));
                } elseif (str_starts_with($token, 'extends ')) {
                    $type = 'extends';
                    $expression = trim(substr($token, 8));
                } elseif (str_starts_with($token, 'include ')) {
                    $type = 'include';
                    $expression = trim(substr($token, 8));
                } else {
                    $type = 'var';
                    $expression = $token;
                }
                if (in_array($type, ['endif', 'endfor', 'endblock', 'elseif', 'else'])) {
                    if (count($stack)) {
                        $current = array_pop($stack);
                    }
                }
                if (in_array($type, ['var', 'extends', 'include'])) {
                    $node = new TreeNode($type, $expression);
                    array_push($current->children, $node);
                }
                if (in_array($type, ['if', 'for', 'elseif', 'else', 'block'])) {
                    $node = new TreeNode($type, $expression);
                    array_push($current->children, $node);
                    array_push($stack, $current);
                    $current = $node;
                }
            } else {
                array_push($current->children, new TreeNode('lit', $token));
            }
        }
        return $root;
    }

    /**
     * Renders all child nodes of a given node.
     *
     * Iterates through the children of a node and dispatches to the appropriate
     * render method based on the child's type (if, for, var, lit, else, elseif, block, extends, include).
     *
     * @param TreeNode $node The parent node whose children should be rendered.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output of all child nodes.
     */
    private function renderChildren(TreeNode $node, array $data, array $functions): string
    {
        $result = '';
        $ifNodes = [];

        // First pass: check for extends directive (must be first non-whitespace)
        $hasExtends = false;
        $extendsNode = null;
        $blocks = [];

        foreach ($node->children as $child) {
            if ($child->type === 'extends') {
                $hasExtends = true;
                $extendsNode = $child;
                break;
            } elseif ($child->type === 'lit' && is_string($child->expression) && trim($child->expression) === '') {
                // Skip whitespace-only literals when looking for extends
                continue;
            } else {
                // Non-whitespace, non-extends found
                break;
            }
        }

        // If we have extends, collect all blocks and render the parent
        if ($hasExtends) {
            foreach ($node->children as $child) {
                if ($child->type === 'block' && is_string($child->expression)) {
                    $blocks[trim($child->expression)] = $child;
                }
            }

            return $this->renderExtendsNode($extendsNode, $blocks, $data, $functions);
        }

        // Normal rendering without extends
        foreach ($node->children as $child) {
            switch ($child->type) {
                case 'if':
                    $result .= $this->renderIfNode($child, $data, $functions);
                    $ifNodes = [$child];
                    break;
                case 'elseif':
                    $result .= $this->renderElseIfNode($child, $ifNodes, $data, $functions);
                    array_push($ifNodes, $child);
                    break;
                case 'else':
                    $result .= $this->renderElseNode($child, $ifNodes, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'for':
                    $result .= $this->renderForNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'block':
                    $result .= $this->renderBlockNode($child, [], $data, $functions);
                    $ifNodes = [];
                    break;
                case 'include':
                    $result .= $this->renderIncludeNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'var':
                    $result .= $this->renderVarNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'lit':
                    if (is_string($child->expression)) {
                        $result .= $child->expression;
                    }
                    $ifNodes = [];
                    break;
            }
        }
        return $result;
    }

    /**
     * Renders an 'if' conditional node.
     *
     * Evaluates the condition expression and renders the node's children if truthy.
     * The evaluated value is stored in the node for use by subsequent elseif/else nodes.
     *
     * @param TreeNode $node The if node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if condition is true, empty string otherwise.
     */
    private function renderIfNode(TreeNode $node, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{% if !!invalid expression %}');
        }

        $expressionStr = $node->expression;
        $parts = $this->explode('|', $expressionStr);
        $exprPart = array_shift($parts);
        if ($exprPart === null) {
            return $this->escape('{% if ' . $expressionStr . '!!invalid expression %}');
        }

        try {
            $expr = new Expression($exprPart);
            $value = $expr->evaluate($data, function (string $path, array $data) {
                /** @var array<string,mixed> $data */
                return $this->resolvePath($path, $data);
            });
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{% if ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
        }
        $result = '';
        if ($value) {
            $result .= $this->renderChildren($node, $data, $functions);
        }
        $node->value = $value;
        return $result;
    }

    /**
     * Renders an 'elseif' conditional node.
     *
     * Only evaluates and renders if no previous if/elseif in the chain was true.
     * Checks the values of preceding if/elseif nodes to determine whether to evaluate.
     *
     * @param TreeNode $node The elseif node to render.
     * @param array<int,TreeNode> $ifNodes Array of preceding if/elseif nodes in the chain.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if condition is true and no previous conditions were true, empty string otherwise.
     */
    private function renderElseIfNode(TreeNode $node, array $ifNodes, array $data, array $functions): string
    {
        if (count($ifNodes) < 1 || $ifNodes[0]->type != 'if') {
            return $this->escape("{% elseif !!could not find matching `if` %}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            if ($node->expression === null || !is_string($node->expression)) {
                return $this->escape('{% elseif !!invalid expression %}');
            }

            $expressionStr = $node->expression;
            $parts = $this->explode('|', $expressionStr);
            $exprPart = array_shift($parts);
            if ($exprPart === null) {
                return $this->escape('{% elseif ' . $expressionStr . '!!invalid expression %}');
            }
            try {
                $expr = new Expression($exprPart);
                $value = $expr->evaluate($data, function (string $path, array $data) {
                    /** @var array<string,mixed> $data */
                    return $this->resolvePath($path, $data);
                });
                $value = $this->applyFunctions($value, $parts, $functions, $data);
            } catch (\Throwable $e) {
                return $this->escape('{% elseif ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
            }
            if ($value) {
                $result .= $this->renderChildren($node, $data, $functions);
            }
        }
        $node->value = $value;
        return $result;
    }

    /**
     * Renders an 'else' node.
     *
     * Only renders if no previous if/elseif in the chain was true.
     *
     * @param TreeNode $node The else node to render.
     * @param array<int,TreeNode> $ifNodes Array of preceding if/elseif nodes in the chain.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if no previous conditions were true, empty string otherwise.
     */
    private function renderElseNode(TreeNode $node, array $ifNodes, array $data, array $functions): string
    {
        if (count($ifNodes) < 1 || $ifNodes[0]->type != 'if') {
            return $this->escape("{% else !!could not find matching `if` %}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            $result .= $this->renderChildren($node, $data, $functions);
        }
        return $result;
    }

    /**
     * Renders a 'for' loop node.
     *
     * Iterates over an array and renders the node's children for each element.
     * Supports two formats:
     * - {% for item in array %} - iterates with values only
     * - {% for key, value in array %} - iterates with both keys and values
     *
     * @param TreeNode $node The for node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output for each iteration.
     */
    private function renderForNode(TreeNode $node, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{% for !!invalid expression %}');
        }

        $expressionStr = $node->expression;

        // Parse "for key, value in array" or "for value in array"
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*(?:\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*)?)\s+in\s+(.+)$/', $expressionStr, $matches)) {
            return $this->escape('{% for ' . $expressionStr . '!!invalid syntax, expected "item in array" or "key, value in array" %}');
        }

        $vars = $matches[1];
        $arrayExpr = $matches[2];

        // Check if we have "key, value" or just "value"
        if (str_contains($vars, ',')) {
            [$key, $var] = array_map('trim', explode(',', $vars, 2));
        } else {
            $var = trim($vars);
            $key = false;
        }

        // Parse filters from array expression
        $parts = $this->explode('|', $arrayExpr);
        $path = array_shift($parts);
        if ($path === null) {
            return $this->escape('{% for ' . $expressionStr . '!!invalid expression %}');
        }

        try {
            $value = $this->resolvePath(trim($path), $data);
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{% for ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
        }
        if (!is_array($value)) {
            return $this->escape('{% for ' . $expressionStr . '!!expression must evaluate to an array %}');
        }
        $result = '';
        foreach ($value as $k => $v) {
            $data = array_merge($data, $key ? [$key => $k, $var => $v] : [$var => $v]);
            $result .= $this->renderChildren($node, $data, $functions);
        }
        return $result;
    }

    /**
     * Renders a 'block' node.
     *
     * Blocks are used for template inheritance. If a block override is provided,
     * it will be rendered instead of the default block content.
     *
     * @param TreeNode $node The block node to render.
     * @param array<string,TreeNode> $blockOverrides Override blocks from child template.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered block content.
     */
    private function renderBlockNode(TreeNode $node, array $blockOverrides, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{% block !!invalid expression %}');
        }

        $blockName = trim($node->expression);

        // If we have an override for this block, use it
        if (isset($blockOverrides[$blockName])) {
            return $this->renderChildren($blockOverrides[$blockName], $data, $functions);
        }

        // Otherwise render the default content, but pass blockOverrides for nested blocks
        return $this->renderChildrenWithBlocks($node, $blockOverrides, $data, $functions);
    }

    /**
     * Renders an 'extends' node.
     *
     * Loads the parent template and renders it with block overrides from the child.
     *
     * @param TreeNode|null $node The extends node to render.
     * @param array<string,TreeNode> $blocks Blocks defined in the child template.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered parent template with block overrides.
     */
    private function renderExtendsNode(?TreeNode $node, array $blocks, array $data, array $functions): string
    {
        if ($node === null || $node->expression === null || !is_string($node->expression)) {
            return $this->escape('{% extends !!invalid expression %}');
        }

        if ($this->templateLoader === null) {
            return $this->escape('{% extends !!template loader not configured %}');
        }

        $templateName = trim($node->expression);
        // Remove quotes if present
        if ((str_starts_with($templateName, '"') && str_ends_with($templateName, '"')) ||
            (str_starts_with($templateName, "'") && str_ends_with($templateName, "'"))
        ) {
            $templateName = substr($templateName, 1, -1);
        }

        try {
            /** @var string|null $parentTemplate */
            $parentTemplate = ($this->templateLoader)($templateName);
            if ($parentTemplate === null) {
                return $this->escape('{% extends "' . $templateName . '" !!template not found %}');
            }

            // Parse parent template
            $tokens = $this->tokenize($parentTemplate);
            $tree = $this->createSyntaxTree($tokens);

            // Render parent with block overrides
            return $this->renderChildrenWithBlocks($tree, $blocks, $data, $functions);
        } catch (\Throwable $e) {
            return $this->escape('{% extends "' . $templateName . '" !!' . $e->getMessage() . ' %}');
        }
    }

    /**
     * Renders an 'include' node.
     *
     * Loads and renders another template at this position.
     *
     * @param TreeNode $node The include node to render.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered included template.
     */
    private function renderIncludeNode(TreeNode $node, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{% include !!invalid expression %}');
        }

        if ($this->templateLoader === null) {
            return $this->escape('{% include !!template loader not configured %}');
        }

        $templateName = trim($node->expression);
        // Remove quotes if present
        if ((str_starts_with($templateName, '"') && str_ends_with($templateName, '"')) ||
            (str_starts_with($templateName, "'") && str_ends_with($templateName, "'"))
        ) {
            $templateName = substr($templateName, 1, -1);
        }

        try {
            /** @var string|null $includedTemplate */
            $includedTemplate = ($this->templateLoader)($templateName);
            if ($includedTemplate === null) {
                return $this->escape('{% include "' . $templateName . '" !!template not found %}');
            }

            // Parse and render included template
            $tokens = $this->tokenize($includedTemplate);
            $tree = $this->createSyntaxTree($tokens);

            return $this->renderChildren($tree, $data, $functions);
        } catch (\Throwable $e) {
            return $this->escape('{% include "' . $templateName . '" !!' . $e->getMessage() . ' %}');
        }
    }

    /**
     * Renders children with block overrides (for template inheritance).
     *
     * @param TreeNode $node The parent node whose children should be rendered.
     * @param array<string,TreeNode> $blockOverrides Override blocks from child template.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output of all child nodes.
     */
    private function renderChildrenWithBlocks(TreeNode $node, array $blockOverrides, array $data, array $functions): string
    {
        $result = '';
        $ifNodes = [];
        foreach ($node->children as $child) {
            switch ($child->type) {
                case 'if':
                    $result .= $this->renderIfNode($child, $data, $functions);
                    $ifNodes = [$child];
                    break;
                case 'elseif':
                    $result .= $this->renderElseIfNode($child, $ifNodes, $data, $functions);
                    array_push($ifNodes, $child);
                    break;
                case 'else':
                    $result .= $this->renderElseNode($child, $ifNodes, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'for':
                    $result .= $this->renderForNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'block':
                    $result .= $this->renderBlockNode($child, $blockOverrides, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'include':
                    $result .= $this->renderIncludeNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'var':
                    $result .= $this->renderVarNode($child, $data, $functions);
                    $ifNodes = [];
                    break;
                case 'lit':
                    if (is_string($child->expression)) {
                        $result .= $child->expression;
                    }
                    $ifNodes = [];
                    break;
            }
        }
        return $result;
    }

    /**
     * Renders a variable interpolation node.
     *
     * Resolves the variable path, applies any filter functions, and escapes the result.
     * Variables can be simple ({{name}}) or use filters ({{name|upper}}).
     *
     * @param TreeNode $node The var node to render.
     * @param array<string,mixed> $data The data context for variable lookup.
     * @param array<string,callable> $functions Available custom functions for filters.
     * @return string The rendered and escaped variable value.
     */
    private function renderVarNode(TreeNode $node, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{{!!' . "invalid expression" . '}}');
        }

        $expressionStr = $node->expression;
        $parts = $this->explode('|', $expressionStr);
        $exprPart = array_shift($parts);
        if ($exprPart === null) {
            return $this->escape('{{' . $expressionStr . '!!' . "invalid expression" . '}}');
        }
        try {
            $expr = new Expression($exprPart);
            $value = $expr->evaluate($data, function (string $path, array $data) {
                /** @var array<string,mixed> $data */
                return $this->resolvePath($path, $data);
            });
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{' . $expressionStr . '!!' . $e->getMessage() . '}}');
        }
        if ($value instanceof RawValue) {
            return $this->escape($value);
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '';
        } elseif (!is_string($value) && !is_numeric($value)) {
            $value = '';
        }
        return $this->escape((string)$value);
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
    private function resolvePath(string $path, array $data): mixed
    {
        $current = $data;
        foreach ($this->explode('.', $path) as $p) {
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
    private function applyFunctions(mixed $value, array $parts, array $functions, array $data): mixed
    {
        foreach ($parts as $part) {
            $function = $this->explode('(', rtrim($part, ')'), 2);
            $f = $function[0];
            $arguments = isset($function[1]) ? $this->explode(',', $function[1]) : [];
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
                    $argument = $this->resolvePath($argument, $data);
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

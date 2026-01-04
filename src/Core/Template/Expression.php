<?php

namespace MintyPHP\Core\Template;

use MintyPHP\Error\TemplateError;

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
                        if (is_array($operand) && isset($operand['__test_identifier']) && is_string($operand['__test_identifier'])) {
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
                    /** @var array<string,string>|float|int|string $right */
                    $right = array_pop($stack);
                    /** @var array<string,string>|float|int|string $left */
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
                            ? ((is_scalar($left) ? (string)$left : '') . (is_scalar($right) ? (string)$right : ''))  // String concatenation
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
     * @param array<string,string>|float|int|string $testSpec The test specification (identifier like "even", "not.defined", or "divisibleby(3)")
     * @param array<string,mixed> $data Data context
     * @param callable $resolvePath Path resolver function
     * @return bool The test result
     */
    private function applyTest(mixed $value, array|float|int|string $testSpec, array $data, callable $resolvePath): bool
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
        } elseif (is_array($testSpec) && isset($testSpec['__test_identifier'])) {
            // Test identifier marker from expression evaluation
            $testName = $testSpec['__test_identifier'];
            if (str_starts_with($testName, 'not.')) {
                $isNegated = true;
                $testName = substr($testName, 4);
            }
        } else {
            // testSpec was resolved, shouldn't happen in normal cases
            $testName = is_scalar($testSpec) ? (string)$testSpec : '';
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

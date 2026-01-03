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
        '||' => ['precedence' => 1, 'associativity' => 'left'],
        '&&' => ['precedence' => 2, 'associativity' => 'left'],
        '==' => ['precedence' => 3, 'associativity' => 'left'],
        '!=' => ['precedence' => 3, 'associativity' => 'left'],
        '<' => ['precedence' => 4, 'associativity' => 'left'],
        '>' => ['precedence' => 4, 'associativity' => 'left'],
        '<=' => ['precedence' => 4, 'associativity' => 'left'],
        '>=' => ['precedence' => 4, 'associativity' => 'left'],
        '+' => ['precedence' => 5, 'associativity' => 'left'],
        '-' => ['precedence' => 5, 'associativity' => 'left'],
        '*' => ['precedence' => 6, 'associativity' => 'left'],
        '/' => ['precedence' => 6, 'associativity' => 'left'],
        '%' => ['precedence' => 6, 'associativity' => 'left'],
        '!' => ['precedence' => 7, 'associativity' => 'right'],
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
            if (ctype_alpha($char) || $char === '_') {
                $ident = '';
                while ($i < $len && (ctype_alnum($expression[$i]) || $expression[$i] === '_' || $expression[$i] === '.')) {
                    $ident .= $expression[$i];
                    $i++;
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

        foreach ($rpn as $token) {
            if ($token->isOperand()) {
                // Operand
                if ($token->type === 'number') {
                    $stack[] = strpos($token->value, '.') !== false ?
                        (float)$token->value : (int)$token->value;
                } elseif ($token->type === 'string') {
                    $stack[] = $token->value;
                } elseif ($token->type === 'identifier') {
                    $stack[] = $resolvePath($token->value, $data);
                }
            } elseif ($token->isOperator()) {
                // Operator
                $op = $token->value;
                if ($op === '!') {
                    // Unary operator
                    if (empty($stack)) {
                        throw new TemplateError("not enough operands for: $op");
                    }
                    $operand = array_pop($stack);
                    $stack[] = !$operand;
                } else {
                    // Binary operator
                    if (count($stack) < 2) {
                        throw new TemplateError("not enough operands for: $op");
                    }
                    /** @var float|int|string $right */
                    $right = array_pop($stack);
                    /** @var float|int|string $left */
                    $left = array_pop($stack);

                    // Check for logical, numeric or string operations and cast accordingly
                    $result = match ($op) {
                        // Logical operators - no casting needed, PHP handles truthiness
                        '||' => $left || $right,
                        '&&' => $left && $right,
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

    /**
     * Constructor
     * @param string $escape
     */
    public function __construct(private string $escape) {}

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
        // Add built-in 'raw' filter
        $functions['raw'] = (fn(string $value) => new RawValue($value));
        return $this->renderChildren($tree, $data, $functions);
    }

    /**
     * Tokenizes a template string by splitting it into literal text and expressions.
     *
     * Expressions are delimited by {{ and }}. The resulting array alternates between
     * literal text (even indices) and expressions (odd indices).
     *
     * @param string $template The template string to tokenize.
     * @return array<int,string> Array of tokens alternating between literals and expressions.
     */
    private function tokenize(string $template): array
    {
        $parts = ['', $template];
        $tokens = [];
        while (true) {
            $parts = explode('{{', $parts[1], 2);
            $tokens[] = $parts[0];
            if (count($parts) != 2) {
                break;
            }
            $parts = $this->explode('}}', $parts[1], 2);
            $tokens[] = $parts[0];
            if (count($parts) != 2) {
                break;
            }
        }
        return $tokens;
    }

    /**
     * Splits a string by a separator, respecting quoted strings and || operators.
     *
     * Similar to PHP's explode() but handles quoted strings properly, ensuring that
     * separators inside double-quoted strings are not treated as delimiters.
     * Also handles || operators when separator is |.
     *
     * @param string $separator The boundary string to split by.
     * @param string $str The input string to split.
     * @param int $count Maximum number of elements to return. -1 means no limit.
     * @return array<int,string> Array of string segments.
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
                    // Special handling for | separator: skip if it's part of ||
                    if ($separator === '|' && $i > 0 && $str[$i - 1] === '|') {
                        // Previous char was |, this is part of ||, include it
                        $token .= $c;
                        $i++;
                        continue;
                    }
                    if ($separator === '|' && $i < strlen($str) - 1 && $str[$i + 1] === '|') {
                        // Next char is |, this is part of ||, include both
                        $token .= '||';
                        $i++;
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
                if ($token == 'endif') {
                    $type = 'endif';
                    $expression = null;
                } elseif ($token == 'endfor') {
                    $type = 'endfor';
                    $expression = null;
                } elseif ($token == 'else') {
                    $type = 'else';
                    $expression = null;
                } elseif (str_starts_with($token, 'elseif:')) {
                    $type = 'elseif';
                    $expression = substr($token, 7);
                } elseif (str_starts_with($token, 'if:')) {
                    $type = 'if';
                    $expression = substr($token, 3);
                } elseif (str_starts_with($token, 'for:')) {
                    $type = 'for';
                    $expression = substr($token, 4);
                } else {
                    $type = 'var';
                    $expression = $token;
                }
                if (in_array($type, ['endif', 'endfor', 'elseif', 'else'])) {
                    if (count($stack)) {
                        $current = array_pop($stack);
                    }
                }
                if (in_array($type, ['var'])) {
                    $node = new TreeNode($type, $expression);
                    array_push($current->children, $node);
                }
                if (in_array($type, ['if', 'for', 'elseif', 'else'])) {
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
     * render method based on the child's type (if, for, var, lit, else, elseif).
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
            return $this->escape('{{if:!!' . "invalid expression" . '}}');
        }

        $expressionStr = $node->expression;
        $parts = $this->explode('|', $expressionStr);
        $exprPart = array_shift($parts);
        if ($exprPart === null) {
            return $this->escape('{{if:' . $expressionStr . '!!' . "invalid expression" . '}}');
        }

        try {
            $expr = new Expression($exprPart);
            $value = $expr->evaluate($data, function (string $path, array $data) {
                /** @var array<string,mixed> $data */
                return $this->resolvePath($path, $data);
            });
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{if:' . $expressionStr . '!!' . $e->getMessage() . '}}');
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
            return $this->escape("{{elseif!!could not find matching `if`}}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            if ($node->expression === null || !is_string($node->expression)) {
                return $this->escape('{{elseif:!!' . "invalid expression" . '}}');
            }

            $expressionStr = $node->expression;
            $parts = $this->explode('|', $expressionStr);
            $exprPart = array_shift($parts);
            if ($exprPart === null) {
                return $this->escape('{{elseif:' . $expressionStr . '!!' . "invalid expression" . '}}');
            }
            try {
                $expr = new Expression($exprPart);
                $value = $expr->evaluate($data, function (string $path, array $data) {
                    /** @var array<string,mixed> $data */
                    return $this->resolvePath($path, $data);
                });
                $value = $this->applyFunctions($value, $parts, $functions, $data);
            } catch (\Throwable $e) {
                return $this->escape('{{elseif:' . $expressionStr . '!!' . $e->getMessage() . '}}');
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
            return $this->escape("{{else!!could not find matching `if`}}");
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
     * - {{for:var:array}} - iterates with values only
     * - {{for:var:key:array}} - iterates with both keys and values
     *
     * @param TreeNode $node The for node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output for each iteration.
     */
    private function renderForNode(TreeNode $node, array $data, array $functions): string
    {
        if ($node->expression === null || !is_string($node->expression)) {
            return $this->escape('{{for:!!' . "invalid expression" . '}}');
        }

        $expressionStr = $node->expression;
        $parts = $this->explode('|', $expressionStr);
        $path = array_shift($parts);
        if ($path === null) {
            return $this->escape('{{for:' . $expressionStr . '!!' . "invalid expression" . '}}');
        }
        $path = $this->explode(':', $path, 3);
        if (count($path) == 2) {
            [$var, $path] = $path;
            $key = false;
        } elseif (count($path) == 3) {
            [$var, $key, $path] = $path;
        } else {
            return $this->escape('{{for:' . $expressionStr . '!!' . "for must have `for:var:array` format" . '}}');
        }
        try {
            $value = $this->resolvePath($path, $data);
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{for:' . $expressionStr . '!!' . $e->getMessage() . '}}');
        }
        if (!is_array($value)) {
            return $this->escape('{{for:' . $expressionStr . '!!' . "expression must evaluate to an array" . '}}');
        }
        $result = '';
        foreach ($value as $k => $v) {
            $data = array_merge($data, $key ? [$var => $v, $key => $k] : [$var => $v]);
            $result .= $this->renderChildren($node, $data, $functions);
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
        if (!is_string($value) && !is_numeric($value)) {
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
                if ($argument[0] == '"' && $argument[$len - 1] == '"') {
                    $argument = stripcslashes(substr($argument, 1, $len - 2));
                } else if (!is_numeric($argument)) {
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

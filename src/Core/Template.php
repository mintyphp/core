<?php

namespace MintyPHP\Core;

/**
 * Internal class to mark values that should not be escaped
 */
class RawValue
{
    public function __construct(public string $value) {}
}


class Template
{
    // Default static configuration
    public static string $__escape = 'html';

    // Configuration properties
    private string $escape;

    /**
     * Constructor
     * @param string $escape
     */
    public function __construct(string $escape)
    {
        $this->escape = $escape;
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
        switch ($this->escape) {
            case 'html':
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }
        return $string;
    }

    /**
     * Renders a template string with the provided data and custom functions.
     *
     * @param string $template The template string containing placeholders like {{variable}}.
     * @param array<string,mixed> $data Associative array of data to use in the template.
     * @param array<string,callable> $functions Associative array of custom functions available in the template.
     * @return string The rendered template string.
     */
    public function render(string $template, array $data, array $functions = []): string
    {
        $tokens = $this->tokenize($template);
        $tree = $this->createSyntaxTree($tokens);
        // Add built-in 'raw' filter
        $functions['raw'] = function ($value) {
            return new RawValue((string)$value);
        };
        return $this->renderChildren($tree, $data, $functions);
    }

    /**
     * Creates a syntax tree node.
     *
     * @param string $type The node type ('root', 'if', 'for', 'var', 'lit', 'else', 'elseif', etc.).
     * @param string|false $expression The expression associated with the node, or false if none.
     * @return \stdClass A node object with properties: type, expression, children, and value.
     */
    private function createNode(string $type, string|false $expression): \stdClass
    {
        $obj = new \stdClass();
        $obj->type = $type;
        $obj->expression = $expression;
        $obj->children = [];
        $obj->value = null;
        return $obj;
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
     * Splits a string by a separator, respecting quoted strings.
     *
     * Similar to PHP's explode() but handles quoted strings properly, ensuring that
     * separators inside double-quoted strings are not treated as delimiters.
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
     * @return \stdClass The root node of the syntax tree.
     */
    private function createSyntaxTree(array &$tokens): \stdClass
    {
        $root = $this->createNode('root', false);
        $current = $root;
        $stack = [];
        foreach ($tokens as $i => $token) {
            if ($i % 2 == 1) {
                if ($token == 'endif') {
                    $type = 'endif';
                    $expression = false;
                } elseif ($token == 'endfor') {
                    $type = 'endfor';
                    $expression = false;
                } elseif ($token == 'else') {
                    $type = 'else';
                    $expression = false;
                } elseif (substr($token, 0, 7) == 'elseif:') {
                    $type = 'elseif';
                    $expression = substr($token, 7);
                } elseif (substr($token, 0, 3) == 'if:') {
                    $type = 'if';
                    $expression = substr($token, 3);
                } elseif (substr($token, 0, 4) == 'for:') {
                    $type = 'for';
                    $expression = substr($token, 4);
                } else {
                    $type = 'var';
                    $expression = $token;
                }
                if (in_array($type, array('endif', 'endfor', 'elseif', 'else'))) {
                    if (count($stack)) {
                        $current = array_pop($stack);
                    }
                }
                if (in_array($type, array('var'))) {
                    $node = $this->createNode($type, $expression);
                    array_push($current->children, $node);
                }
                if (in_array($type, array('if', 'for', 'elseif', 'else'))) {
                    $node = $this->createNode($type, $expression);
                    array_push($current->children, $node);
                    array_push($stack, $current);
                    $current = $node;
                }
            } else {
                array_push($current->children, $this->createNode('lit', $token));
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
     * @param \stdClass $node The parent node whose children should be rendered.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output of all child nodes.
     */
    private function renderChildren(\stdClass $node, array $data, array $functions): string
    {
        $result = '';
        $ifNodes = [];
        foreach ($node->children as $child) {
            /** @var \stdClass $child */
            switch ($child->type) {
                case 'if':
                    $result .= $this->renderIfNode($child, $data, $functions);
                    $ifNodes = array($child);
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
                    $result .= $child->expression;
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
     * @param \stdClass $node The if node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if condition is true, empty string otherwise.
     */
    private function renderIfNode(\stdClass $node, array $data, array $functions): string
    {
        $parts = $this->explode('|', (string)$node->expression);
        $path = array_shift($parts);
        if ($path === null) {
            return $this->escape('{{if:' . $node->expression . '!!' . "invalid expression" . '}}');
        }
        try {
            $value = $this->resolvePath($path, $data);
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{if:' . $node->expression . '!!' . $e->getMessage() . '}}');
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
     * @param \stdClass $node The elseif node to render.
     * @param array<int,\stdClass> $ifNodes Array of preceding if/elseif nodes in the chain.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if condition is true and no previous conditions were true, empty string otherwise.
     */
    private function renderElseIfNode(\stdClass $node, array $ifNodes, array $data, array $functions): string
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
            $parts = $this->explode('|', (string)$node->expression);
            $path = array_shift($parts);
            if ($path === null) {
                return $this->escape('{{elseif:' . $node->expression . '!!' . "invalid expression" . '}}');
            }
            try {
                $value = $this->resolvePath($path, $data);
                $value = $this->applyFunctions($value, $parts, $functions, $data);
            } catch (\Throwable $e) {
                return $this->escape('{{elseif:' . $node->expression . '!!' . $e->getMessage() . '}}');
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
     * @param \stdClass $node The else node to render.
     * @param array<int,\stdClass> $ifNodes Array of preceding if/elseif nodes in the chain.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The rendered output if no previous conditions were true, empty string otherwise.
     */
    private function renderElseNode(\stdClass $node, array $ifNodes, array $data, array $functions): string
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
     * @param \stdClass $node The for node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @return string The concatenated rendered output for each iteration.
     */
    private function renderForNode(\stdClass $node, array $data, array $functions): string
    {
        $parts = $this->explode('|', (string)$node->expression);
        $path = array_shift($parts);
        if ($path === null) {
            return $this->escape('{{for:' . $node->expression . '!!' . "invalid expression" . '}}');
        }
        $path = $this->explode(':', $path, 3);
        if (count($path) == 2) {
            list($var, $path) = $path;
            $key = false;
        } elseif (count($path) == 3) {
            list($var, $key, $path) = $path;
        } else {
            return $this->escape('{{for:' . $node->expression . '!!' . "for must have `for:var:array` format" . '}}');
        }
        try {
            $value = $this->resolvePath($path, $data);
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{for:' . $node->expression . '!!' . $e->getMessage() . '}}');
        }
        if (!is_array($value)) {
            return $this->escape('{{for:' . $node->expression . '!!' . "expression must evaluate to an array" . '}}');
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
     * @param \stdClass $node The var node to render.
     * @param array<string,mixed> $data The data context for variable lookup.
     * @param array<string,callable> $functions Available custom functions for filters.
     * @return string The rendered and escaped variable value.
     */
    private function renderVarNode(\stdClass $node, array $data, array $functions): string
    {
        $parts = $this->explode('|', (string)$node->expression);
        $path = array_shift($parts);
        if ($path === null) {
            return $this->escape('{{' . $node->expression . '!!' . "invalid expression" . '}}');
        }
        try {
            $value = $this->resolvePath($path, $data);
            $value = $this->applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $this->escape('{{' . $node->expression . '!!' . $e->getMessage() . '}}');
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
     * @throws \Exception If any part of the path is not found.
     */
    private function resolvePath(string $path, array $data): mixed
    {
        $current = $data;
        foreach ($this->explode('.', $path) as $p) {
            if (!is_array($current) || !array_key_exists($p, $current)) {
                throw new \Exception("path `$p` not found");
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
     * @throws \Exception If a referenced function is not found.
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
                throw new \Exception("function `$f` not found");
            }
        }
        return $value;
    }
}

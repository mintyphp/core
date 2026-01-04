<?php

namespace MintyPHP\Core\Template;

/**
 * Core rendering logic for template nodes
 */
class Render
{
    /**
     * Renders an 'if' conditional node.
     *
     * Evaluates the condition expression and renders the node's children if truthy.
     * The evaluated value is stored in the node for use by subsequent elseif/else nodes.
     *
     * @param TreeNode $node The if node to render.
     * @param array<string,mixed> $data The data context for evaluation.
     * @param array<string,callable> $functions Available custom functions.
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The rendered output if condition is true, empty string otherwise.
     */
    public static function renderIfNode(
        TreeNode $node,
        array $data,
        array $functions,
        callable $renderChildren,
        callable $escape
    ): string {
        if ($node->expression === null || !is_string($node->expression)) {
            return $escape('{% if !!invalid expression %}');
        }

        $expressionStr = $node->expression;
        $parts = Helpers::explode('|', $expressionStr);
        $exprPart = array_shift($parts);
        if ($exprPart === null) {
            return $escape('{% if ' . $expressionStr . '!!invalid expression %}');
        }

        try {
            $expr = new Expression($exprPart);
            $value = $expr->evaluate($data, function (string $path, array $data) {
                /** @var array<string,mixed> $data */
                return Helpers::resolvePath($path, $data);
            });
            $value = Helpers::applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $escape('{% if ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
        }
        $result = '';
        if ($value) {
            $result .= $renderChildren($node, $data, $functions);
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
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The rendered output if condition is true and no previous conditions were true, empty string otherwise.
     */
    public static function renderElseIfNode(
        TreeNode $node,
        array $ifNodes,
        array $data,
        array $functions,
        callable $renderChildren,
        callable $escape
    ): string {
        if (count($ifNodes) < 1 || $ifNodes[0]->type != 'if') {
            return $escape("{% elseif !!could not find matching `if` %}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            if ($node->expression === null || !is_string($node->expression)) {
                return $escape('{% elseif !!invalid expression %}');
            }

            $expressionStr = $node->expression;
            $parts = Helpers::explode('|', $expressionStr);
            $exprPart = array_shift($parts);
            if ($exprPart === null) {
                return $escape('{% elseif ' . $expressionStr . '!!invalid expression %}');
            }
            try {
                $expr = new Expression($exprPart);
                $value = $expr->evaluate($data, function (string $path, array $data) {
                    /** @var array<string,mixed> $data */
                    return Helpers::resolvePath($path, $data);
                });
                $value = Helpers::applyFunctions($value, $parts, $functions, $data);
            } catch (\Throwable $e) {
                return $escape('{% elseif ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
            }
            if ($value) {
                $result .= $renderChildren($node, $data, $functions);
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
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The rendered output if no previous conditions were true, empty string otherwise.
     */
    public static function renderElseNode(
        TreeNode $node,
        array $ifNodes,
        array $data,
        array $functions,
        callable $renderChildren,
        callable $escape
    ): string {
        if (count($ifNodes) < 1 || $ifNodes[0]->type != 'if') {
            return $escape("{% else !!could not find matching `if` %}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            $result .= $renderChildren($node, $data, $functions);
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
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The concatenated rendered output for each iteration.
     */
    public static function renderForNode(
        TreeNode $node,
        array $data,
        array $functions,
        callable $renderChildren,
        callable $escape
    ): string {
        if ($node->expression === null || !is_string($node->expression)) {
            return $escape('{% for !!invalid expression %}');
        }

        $expressionStr = $node->expression;

        // Parse "for key, value in array" or "for value in array"
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*(?:\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*)?)\s+in\s+(.+)$/', $expressionStr, $matches)) {
            return $escape('{% for ' . $expressionStr . '!!invalid syntax, expected "item in array" or "key, value in array" %}');
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
        $parts = Helpers::explode('|', $arrayExpr);
        $path = array_shift($parts);
        if ($path === null) {
            return $escape('{% for ' . $expressionStr . '!!invalid expression %}');
        }

        try {
            $value = Helpers::resolvePath(trim($path), $data);
            $value = Helpers::applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $escape('{% for ' . $expressionStr . '!!' . $e->getMessage() . ' %}');
        }
        if (!is_array($value)) {
            return $escape('{% for ' . $expressionStr . '!!expression must evaluate to an array %}');
        }
        $result = '';
        foreach ($value as $k => $v) {
            $data = array_merge($data, $key ? [$key => $k, $var => $v] : [$var => $v]);
            $result .= $renderChildren($node, $data, $functions);
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
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The rendered and escaped variable value.
     */
    public static function renderVarNode(
        TreeNode $node,
        array $data,
        array $functions,
        callable $escape
    ): string {
        if ($node->expression === null || !is_string($node->expression)) {
            return $escape('{{!!' . "invalid expression" . '}}');
        }

        $expressionStr = $node->expression;
        $parts = Helpers::explode('|', $expressionStr);
        $exprPart = array_shift($parts);
        if ($exprPart === null) {
            return $escape('{{' . $expressionStr . '!!' . "invalid expression" . '}}');
        }
        try {
            $expr = new Expression($exprPart);
            $value = $expr->evaluate($data, function (string $path, array $data) {
                /** @var array<string,mixed> $data */
                return Helpers::resolvePath($path, $data);
            });
            $value = Helpers::applyFunctions($value, $parts, $functions, $data);
        } catch (\Throwable $e) {
            return $escape('{{' . $expressionStr . '!!' . $e->getMessage() . '}}');
        }
        if ($value instanceof RawValue) {
            return $escape($value);
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '';
        } elseif (!is_string($value) && !is_numeric($value)) {
            $value = '';
        }
        return $escape((string)$value);
    }
}

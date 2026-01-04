<?php

namespace MintyPHP\Core;

use MintyPHP\Core\Template\RawValue;
use MintyPHP\Core\Template\TreeNode;
use MintyPHP\Core\Template\Filters;
use MintyPHP\Core\Template\Render;
use MintyPHP\Core\Template\Blocks;

/**
 * Template engine for MintyPHP
 * 
 * Provides functionality to render templates with variable interpolation,
 * control structures (if/for), and custom filters. Supports HTML escaping
 * and raw output.
 */
class Template
{
    /** @var callable|null */
    private $templateLoader = null;

    /**
     * Constructor
     * @param callable|null $templateLoader Function to load templates by name for extends/include
     */
    public function __construct(?callable $templateLoader = null)
    {
        $this->templateLoader = $templateLoader;
    }

    /**
     * Escapes a string for HTML output.
     *
     * @param string|RawValue $string The string to escape.
     * @return string The escaped string.
     */
    private function escape(string|RawValue $string): string
    {
        if ($string instanceof RawValue) {
            return $string->value;
        }
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renders a template string with the provided data and custom filters.
     *
     * @param string $template The template string containing placeholders like {{variable}}.
     * @param array<string,mixed> $data Associative array of data to use in the template.
     * @param array<string,callable> $filters Associative array of custom filters available in the template.
     * @return string The rendered template string.
     * @throws \RuntimeException If there is an error during rendering.
     */
    public function render(string $template, array $data, array $filters = []): string
    {
        $tokens = $this->tokenize($template);
        $tree = $this->createSyntaxTree($tokens);
        // Add built-in filters
        $filters = array_merge(Filters::getBuiltinFilters(), $filters);
        return $this->renderChildren($tree, $data, $filters);
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
     * @param array<string,callable> $filters Available custom filters.
     * @return string The concatenated rendered output of all child nodes.
     */
    private function renderChildren(TreeNode $node, array $data, array $filters): string
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

            return Blocks::renderExtendsNode(
                $extendsNode,
                $blocks,
                $data,
                $filters,
                $this->tokenize(...),
                $this->createSyntaxTree(...),
                $this->renderChildrenWithBlocks(...),
                $this->escape(...),
                $this->templateLoader
            );
        }

        // Normal rendering without extends
        foreach ($node->children as $child) {
            switch ($child->type) {
                case 'if':
                    $result .= Render::renderIfNode(
                        $child,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    $ifNodes = [$child];
                    break;
                case 'elseif':
                    $result .= Render::renderElseIfNode(
                        $child,
                        $ifNodes,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    array_push($ifNodes, $child);
                    break;
                case 'else':
                    $result .= Render::renderElseNode(
                        $child,
                        $ifNodes,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );

                    break;
                case 'for':
                    $result .= Render::renderForNode(
                        $child,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    break;
                case 'block':
                    $result .= Blocks::renderBlockNode(
                        $child,
                        [],
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->renderChildrenWithBlocks(...),
                        $this->escape(...)
                    );
                    break;
                case 'include':
                    $result .= Blocks::renderIncludeNode(
                        $child,
                        $data,
                        $filters,
                        $this->tokenize(...),
                        $this->createSyntaxTree(...),
                        $this->renderChildren(...),
                        $this->escape(...),
                        $this->templateLoader
                    );
                    break;
                case 'var':
                    $result .= Render::renderVarNode(
                        $child,
                        $data,
                        $filters,
                        $this->escape(...)
                    );
                    break;
                case 'lit':
                    if (is_string($child->expression)) {
                        $result .= $child->expression;
                    }
                    break;
            }
        }
        return $result;
    }

    /**
     * Renders children with block overrides (for template inheritance).
     *
     * @param TreeNode $node The parent node whose children should be rendered.
     * @param array<string,TreeNode> $blockOverrides Override blocks from child template.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $filters Available custom filters.
     * @return string The concatenated rendered output of all child nodes.
     */
    private function renderChildrenWithBlocks(TreeNode $node, array $blockOverrides, array $data, array $filters): string
    {
        $result = '';
        $ifNodes = [];
        foreach ($node->children as $child) {
            switch ($child->type) {
                case 'if':
                    $result .= Render::renderIfNode(
                        $child,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    $ifNodes = [$child];
                    break;
                case 'elseif':
                    $result .= Render::renderElseIfNode(
                        $child,
                        $ifNodes,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    array_push($ifNodes, $child);
                    break;
                case 'else':
                    $result .= Render::renderElseNode(
                        $child,
                        $ifNodes,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    $ifNodes = [];
                    break;
                case 'for':
                    $result .= Render::renderForNode(
                        $child,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->escape(...)
                    );
                    break;
                case 'block':
                    $result .= Blocks::renderBlockNode(
                        $child,
                        $blockOverrides,
                        $data,
                        $filters,
                        $this->renderChildren(...),
                        $this->renderChildrenWithBlocks(...),
                        $this->escape(...)
                    );
                    break;
                case 'include':
                    $result .= Blocks::renderIncludeNode(
                        $child,
                        $data,
                        $filters,
                        $this->tokenize(...),
                        $this->createSyntaxTree(...),
                        $this->renderChildren(...),
                        $this->escape(...),
                        $this->templateLoader
                    );
                    break;
                case 'var':
                    $result .= Render::renderVarNode(
                        $child,
                        $data,
                        $filters,
                        $this->escape(...)
                    );
                    break;
                case 'lit':
                    if (is_string($child->expression)) {
                        $result .= $child->expression;
                    }
                    break;
            }
        }
        return $result;
    }
}

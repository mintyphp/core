<?php

namespace MintyPHP\Core\Template;

/**
 * Block inheritance and template composition logic
 */
class Blocks
{
    /**
     * Renders an 'extends' node.
     *
     * Loads the parent template and renders it with block overrides from the child.
     *
     * @param TreeNode|null $node The extends node to render.
     * @param array<string,TreeNode> $blocks Blocks defined in the child template.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @param callable(string): array<int,string> $tokenize Function to tokenize templates.
     * @param callable(array<int,string>): TreeNode $createSyntaxTree Function to create syntax trees.
     * @param callable(TreeNode, array<string,TreeNode>, array<string,mixed>, array<string,callable>): string $renderChildrenWithBlocks Function to render with blocks.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @param (callable(string): (string|null))|null $templateLoader Template loader function.
     * @return string The rendered parent template with block overrides.
     */
    public static function renderExtendsNode(
        ?TreeNode $node,
        array $blocks,
        array $data,
        array $functions,
        callable $tokenize,
        callable $createSyntaxTree,
        callable $renderChildrenWithBlocks,
        callable $escape,
        ?callable $templateLoader
    ): string {
        if ($node === null || $node->expression === null || !is_string($node->expression)) {
            return $escape('{% extends !!invalid expression %}');
        }

        if ($templateLoader === null) {
            return $escape('{% extends !!template loader not configured %}');
        }

        $templateName = trim($node->expression);
        // Remove quotes if present
        if ((str_starts_with($templateName, '"') && str_ends_with($templateName, '"')) ||
            (str_starts_with($templateName, "'") && str_ends_with($templateName, "'"))
        ) {
            $templateName = substr($templateName, 1, -1);
        }

        try {
            $parentTemplate = $templateLoader($templateName);
            if ($parentTemplate === null) {
                return $escape('{% extends "' . $templateName . '" !!template not found %}');
            }

            // Parse parent template
            $tokens = $tokenize($parentTemplate);
            $tree = $createSyntaxTree($tokens);

            // Render parent with block overrides
            return $renderChildrenWithBlocks($tree, $blocks, $data, $functions);
        } catch (\Throwable $e) {
            return $escape('{% extends "' . $templateName . '" !!' . $e->getMessage() . ' %}');
        }
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
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(TreeNode, array<string,TreeNode>, array<string,mixed>, array<string,callable>): string $renderChildrenWithBlocks Function to render with blocks.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @return string The rendered block content.
     */
    public static function renderBlockNode(
        TreeNode $node,
        array $blockOverrides,
        array $data,
        array $functions,
        callable $renderChildren,
        callable $renderChildrenWithBlocks,
        callable $escape
    ): string {
        if ($node->expression === null || !is_string($node->expression)) {
            return $escape('{% block !!invalid expression %}');
        }

        $blockName = trim($node->expression);

        // If we have an override for this block, use it
        if (isset($blockOverrides[$blockName])) {
            return $renderChildren($blockOverrides[$blockName], $data, $functions);
        }

        // Otherwise render the default content, but pass blockOverrides for nested blocks
        return $renderChildrenWithBlocks($node, $blockOverrides, $data, $functions);
    }

    /**
     * Renders an 'include' node.
     *
     * Loads and renders another template at this position.
     *
     * @param TreeNode $node The include node to render.
     * @param array<string,mixed> $data The data context for rendering.
     * @param array<string,callable> $functions Available custom functions.
     * @param callable(string): array<int,string> $tokenize Function to tokenize templates.
     * @param callable(array<int,string>): TreeNode $createSyntaxTree Function to create syntax trees.
     * @param callable(TreeNode, array<string,mixed>, array<string,callable>): string $renderChildren Function to render children nodes.
     * @param callable(string|RawValue): string $escape Function to escape values.
     * @param (callable(string): (string|null))|null $templateLoader Template loader function.
     * @return string The rendered included template.
     */
    public static function renderIncludeNode(
        TreeNode $node,
        array $data,
        array $functions,
        callable $tokenize,
        callable $createSyntaxTree,
        callable $renderChildren,
        callable $escape,
        ?callable $templateLoader
    ): string {
        if ($node->expression === null || !is_string($node->expression)) {
            return $escape('{% include !!invalid expression %}');
        }

        if ($templateLoader === null) {
            return $escape('{% include !!template loader not configured %}');
        }

        $templateName = trim($node->expression);
        // Remove quotes if present
        if ((str_starts_with($templateName, '"') && str_ends_with($templateName, '"')) ||
            (str_starts_with($templateName, "'") && str_ends_with($templateName, "'"))
        ) {
            $templateName = substr($templateName, 1, -1);
        }

        try {
            $includedTemplate = $templateLoader($templateName);
            if ($includedTemplate === null) {
                return $escape('{% include "' . $templateName . '" !!template not found %}');
            }

            // Parse and render included template
            $tokens = $tokenize($includedTemplate);
            $tree = $createSyntaxTree($tokens);

            return $renderChildren($tree, $data, $functions);
        } catch (\Throwable $e) {
            return $escape('{% include "' . $templateName . '" !!' . $e->getMessage() . ' %}');
        }
    }
}

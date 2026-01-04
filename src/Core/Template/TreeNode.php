<?php

namespace MintyPHP\Core\Template;

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

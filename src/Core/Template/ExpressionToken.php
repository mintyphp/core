<?php

namespace MintyPHP\Core\Template;

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

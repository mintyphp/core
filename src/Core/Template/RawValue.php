<?php

namespace MintyPHP\Core\Template;

/**
 * Internal class to mark values that should not be escaped
 */
class RawValue
{
    public function __construct(public string $value) {}
}

<?php
namespace MintyPHP;

class Template
{
    public static $escape = 'html';

    public static function escape($string)
    {
        switch (static::$escape) {
            case 'html':
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }
        return $string;
    }

    public static function render($template, $data, $functions = array())
    {
        $tokens = Template::tokenize($template);
        $tree = Template::createSyntaxTree($tokens);
        return Template::renderChildren($tree, $data, $functions);
    }

    private static function createNode($type, $expression)
    {
        return (object) array('type' => $type, 'expression' => $expression, 'children' => array(), 'value' => null);
    }

    private static function tokenize($template)
    {
        $parts = ['', $template];
        $tokens = [];
        while (true) {
            $parts = explode('{{', $parts[1], 2);
            $tokens[] = $parts[0];
            if (count($parts) != 2) {
                break;
            }
            $parts = explode('}}', $parts[1], 2);
            $tokens[] = $parts[0];
            if (count($parts) != 2) {
                break;
            }
        }
        return $tokens;
    }

    private static function split(string $a, string $separator, string $escape)
    {
        $result = array();
        $token = '';
        $escaped = false;
        for ($i = 0; $i < strlen($a); $i++) {
            $c = $a[$i];
            if (!$escaped) {
                if ($c == $escape) {
                    $escaped = true;
                } elseif ($c == $separator) {
                    $result[] = $token;
                    $token = '';
                } else { // other char
                    $token .= $c;
                }
            } else { // $escaped
                $token .= $c;
                $escaped = false;
            }
        }
        $result[] = $token;
        return $result;
    }

    private static function createSyntaxTree(&$tokens)
    {
        $root = Template::createNode('root', false);
        $current = $root;
        $stack = array();
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
                if (in_array($type, array('if', 'for', 'var', 'elseif', 'else'))) {
                    $node = Template::createNode($type, $expression);
                    array_push($current->children, $node);
                }
                if (in_array($type, array('if', 'for', 'elseif', 'else'))) {
                    array_push($stack, $current);
                    $current = $node;
                }
            } else {
                array_push($current->children, Template::createNode('lit', $token));
            }
        }
        return $root;
    }

    private static function renderChildren($node, $data, $functions)
    {
        $result = '';
        $ifNodes = array();
        foreach ($node->children as $child) {
            switch ($child->type) {
                case 'if':
                    $result .= Template::renderIfNode($child, $data, $functions);
                    $ifNodes = array($child);
                    break;
                case 'elseif':
                    $result .= Template::renderElseIfNode($child, $ifNodes, $data, $functions);
                    if (count($ifNodes) > 0) {
                        array_push($ifNodes, $child);
                    }
                    break;
                case 'else':
                    $result .= Template::renderElseNode($child, $ifNodes, $data, $functions);
                    $ifNodes = array();
                    break;
                case 'for':
                    $result .= Template::renderForNode($child, $data, $functions);
                    $ifNodes = array();
                    break;
                case 'var':
                    $result .= Template::renderVarNode($child, $data, $functions);
                    $ifNodes = array();
                    break;
                case 'lit':
                    $result .= $child->expression;
                    $ifNodes = array();
                    break;
            }
        }
        return $result;
    }

    private static function renderIfNode($node, $data, $functions)
    {
        $parts = explode('|', $node->expression);
        $path = array_shift($parts);
        try {
            $value = Template::resolvePath($path, $data);
            $value = Template::applyFunctions($value, $parts, $functions);
        } catch (\Throwable $e) {
            return Template::escape('{{if:' . $node->expression . '!!' . $e->getMessage() . '}}');
        }
        $result = '';
        if ($value) {
            $result .= Template::renderChildren($node, $data, $functions);
        }
        $node->value = $value;
        return $result;
    }

    private static function renderElseIfNode($node, $ifNodes, $data, $functions)
    {
        if (count($ifNodes) == 0) {
            return Template::escape("{{elseif!!could not find matching `if`}}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            $parts = explode('|', $node->expression);
            $path = array_shift($parts);
            try {
                $value = Template::resolvePath($path, $data);
                $value = Template::applyFunctions($value, $parts, $functions);
            } catch (\Throwable $e) {
                return Template::escape('{{elseif:' . $node->expression . '!!' . $e->getMessage() . '}}');
            }
            if ($value) {
                $result .= Template::renderChildren($node, $data, $functions);
            }
        }
        $node->value = $value;
        return $result;
    }

    private static function renderElseNode($node, $ifNodes, $data, $functions)
    {
        if (count($ifNodes) == 0) {
            return Template::escape("{{else!!could not find matching `if`}}");
        }
        $result = '';
        $value = false;
        for ($i = 0; $i < count($ifNodes); $i++) {
            $value = $value || $ifNodes[$i]->value;
        }
        if (!$value) {
            $result .= Template::renderChildren($node, $data, $functions);
        }
        return $result;
    }

    private static function renderForNode($node, $data, $functions)
    {
        $parts = explode('|', $node->expression);
        $path = array_shift($parts);
        $path = explode(':', $path, 3);
        if (count($path) == 2) {
            list($var, $path) = $path;
            $key = false;
        } elseif (count($path) == 3) {
            list($var, $key, $path) = $path;
        } else {
            return Template::escape('{{for:' . $node->expression . '!!' . "for must have `for:var:array` format" . '}}');
        }
        try {
            $value = Template::resolvePath($path, $data);
            $value = Template::applyFunctions($value, $parts, $functions);
        } catch (\Throwable $e) {
            return Template::escape('{{for:' . $node->expression . '!!' . $e->getMessage() . '}}');
        }
        if (!is_array($value)) {
            return Template::escape('{{for:' . $node->expression . '!!' . "expression must evaluate to an array" . '}}');
        }
        $result = '';
        foreach ($value as $k => $v) {
            $data = array_merge($data, $key ? [$var => $v, $key => $k] : [$var => $v]);
            $result .= Template::renderChildren($node, $data, $functions);
        }
        return $result;
    }

    private static function renderVarNode($node, $data, $functions)
    {
        $parts = explode('|', $node->expression);
        $path = array_shift($parts);
        try {
            $value = Template::resolvePath($path, $data);
            $value = Template::applyFunctions($value, $parts, $functions);
        } catch (\Throwable $e) {
            return Template::escape('{{' . $node->expression . '!!' . $e->getMessage() . '}}');
        }
        return Template::escape($value);
    }

    private static function resolvePath($path, $data)
    {
        $current = $data;
        foreach (explode('.', $path) as $p) {
            if (!array_key_exists($p, $current)) {
                throw new \Exception("path `$p` not found");
            }
            $current = &$current[$p];
        }
        return $current;
    }

    private static function applyFunctions($value, $parts, $functions)
    {
        foreach ($parts as $part) {
            $function = explode('(', rtrim($part, ')'));
            $f = $function[0];
            $arguments = isset($function[1]) ? Template::split($function[1], ',', '\\') : array();
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

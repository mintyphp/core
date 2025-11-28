<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    private static Template $template;

    public static function setUpBeforeClass(): void
    {
        self::$template = new Template('html');
    }

    public function testRenderWithCustomFunction(): void
    {
        $result = self::$template->render('hello {{name|capitalize}}', ['name' => 'world'], ['capitalize' => 'ucfirst']);
        $this->assertEquals("hello World", $result);
    }

    public function testRenderWithHtmlEscaping(): void
    {
        $this->assertEquals("<br>hello &lt;br&gt;world", self::$template->render('<br>hello {{name}}', ['name' => '<br>world']));
    }

    public function testRenderWithMissingFunction(): void
    {
        $this->assertEquals("hello {{name|failure!!function `failure` not found}}", self::$template->render('hello {{name|failure}}', ['name' => 'world'], ['capitalize' => 'ucfirst']));
    }

    public function testRenderIfWithNestedPath(): void
    {
        $this->assertEquals("hello m is 3", self::$template->render('hello {{if:n.m|eq(3)}}m is 3{{endif}}', ['n' => ['m' => 3]], ['eq' => function ($a, $b) {
            return $a == $b;
        }]));
    }

    public function testRenderIfElse(): void
    {
        $this->assertEquals("hello not n", self::$template->render('hello {{if:n}}n{{else}}not n{{endif}}', ['n' => false]));
    }

    public function testRenderWithFunctionLiteralArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{name|dateFormat("Y-m-d")}}', ['name' => 'May 13, 1980'], ['dateFormat' => function ($date, $format) {
            return date($format, strtotime($date));
        }]));
    }

    public function testRenderWithFunctionDataArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{name|dateFormat(format)}}', ['name' => 'May 13, 1980', 'format' => 'Y-m-d'], ['dateFormat' => function ($date, $format) {
            return date($format, strtotime($date));
        }]));
    }

    public function testRenderWithFunctionComplexLiteralArgument(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{name|dateFormat("M j, Y")}}', ['name' => 'May 13, 1980'], ['dateFormat' => function ($date, $format) {
            return date($format, strtotime($date));
        }]));
    }

    public function testRenderWithFunctionArgumentWithWhitespace(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{name|dateFormat( "M j, Y")}}', ['name' => 'May 13, 1980'], ['dateFormat' => function ($date, $format) {
            return date($format, strtotime($date));
        }]));
    }

    public function testRenderWithEscapedSpecialCharacters(): void
    {
        $this->assertEquals("hello \" May ()}}&quot;,|:.13, 1980\"", self::$template->render('hello "{{name|dateFormat(" M ()}}\\",|:.j, Y")}}"', ['name' => 'May 13, 1980'], ['dateFormat' => function ($date, $format) {
            return date($format, strtotime($date));
        }]));
    }

    public function testRenderForLoopWithValues(): void
    {
        $this->assertEquals("test 1 2 3", self::$template->render('test{{for:i:counts}} {{i}}{{endfor}}', ['counts' => [1, 2, 3]]));
    }

    public function testRenderForLoopWithKeysAndValues(): void
    {
        $this->assertEquals("test a=1 b=2 c=3", self::$template->render('test{{for:v:k:counts}} {{k}}={{v}}{{endfor}}', ['counts' => ['a' => 1, 'b' => 2, 'c' => 3]]));
    }

    public function testRenderNestedForLoops(): void
    {
        $this->assertEquals("test (-1,-1) (-1,1) (1,-1) (1,1)", self::$template->render('test{{for:x:steps}}{{for:y:steps}} ({{x}},{{y}}){{endfor}}{{endfor}}', ['steps' => [-1, 1]]));
    }

    public function testRenderForLoopWithIfElseIf(): void
    {
        $this->assertEquals("hello one two three", self::$template->render('hello{{for:i:counts}} {{if:i|eq(1)}}one{{elseif:i|eq(2)}}two{{else}}three{{endif}}{{endfor}}', ['counts' => [1, 2, 3]], ['eq' => function ($a, $b) {
            return $a == $b;
        }]));
    }

    public function testEscape(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', self::$template->render('{{a}}', ['a' => '<script>alert("xss")</script>'], []));
    }

    public function testRawEscape(): void
    {
        $this->assertEquals('<script>alert("xss")</script>', self::$template->render('{{a|raw}}', ['a' => '<script>alert("xss")</script>'], []));
    }

    public function testNoEscape(): void
    {
        self::$template = new Template('none');
        $this->assertEquals('<script>alert("xss")</script>', self::$template->render('{{a|raw}}', ['a' => '<script>alert("xss")</script>'], []));
    }
}

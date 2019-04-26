<?php
namespace MintyPHP\Tests;

use MintyPHP\Template;

class TemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testRender()
    {
        $this->assertEquals("hello World", Template::render('hello {{name|capitalize}}', ['name' => 'world'], ['capitalize' => 'ucfirst']));
        $this->assertEquals("hello {{name|failure!!function `failure` not found}}", Template::render('hello {{name|failure}}', ['name' => 'world'], ['capitalize' => 'ucfirst']));
        $this->assertEquals("hello m is 3", Template::render('hello {{if:n.m|eq(3)}}m is 3{{endif}}', ['n' => ['m' => 3]], ['eq' => function ($a, $b) {return $a == $b;}]));
        $this->assertEquals("hello not n", Template::render('hello {{if:n}}n{{else}}not n{{endif}}', ['n' => false]));
        $this->assertEquals("hello 1980-05-13", Template::render('hello {{name|dateFormat(Y-m-d)}}', ['name' => 'May 13, 1980'], ['dateFormat' => function ($date, $format) {return date($format, strtotime($date));}]));
        $this->assertEquals("test 1 2 3", Template::render('test{{for:i:counts}} {{i}}{{endfor}}', ['counts' => [1, 2, 3]]));
        $this->assertEquals("test a=1 b=2 c=3", Template::render('test{{for:v:k:counts}} {{k}}={{v}}{{endfor}}', ['counts' => ['a' => 1, 'b' => 2, 'c' => 3]]));
        $this->assertEquals("test (-1,-1) (-1,1) (1,-1) (1,1)", Template::render('test{{for:x:steps}}{{for:y:steps}} ({{x}},{{y}}){{endfor}}{{endfor}}', ['steps' => [-1, 1]]));
        $this->assertEquals("hello one two three", Template::render('hello{{for:i:counts}} {{if:i|eq(1)}}one{{elseif:i|eq(2)}}two{{else}}three{{endif}}{{endfor}}', ['counts' => [1, 2, 3]], ['eq' => function ($a, $b) {return $a == $b;}]));
    }
}

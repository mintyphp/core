<?php
namespace MintyPHP\Tests;

use MintyPHP\Template;

class TemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testRender() {
        $this->assertEquals("hello World",Template::render('hello {{name|capitalize}}', ['name'=>'world'], ['capitalize'=>'ucfirst']));
        $this->assertEquals("hello {{name|failure!!function 'failure' not found}}",Template::render('hello {{name|failure}}', ['name'=>'world'], ['capitalize'=>'ucfirst']));
        $this->assertEquals("hello m is 3",Template::render('hello {{if:n.m|eq(3)}}m is 3{{endif}}', ['n'=>['m'=>3]], ['eq'=>function($a,$b){return $a==$b;}]));
        $this->assertEquals("hello 1980-05-13",Template::render('hello {{name|dateFormat(Y-m-d)}}', ['name'=>'May 13, 1980'], ['dateFormat'=>function($date, $format) { return date($format, strtotime($date)); }]));
        $this->assertEquals("test 1 2 3",Template::render('test{{for:i:counts}} {{i}}{{endfor}}', ['counts'=>[1,2,3]]));
        $this->assertEquals("test (-1,-1) (-1,1) (1,-1) (1,1)",Template::render('test{{for:x:steps}}{{for:y:steps}} ({{x}},{{y}}){{endfor}}{{endfor}}', ['steps'=>[-1,1]]));
    }
}

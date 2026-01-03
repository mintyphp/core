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
        $this->assertEquals("hello m is 3", self::$template->render('hello {{if:n.m|eq(3)}}m is 3{{endif}}', ['n' => ['m' => 3]], ['eq' => fn($a, $b) => $a == $b]));
    }

    public function testRenderIfElse(): void
    {
        $this->assertEquals("hello not n", self::$template->render('hello {{if:n}}n{{else}}not n{{endif}}', ['n' => false]));
    }

    public function testRenderWithFunctionLiteralArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{name|dateFormat("Y-m-d")}}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionDataArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{name|dateFormat(format)}}', ['name' => 'May 13, 1980', 'format' => 'Y-m-d'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionComplexLiteralArgument(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{name|dateFormat("M j, Y")}}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionArgumentWithWhitespace(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{name|dateFormat( "M j, Y")}}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithEscapedSpecialCharacters(): void
    {
        $this->assertEquals("hello \" May ()}}&quot;,|:.13, 1980\"", self::$template->render('hello "{{name|dateFormat(" M ()}}\\",|:.j, Y")}}"', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
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
        $this->assertEquals("hello one two three", self::$template->render('hello{{for:i:counts}} {{if:i|eq(1)}}one{{elseif:i|eq(2)}}two{{else}}three{{endif}}{{endfor}}', ['counts' => [1, 2, 3]], ['eq' => fn($a, $b) => $a == $b]));
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
        $template = new Template('none');
        $this->assertEquals('<script>alert("xss")</script>', $template->render('{{a}}', ['a' => '<script>alert("xss")</script>'], []));
    }

    // Expression tests - Basic comparison operators
    public function testExpressionEquals(): void
    {
        $this->assertEquals("equal", self::$template->render('{{if:a == 5}}equal{{endif}}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{{if:a == 5}}equal{{endif}}', ['a' => 3]));
    }

    public function testExpressionNotEquals(): void
    {
        $this->assertEquals("not equal", self::$template->render('{{if:a != 5}}not equal{{endif}}', ['a' => 3]));
        $this->assertEquals("", self::$template->render('{{if:a != 5}}not equal{{endif}}', ['a' => 5]));
    }

    public function testExpressionLessThan(): void
    {
        $this->assertEquals("less", self::$template->render('{{if:a < 10}}less{{endif}}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{{if:a < 10}}less{{endif}}', ['a' => 15]));
    }

    public function testExpressionGreaterThan(): void
    {
        $this->assertEquals("greater", self::$template->render('{{if:a > 10}}greater{{endif}}', ['a' => 15]));
        $this->assertEquals("", self::$template->render('{{if:a > 10}}greater{{endif}}', ['a' => 5]));
    }

    public function testExpressionLessThanOrEqual(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a <= 10}}yes{{endif}}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{{if:a <= 10}}yes{{endif}}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{{if:a <= 10}}yes{{endif}}', ['a' => 15]));
    }

    public function testExpressionGreaterThanOrEqual(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a >= 10}}yes{{endif}}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{{if:a >= 10}}yes{{endif}}', ['a' => 15]));
        $this->assertEquals("", self::$template->render('{{if:a >= 10}}yes{{endif}}', ['a' => 5]));
    }

    // Expression tests - Logical operators
    public function testExpressionLogicalAnd(): void
    {
        $this->assertEquals("both true", self::$template->render('{{if:a > 5 && b < 20}}both true{{endif}}', ['a' => 10, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{{if:a > 5 && b < 20}}both true{{endif}}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{{if:a > 5 && b < 20}}both true{{endif}}', ['a' => 10, 'b' => 25]));
    }

    public function testExpressionLogicalOr(): void
    {
        $this->assertEquals("at least one", self::$template->render('{{if:a > 5 || b < 20}}at least one{{endif}}', ['a' => 10, 'b' => 25]));
        $this->assertEquals("at least one", self::$template->render('{{if:a > 5 || b < 20}}at least one{{endif}}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{{if:a > 5 || b < 20}}at least one{{endif}}', ['a' => 3, 'b' => 25]));
    }

    public function testExpressionLogicalNot(): void
    {
        $this->assertEquals("not true", self::$template->render('{{if:!a}}not true{{endif}}', ['a' => false]));
        $this->assertEquals("", self::$template->render('{{if:!a}}not true{{endif}}', ['a' => true]));
    }

    // Expression tests - Arithmetic operators
    public function testExpressionAddition(): void
    {
        $this->assertEquals("15", self::$template->render('{{a + b}}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionSubtraction(): void
    {
        $this->assertEquals("5", self::$template->render('{{a - b}}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionMultiplication(): void
    {
        $this->assertEquals("50", self::$template->render('{{a * b}}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionDivision(): void
    {
        $this->assertEquals("2", self::$template->render('{{a / b}}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionModulo(): void
    {
        $this->assertEquals("1", self::$template->render('{{a % b}}', ['a' => 10, 'b' => 3]));
    }

    // Expression tests - Operator precedence
    public function testExpressionPrecedenceArithmetic(): void
    {
        $this->assertEquals("14", self::$template->render('{{a + b * c}}', ['a' => 2, 'b' => 3, 'c' => 4]));
    }

    public function testExpressionPrecedenceComparison(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a + b > c}}yes{{endif}}', ['a' => 5, 'b' => 10, 'c' => 12]));
    }

    public function testExpressionPrecedenceLogical(): void
    {
        // && has higher precedence than ||
        $this->assertEquals("yes", self::$template->render('{{if:a == 1 || b == 2 && c == 3}}yes{{endif}}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("", self::$template->render('{{if:a == 1 || b == 2 && c == 3}}yes{{endif}}', ['a' => 5, 'b' => 2, 'c' => 5]));
    }

    // Expression tests - Nested expressions with parentheses
    public function testExpressionParenthesesArithmetic(): void
    {
        $this->assertEquals("18", self::$template->render('{{(a + b) * c}}', ['a' => 2, 'b' => 4, 'c' => 3]));
    }

    public function testExpressionParenthesesLogical(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:(a == 1 || b == 2) && c == 3}}yes{{endif}}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("", self::$template->render('{{if:(a == 1 || b == 2) && c == 3}}yes{{endif}}', ['a' => 5, 'b' => 5, 'c' => 3]));
    }

    public function testExpressionNestedParentheses(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:((a + b) * c) > 20}}yes{{endif}}', ['a' => 5, 'b' => 5, 'c' => 3]));
    }

    // Expression tests - Complex combined conditions
    public function testExpressionComplexCondition1(): void
    {
        $this->assertEquals("match", self::$template->render('{{if:a > 5 && b < 20 || c == 10}}match{{endif}}', ['a' => 10, 'b' => 15, 'c' => 0]));
    }

    public function testExpressionComplexCondition2(): void
    {
        $this->assertEquals("match", self::$template->render('{{if:(a > 5 || b > 5) && (c < 20 || d < 20)}}match{{endif}}', ['a' => 3, 'b' => 10, 'c' => 25, 'd' => 15]));
    }

    public function testExpressionInElseIf(): void
    {
        $this->assertEquals("second", self::$template->render('{{if:a > 10}}first{{elseif:a > 5}}second{{else}}third{{endif}}', ['a' => 7]));
    }

    public function testExpressionWithStringLiterals(): void
    {
        $this->assertEquals("match", self::$template->render('{{if:name == "John"}}match{{endif}}', ['name' => 'John']));
        $this->assertEquals("", self::$template->render('{{if:name == "John"}}match{{endif}}', ['name' => 'Jane']));
    }

    public function testExpressionWithNumericLiterals(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a + 5 > 10}}yes{{endif}}', ['a' => 8]));
    }

    // Expression tests - String concatenation
    public function testStringConcatenationSimple(): void
    {
        $this->assertEquals("helloworld", self::$template->render('{{first + second}}', ['first' => 'hello', 'second' => 'world']));
    }

    public function testStringConcatenationWithSpace(): void
    {
        $this->assertEquals("hello world", self::$template->render('{{first + " " + second}}', ['first' => 'hello', 'second' => 'world']));
    }

    public function testStringConcatenationMultiple(): void
    {
        $this->assertEquals("John Doe Smith", self::$template->render('{{first + " " + middle + " " + last}}', ['first' => 'John', 'middle' => 'Doe', 'last' => 'Smith']));
    }

    public function testStringConcatenationWithNumber(): void
    {
        $this->assertEquals("Value: 42", self::$template->render('{{"Value: " + num}}', ['num' => 42]));
    }

    public function testNumericAdditionStillWorks(): void
    {
        $this->assertEquals("15", self::$template->render('{{a + b}}', ['a' => 10, 'b' => 5]));
        $this->assertEquals("7.5", self::$template->render('{{a + b}}', ['a' => 5, 'b' => 2.5]));
    }

    public function testExpressionWithNestedPaths(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:user.age >= 18}}yes{{endif}}', ['user' => ['age' => 21]]));
        $this->assertEquals("", self::$template->render('{{if:user.age >= 18}}yes{{endif}}', ['user' => ['age' => 16]]));
    }

    public function testExpressionInVariableOutput(): void
    {
        $this->assertEquals("8", self::$template->render('{{a + b}}', ['a' => 3, 'b' => 5]));
    }

    public function testExpressionWithMultipleConditions(): void
    {
        $this->assertEquals("passed", self::$template->render('{{if:score >= 60 && score <= 100 && !failed}}passed{{endif}}', ['score' => 75, 'failed' => false]));
    }

    // Expression tests - Without spaces
    public function testExpressionWithoutSpacesEquals(): void
    {
        $this->assertEquals("equal", self::$template->render('{{if:a==5}}equal{{endif}}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{{if:a==5}}equal{{endif}}', ['a' => 3]));
    }

    public function testExpressionWithoutSpacesComparison(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a<10}}yes{{endif}}', ['a' => 5]));
        $this->assertEquals("yes", self::$template->render('{{if:a>10}}yes{{endif}}', ['a' => 15]));
        $this->assertEquals("yes", self::$template->render('{{if:a<=10}}yes{{endif}}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{{if:a>=10}}yes{{endif}}', ['a' => 10]));
    }

    public function testExpressionWithoutSpacesArithmetic(): void
    {
        $this->assertEquals("15", self::$template->render('{{a+b}}', ['a' => 10, 'b' => 5]));
        $this->assertEquals("5", self::$template->render('{{a-b}}', ['a' => 10, 'b' => 5]));
        $this->assertEquals("50", self::$template->render('{{a*b}}', ['a' => 10, 'b' => 5]));
        $this->assertEquals("2", self::$template->render('{{a/b}}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionWithoutSpacesLogical(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a>5&&b<20}}yes{{endif}}', ['a' => 10, 'b' => 15]));
        $this->assertEquals("yes", self::$template->render('{{if:a>5||b>20}}yes{{endif}}', ['a' => 10, 'b' => 15]));
    }

    public function testExpressionWithoutSpacesPrecedence(): void
    {
        $this->assertEquals("14", self::$template->render('{{a+b*c}}', ['a' => 2, 'b' => 3, 'c' => 4]));
        $this->assertEquals("18", self::$template->render('{{(a+b)*c}}', ['a' => 2, 'b' => 4, 'c' => 3]));
    }

    public function testExpressionWithoutSpacesComplex(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a==1||b==2&&c==3}}yes{{endif}}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("yes", self::$template->render('{{if:(a+b)>10&&c<20}}yes{{endif}}', ['a' => 7, 'b' => 5, 'c' => 15]));
    }

    public function testExpressionMixedSpacing(): void
    {
        $this->assertEquals("yes", self::$template->render('{{if:a+b>10&&c<20}}yes{{endif}}', ['a' => 7, 'b' => 5, 'c' => 15]));
        $this->assertEquals("17", self::$template->render('{{a +b* c}}', ['a' => 5, 'b' => 3, 'c' => 4]));
    }
}

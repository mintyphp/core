<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    private static Template $template;

    public static function setUpBeforeClass(): void
    {
        self::$template = new Template();
    }

    public function testRenderWithCustomFunction(): void
    {
        $result = self::$template->render('hello {{ name|capitalize }}', ['name' => 'world'], ['capitalize' => 'ucfirst']);
        $this->assertEquals("hello World", $result);
    }

    public function testRenderWithHtmlEscaping(): void
    {
        $this->assertEquals("<br>hello &lt;br&gt;world", self::$template->render('<br>hello {{ name }}', ['name' => '<br>world']));
    }

    public function testRenderWithMissingFunction(): void
    {
        $this->assertEquals("hello {{name|failure!!function `failure` not found}}", self::$template->render('hello {{ name|failure }}', ['name' => 'world'], ['capitalize' => 'ucfirst']));
    }

    public function testRenderIfWithNestedPath(): void
    {
        $this->assertEquals("hello m is 3", self::$template->render('hello {% if n.m|eq(3) %}m is 3{% endif %}', ['n' => ['m' => 3]], ['eq' => fn($a, $b) => $a == $b]));
    }

    public function testRenderIfElse(): void
    {
        $this->assertEquals("hello not n", self::$template->render('hello {% if n %}n{% else %}not n{% endif %}', ['n' => false]));
    }

    public function testRenderWithFunctionLiteralArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{ name|dateFormat("Y-m-d") }}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionDataArgument(): void
    {
        $this->assertEquals("hello 1980-05-13", self::$template->render('hello {{ name|dateFormat(format) }}', ['name' => 'May 13, 1980', 'format' => 'Y-m-d'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionComplexLiteralArgument(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{ name|dateFormat("M j, Y") }}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithFunctionArgumentWithWhitespace(): void
    {
        $this->assertEquals("hello May 13, 1980", self::$template->render('hello {{ name|dateFormat( "M j, Y") }}', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderWithEscapedSpecialCharacters(): void
    {
        $this->assertEquals("hello \" May ()}}&quot;,|:.13, 1980\"", self::$template->render('hello "{{ name|dateFormat(" M ()}}\\",|:.j, Y") }}"', ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testRenderForLoopWithValues(): void
    {
        $this->assertEquals("test 1 2 3", self::$template->render('test{% for i in counts %} {{ i }}{% endfor %}', ['counts' => [1, 2, 3]]));
    }

    public function testRenderForLoopWithKeysAndValues(): void
    {
        $this->assertEquals("test a=1 b=2 c=3", self::$template->render('test{% for k, v in counts %} {{ k }}={{ v }}{% endfor %}', ['counts' => ['a' => 1, 'b' => 2, 'c' => 3]]));
    }

    public function testRenderNestedForLoops(): void
    {
        $this->assertEquals("test (-1,-1) (-1,1) (1,-1) (1,1)", self::$template->render('test{% for x in steps %}{% for y in steps %} ({{ x }},{{ y }}){% endfor %}{% endfor %}', ['steps' => [-1, 1]]));
    }

    public function testRenderForLoopWithIfElseIf(): void
    {
        $this->assertEquals("hello one two three", self::$template->render('hello{% for i in counts %} {% if i|eq(1) %}one{% elseif i|eq(2) %}two{% else %}three{% endif %}{% endfor %}', ['counts' => [1, 2, 3]], ['eq' => fn($a, $b) => $a == $b]));
    }

    public function testEscape(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', self::$template->render('{{ a }}', ['a' => '<script>alert("xss")</script>'], []));
    }

    public function testRawEscape(): void
    {
        $this->assertEquals('<script>alert("xss")</script>', self::$template->render('{{ a|raw }}', ['a' => '<script>alert("xss")</script>'], []));
    }

    public function testNoEscape(): void
    {
        // Since HTML escaping is now always enabled, use raw filter to bypass escaping
        $template = new Template();
        $this->assertEquals('<script>alert("xss")</script>', $template->render('{{ a|raw }}', ['a' => '<script>alert("xss")</script>'], []));
    }

    // Expression tests - Basic comparison operators
    public function testExpressionEquals(): void
    {
        $this->assertEquals("equal", self::$template->render('{% if a == 5 %}equal{% endif %}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{% if a == 5 %}equal{% endif %}', ['a' => 3]));
    }

    public function testExpressionNotEquals(): void
    {
        $this->assertEquals("not equal", self::$template->render('{% if a != 5 %}not equal{% endif %}', ['a' => 3]));
        $this->assertEquals("", self::$template->render('{% if a != 5 %}not equal{% endif %}', ['a' => 5]));
    }

    public function testExpressionLessThan(): void
    {
        $this->assertEquals("less", self::$template->render('{% if a < 10 %}less{% endif %}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{% if a < 10 %}less{% endif %}', ['a' => 15]));
    }

    public function testExpressionGreaterThan(): void
    {
        $this->assertEquals("greater", self::$template->render('{% if a > 10 %}greater{% endif %}', ['a' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 10 %}greater{% endif %}', ['a' => 5]));
    }

    public function testExpressionLessThanOrEqual(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if a <= 10 %}yes{% endif %}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{% if a <= 10 %}yes{% endif %}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{% if a <= 10 %}yes{% endif %}', ['a' => 15]));
    }

    public function testExpressionGreaterThanOrEqual(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if a >= 10 %}yes{% endif %}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{% if a >= 10 %}yes{% endif %}', ['a' => 15]));
        $this->assertEquals("", self::$template->render('{% if a >= 10 %}yes{% endif %}', ['a' => 5]));
    }

    // Expression tests - Logical operators
    public function testExpressionLogicalAnd(): void
    {
        $this->assertEquals("both true", self::$template->render('{% if a > 5 && b < 20 %}both true{% endif %}', ['a' => 10, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 && b < 20 %}both true{% endif %}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 && b < 20 %}both true{% endif %}', ['a' => 10, 'b' => 25]));
    }

    public function testExpressionLogicalOr(): void
    {
        $this->assertEquals("at least one", self::$template->render('{% if a > 5 || b < 20 %}at least one{% endif %}', ['a' => 10, 'b' => 25]));
        $this->assertEquals("at least one", self::$template->render('{% if a > 5 || b < 20 %}at least one{% endif %}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 || b < 20 %}at least one{% endif %}', ['a' => 3, 'b' => 25]));
    }

    public function testExpressionLogicalNot(): void
    {
        $this->assertEquals("not true", self::$template->render('{% if not a %}not true{% endif %}', ['a' => false]));
        $this->assertEquals("", self::$template->render('{% if not a %}not true{% endif %}', ['a' => true]));
    }

    public function testExpressionLogicalAndWordBased(): void
    {
        $this->assertEquals("both true", self::$template->render('{% if a > 5 and b < 20 %}both true{% endif %}', ['a' => 10, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 and b < 20 %}both true{% endif %}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 and b < 20 %}both true{% endif %}', ['a' => 10, 'b' => 25]));
    }

    public function testExpressionLogicalOrWordBased(): void
    {
        $this->assertEquals("at least one", self::$template->render('{% if a > 5 or b < 20 %}at least one{% endif %}', ['a' => 10, 'b' => 25]));
        $this->assertEquals("at least one", self::$template->render('{% if a > 5 or b < 20 %}at least one{% endif %}', ['a' => 3, 'b' => 15]));
        $this->assertEquals("", self::$template->render('{% if a > 5 or b < 20 %}at least one{% endif %}', ['a' => 3, 'b' => 25]));
    }

    public function testExpressionLogicalMixedWordAndSymbol(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if a > 5 and b < 20 or c == 10 %}yes{% endif %}', ['a' => 10, 'b' => 15, 'c' => 0]));
        $this->assertEquals("yes", self::$template->render('{% if a > 5 || b < 20 and c == 10 %}yes{% endif %}', ['a' => 10, 'b' => 25, 'c' => 5]));
    }

    public function testExpressionLogicalNotWithAnd(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if not a and b %}yes{% endif %}', ['a' => false, 'b' => true]));
        $this->assertEquals("", self::$template->render('{% if not a and b %}yes{% endif %}', ['a' => true, 'b' => true]));
        $this->assertEquals("", self::$template->render('{% if not a and b %}yes{% endif %}', ['a' => false, 'b' => false]));
    }

    public function testExpressionLogicalNotWithOr(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if not a or b %}yes{% endif %}', ['a' => false, 'b' => false]));
        $this->assertEquals("yes", self::$template->render('{% if not a or b %}yes{% endif %}', ['a' => true, 'b' => true]));
        $this->assertEquals("", self::$template->render('{% if not a or b %}yes{% endif %}', ['a' => true, 'b' => false]));
    }

    // Expression tests - Arithmetic operators
    public function testExpressionAddition(): void
    {
        $this->assertEquals("15", self::$template->render('{{ a + b }}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionSubtraction(): void
    {
        $this->assertEquals("5", self::$template->render('{{ a - b }}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionMultiplication(): void
    {
        $this->assertEquals("50", self::$template->render('{{ a * b }}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionDivision(): void
    {
        $this->assertEquals("2", self::$template->render('{{ a / b }}', ['a' => 10, 'b' => 5]));
    }

    public function testExpressionDivisionByZero(): void
    {
        $this->assertEquals("{{a / 0!!division by zero}}", self::$template->render('{{ a / 0 }}', ['a' => 10]));
    }

    public function testExpressionModulo(): void
    {
        $this->assertEquals("1", self::$template->render('{{ a % b }}', ['a' => 10, 'b' => 3]));
    }

    public function testExpressionModuloByZero(): void
    {
        $this->assertEquals("{{a % 0!!modulo by zero}}", self::$template->render('{{ a % 0 }}', ['a' => 10]));
    }

    public function testExpressionNotEnoughOperandsUnary(): void
    {
        $this->assertEquals("{{not!!not enough operands for &#039;not&#039;}}", self::$template->render('{{ not }}', []));
    }

    public function testExpressionNotEnoughOperandsBinary(): void
    {
        $this->assertEquals("{{5 +!!not enough operands for &#039;+&#039;}}", self::$template->render('{{ 5 + }}', []));
    }

    public function testExpressionMalformedExpression(): void
    {
        $this->assertEquals("{{5 5!!malformed expression}}", self::$template->render('{{ 5 5 }}', []));
    }

    // Expression tests - Operator precedence
    public function testExpressionPrecedenceArithmetic(): void
    {
        $this->assertEquals("14", self::$template->render('{{ a + b * c }}', ['a' => 2, 'b' => 3, 'c' => 4]));
    }

    public function testExpressionPrecedenceComparison(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if a + b > c %}yes{% endif %}', ['a' => 5, 'b' => 10, 'c' => 12]));
    }

    public function testExpressionPrecedenceLogical(): void
    {
        // && has higher precedence than ||
        $this->assertEquals("yes", self::$template->render('{% if a == 1 || b == 2 && c == 3 %}yes{% endif %}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("", self::$template->render('{% if a == 1 || b == 2 && c == 3 %}yes{% endif %}', ['a' => 5, 'b' => 2, 'c' => 5]));
    }

    // Expression tests - Nested expressions with parentheses
    public function testExpressionParenthesesArithmetic(): void
    {
        $this->assertEquals("18", self::$template->render('{{ (a + b) * c }}', ['a' => 2, 'b' => 4, 'c' => 3]));
    }

    public function testExpressionParenthesesLogical(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if (a == 1 || b == 2) && c == 3 %}yes{% endif %}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("", self::$template->render('{% if (a == 1 || b == 2) && c == 3 %}yes{% endif %}', ['a' => 5, 'b' => 5, 'c' => 3]));
    }

    public function testExpressionNestedParentheses(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if ((a + b) * c) > 20 %}yes{% endif %}', ['a' => 5, 'b' => 5, 'c' => 3]));
    }

    // Expression tests - Complex combined conditions
    public function testExpressionComplexCondition1(): void
    {
        $this->assertEquals("match", self::$template->render('{% if a > 5 && b < 20 || c == 10 %}match{% endif %}', ['a' => 10, 'b' => 15, 'c' => 0]));
    }

    public function testExpressionComplexCondition2(): void
    {
        $this->assertEquals("match", self::$template->render('{% if (a > 5 || b > 5) && (c < 20 || d < 20) %}match{% endif %}', ['a' => 3, 'b' => 10, 'c' => 25, 'd' => 15]));
    }

    public function testExpressionInElseIf(): void
    {
        $this->assertEquals("second", self::$template->render('{% if a > 10 %}first{% elseif a > 5 %}second{% else %}third{% endif %}', ['a' => 7]));
    }

    public function testExpressionWithStringLiterals(): void
    {
        $this->assertEquals("match", self::$template->render('{% if name == "John" %}match{% endif %}', ['name' => 'John']));
        $this->assertEquals("", self::$template->render('{% if name == "John" %}match{% endif %}', ['name' => 'Jane']));
    }

    public function testExpressionWithNumericLiterals(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if a + 5 > 10 %}yes{% endif %}', ['a' => 8]));
    }

    // Expression tests - String concatenation
    public function testStringConcatenationSimple(): void
    {
        $this->assertEquals("helloworld", self::$template->render('{{ first + second }}', ['first' => 'hello', 'second' => 'world']));
    }

    public function testStringConcatenationWithSpace(): void
    {
        $this->assertEquals("hello world", self::$template->render('{{ first + " " + second }}', ['first' => 'hello', 'second' => 'world']));
    }

    public function testStringConcatenationMultiple(): void
    {
        $this->assertEquals("John Doe Smith", self::$template->render('{{ first + " " + middle + " " + last }}', ['first' => 'John', 'middle' => 'Doe', 'last' => 'Smith']));
    }

    public function testStringConcatenationWithNumber(): void
    {
        $this->assertEquals("Value: 42", self::$template->render('{{ "Value: " + num }}', ['num' => 42]));
    }

    public function testNumericAdditionStillWorks(): void
    {
        $this->assertEquals("15", self::$template->render('{{ a + b }}', ['a' => 10, 'b' => 5]));
        $this->assertEquals("7.5", self::$template->render('{{ a + b }}', ['a' => 5, 'b' => 2.5]));
    }

    public function testExpressionWithNestedPaths(): void
    {
        $this->assertEquals("yes", self::$template->render('{% if user.age >= 18 %}yes{% endif %}', ['user' => ['age' => 21]]));
        $this->assertEquals("", self::$template->render('{% if user.age >= 18 %}yes{% endif %}', ['user' => ['age' => 16]]));
    }

    public function testExpressionInVariableOutput(): void
    {
        $this->assertEquals("8", self::$template->render('{{ a + b }}', ['a' => 3, 'b' => 5]));
    }

    public function testExpressionWithMultipleConditions(): void
    {
        $this->assertEquals("passed", self::$template->render('{% if score >= 60 && score <= 100 && not failed %}passed{% endif %}', ['score' => 75, 'failed' => false]));
    }

    // Expression tests - Without spaces
    public function testExpressionWithoutSpacesEquals(): void
    {
        $this->assertEquals("equal", self::$template->render('{%if a==5%}equal{%endif%}', ['a' => 5]));
        $this->assertEquals("", self::$template->render('{%if a==5%}equal{%endif%}', ['a' => 3]));
    }

    public function testExpressionWithoutSpacesComparison(): void
    {
        $this->assertEquals("yes", self::$template->render('{%if a<10%}yes{%endif%}', ['a' => 5]));
        $this->assertEquals("yes", self::$template->render('{%if a>10%}yes{%endif%}', ['a' => 15]));
        $this->assertEquals("yes", self::$template->render('{%if a<=10%}yes{%endif%}', ['a' => 10]));
        $this->assertEquals("yes", self::$template->render('{%if a>=10%}yes{%endif%}', ['a' => 10]));
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
        $this->assertEquals("yes", self::$template->render('{%if a>5&&b<20%}yes{%endif%}', ['a' => 10, 'b' => 15]));
        $this->assertEquals("yes", self::$template->render('{%if a>5||b>20%}yes{%endif%}', ['a' => 10, 'b' => 15]));
    }

    public function testExpressionWithoutSpacesPrecedence(): void
    {
        $this->assertEquals("14", self::$template->render('{{a+b*c}}', ['a' => 2, 'b' => 3, 'c' => 4]));
        $this->assertEquals("18", self::$template->render('{{(a+b)*c}}', ['a' => 2, 'b' => 4, 'c' => 3]));
    }

    public function testExpressionWithoutSpacesComplex(): void
    {
        $this->assertEquals("yes", self::$template->render('{%if a==1||b==2&&c==3%}yes{%endif%}', ['a' => 5, 'b' => 2, 'c' => 3]));
        $this->assertEquals("yes", self::$template->render('{%if (a+b)>10&&c<20%}yes{%endif%}', ['a' => 7, 'b' => 5, 'c' => 15]));
    }

    public function testExpressionMixedSpacing(): void
    {
        $this->assertEquals("yes", self::$template->render('{%if a+b>10&&c<20%}yes{%endif%}', ['a' => 7, 'b' => 5, 'c' => 15]));
        $this->assertEquals("17", self::$template->render('{{a +b* c}}', ['a' => 5, 'b' => 3, 'c' => 4]));
    }

    // Multiline template tests inspired by Jinja
    public function testMultilineForLoopSimple(): void
    {
        $template = "<ul>\n{% for item in items %}\n    <li>{{ item }}</li>\n{% endfor %}\n</ul>";
        $expected = "<ul>\n    <li>apple</li>\n    <li>banana</li>\n    <li>cherry</li>\n</ul>";
        $this->assertEquals($expected, self::$template->render($template, ['items' => ['apple', 'banana', 'cherry']]));
    }

    public function testMultilineForLoopWithIndentation(): void
    {
        $template = "<div>\n    <ul>\n    {% for user in users %}\n        <li>{{ user }}</li>\n    {% endfor %}\n    </ul>\n</div>";
        $expected = "<div>\n    <ul>\n        <li>Alice</li>\n        <li>Bob</li>\n    </ul>\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['users' => ['Alice', 'Bob']]));
    }

    public function testMultilineIfWithWhitespace(): void
    {
        $template = "<div>\n    {% if active %}\n        <span>Active</span>\n    {% endif %}\n</div>";
        $expected = "<div>\n        <span>Active</span>\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['active' => true]));
    }

    public function testMultilineIfElseWithWhitespace(): void
    {
        $template = "<div>\n    {% if active %}\n        <span>Active</span>\n    {% else %}\n        <span>Inactive</span>\n    {% endif %}\n</div>";
        $expected = "<div>\n        <span>Inactive</span>\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['active' => false]));
    }

    public function testMultilineNestedForLoops(): void
    {
        $template = "<table>\n{% for row in rows %}\n    <tr>\n    {% for cell in row %}\n        <td>{{ cell }}</td>\n    {% endfor %}\n    </tr>\n{% endfor %}\n</table>";
        $expected = "<table>\n    <tr>\n        <td>1</td>\n        <td>2</td>\n    </tr>\n    <tr>\n        <td>3</td>\n        <td>4</td>\n    </tr>\n</table>";
        $this->assertEquals($expected, self::$template->render($template, ['rows' => [[1, 2], [3, 4]]]));
    }

    public function testMultilineComplexHtmlStructure(): void
    {
        $template = "<!DOCTYPE html>\n<html>\n<head>\n    <title>{{ title }}</title>\n</head>\n<body>\n    <ul id=\"navigation\">\n    {% for item in navigation %}\n        <li><a href=\"{{ item.href }}\">{{ item.caption }}</a></li>\n    {% endfor %}\n    </ul>\n    <h1>{{ heading }}</h1>\n</body>\n</html>";

        $data = [
            'title' => 'My Page',
            'heading' => 'Welcome',
            'navigation' => [
                ['href' => '/home', 'caption' => 'Home'],
                ['href' => '/about', 'caption' => 'About']
            ]
        ];

        $expected = "<!DOCTYPE html>\n<html>\n<head>\n    <title>My Page</title>\n</head>\n<body>\n    <ul id=\"navigation\">\n        <li><a href=\"/home\">Home</a></li>\n        <li><a href=\"/about\">About</a></li>\n    </ul>\n    <h1>Welcome</h1>\n</body>\n</html>";

        $this->assertEquals($expected, self::$template->render($template, $data));
    }

    public function testWhitespacePreservationWithLeadingSpaces(): void
    {
        $template = "    Leading spaces\n{{ text }}\n    Trailing spaces    ";
        $expected = "    Leading spaces\nHello\n    Trailing spaces    ";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Hello']));
    }

    public function testWhitespacePreservationWithTabs(): void
    {
        $template = "\t\tTabbed content\n{{ text }}\n\t\tMore tabs";
        $expected = "\t\tTabbed content\nWorld\n\t\tMore tabs";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'World']));
    }

    public function testWhitespacePreservationEmptyLines(): void
    {
        $template = "Line 1\n\n{{ text }}\n\nLine 4";
        $expected = "Line 1\n\nTest\n\nLine 4";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Test']));
    }

    public function testMultilineForLoopWithEmptyList(): void
    {
        $template = "<ul>\n{% for item in items %}\n    <li>{{ item }}</li>\n{% endfor %}\n</ul>";
        $expected = "<ul>\n</ul>";
        $this->assertEquals($expected, self::$template->render($template, ['items' => []]));
    }

    public function testMultilineIfWithFalseCondition(): void
    {
        $template = "<div>\n    Content before\n    {% if show %}\n        This should not appear\n    {% endif %}\n    Content after\n</div>";
        $expected = "<div>\n    Content before\n    Content after\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['show' => false]));
    }

    public function testMultilineTextPreservation(): void
    {
        $template = "First line\nSecond line\nThird line with {{ var }}\nFourth line";
        $expected = "First line\nSecond line\nThird line with value\nFourth line";
        $this->assertEquals($expected, self::$template->render($template, ['var' => 'value']));
    }

    public function testMultilineWithMixedContentTypes(): void
    {
        $template = "<p>\n    Text content\n    {{ text }}\n    {% if show %}\n        <strong>{{ emphasis }}</strong>\n    {% endif %}\n    More text\n</p>";
        $expected = "<p>\n    Text content\n    Hello\n        <strong>Important</strong>\n    More text\n</p>";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Hello', 'show' => true, 'emphasis' => 'Important']));
    }

    public function testMultilineHtmlListWithData(): void
    {
        $template = "<h1>Members</h1>\n<ul>\n{% for user in users %}\n  <li>{{ user.username }}</li>\n{% endfor %}\n</ul>";
        $expected = "<h1>Members</h1>\n<ul>\n  <li>alice</li>\n  <li>bob</li>\n  <li>charlie</li>\n</ul>";
        $data = [
            'users' => [
                ['username' => 'alice'],
                ['username' => 'bob'],
                ['username' => 'charlie']
            ]
        ];
        $this->assertEquals($expected, self::$template->render($template, $data));
    }

    public function testMultilineNestedIfStatements(): void
    {
        $template = "<div>\n{% if outer %}\n    <div class=\"outer\">\n    {% if inner %}\n        <div class=\"inner\">Content</div>\n    {% endif %}\n    </div>\n{% endif %}\n</div>";
        $expected = "<div>\n    <div class=\"outer\">\n        <div class=\"inner\">Content</div>\n    </div>\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['outer' => true, 'inner' => true]));
    }

    public function testMultilineWhitespaceOnlyBetweenTags(): void
    {
        $template = "<div>   \n   {{ text }}   \n   </div>";
        $expected = "<div>   \n   Value   \n   </div>";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Value']));
    }

    public function testMultilineCommentLikeStructure(): void
    {
        // Test Jinja-style {# #} comment syntax - comments should be completely removed
        $template = "<div>\n    {# This is a comment #}\n    {{ content }}\n    {# Another comment #}\n</div>";
        $expected = "<div>\n    Data\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['content' => 'Data']));
    }

    public function testMultilineForLoopWithComplexData(): void
    {
        $template = "<dl>\n{% for item in items %}\n  <dt>{{ item.key }}</dt>\n  <dd>{{ item.value }}</dd>\n{% endfor %}\n</dl>";
        $expected = "<dl>\n  <dt>Name</dt>\n  <dd>John</dd>\n  <dt>Age</dt>\n  <dd>30</dd>\n</dl>";
        $data = [
            'items' => [
                ['key' => 'Name', 'value' => 'John'],
                ['key' => 'Age', 'value' => '30']
            ]
        ];
        $this->assertEquals($expected, self::$template->render($template, $data));
    }

    public function testMultilineTemplateWithNoWhitespace(): void
    {
        $template = "<ul>{% for i in items %}<li>{{ i }}</li>{% endfor %}</ul>";
        $expected = "<ul><li>A</li><li>B</li></ul>";
        $this->assertEquals($expected, self::$template->render($template, ['items' => ['A', 'B']]));
    }

    public function testMultilineIndentationVariations(): void
    {
        $template = "<div>\n  Two spaces\n    Four spaces\n\tOne tab\n{{ text }}\n</div>";
        $expected = "<div>\n  Two spaces\n    Four spaces\n\tOne tab\nValue\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Value']));
    }

    // Comment syntax tests - {# ... #}
    public function testCommentSimple(): void
    {
        $this->assertEquals("hello  world", self::$template->render('hello {# comment #} world', []));
    }

    public function testCommentWithVariables(): void
    {
        $this->assertEquals("hello  test world", self::$template->render('hello {# this is ignored #} {{ text }} world', ['text' => 'test']));
    }

    public function testCommentMultiline(): void
    {
        $template = "Line 1\n{# This is\na multiline\ncomment #}\nLine 2";
        $expected = "Line 1\nLine 2";
        $this->assertEquals($expected, self::$template->render($template, []));
    }

    public function testCommentWithControlStructures(): void
    {
        $this->assertEquals("result", self::$template->render('{# comment #}{% if true %}result{% endif %}{# another #}', ['true' => true]));
    }

    public function testCommentMultiple(): void
    {
        $this->assertEquals("abc", self::$template->render('a{# one #}b{# two #}c{# three #}', []));
    }

    public function testCommentWithSpecialChars(): void
    {
        $this->assertEquals("text", self::$template->render('{# {{ }} {% %} #}text', []));
    }

    public function testCommentInTemplate(): void
    {
        $template = "{# Header comment #}\n<div>\n    {# Content comment #}\n    {{ content }}\n</div>\n{# Footer comment #}";
        $expected = "<div>\n    Data\n</div>\n";
        $this->assertEquals($expected, self::$template->render($template, ['content' => 'Data']));
    }

    public function testCommentBeforeAndAfterVariable(): void
    {
        $this->assertEquals("Value", self::$template->render('{# before #}{{ text }}{# after #}', ['text' => 'Value']));
    }

    public function testCommentInForLoop(): void
    {
        $template = "{% for i in items %}{# loop comment #}{{ i }}{% endfor %}";
        $expected = "123";
        $this->assertEquals($expected, self::$template->render($template, ['items' => [1, 2, 3]]));
    }

    public function testCommentEmpty(): void
    {
        $this->assertEquals("text", self::$template->render('{##}text', []));
    }

    // Newlines in expressions tests
    public function testExpressionWithNewlineInVariable(): void
    {
        $template = "{{ a\n+ b }}";
        $this->assertEquals("15", self::$template->render($template, ['a' => 10, 'b' => 5]));
    }

    public function testExpressionWithMultipleNewlinesInVariable(): void
    {
        $template = "{{ a\n+\nb\n*\nc }}";
        $this->assertEquals("14", self::$template->render($template, ['a' => 2, 'b' => 3, 'c' => 4]));
    }

    public function testExpressionWithNewlineInIfCondition(): void
    {
        $template = "{% if a\n>\n5 %}yes{% endif %}";
        $this->assertEquals("yes", self::$template->render($template, ['a' => 10]));
    }

    public function testExpressionWithNewlineInComplexCondition(): void
    {
        $template = "{% if a\n>\n5\n&&\nb\n<\n20 %}match{% endif %}";
        $this->assertEquals("match", self::$template->render($template, ['a' => 10, 'b' => 15]));
    }

    public function testExpressionWithNewlineInParentheses(): void
    {
        $template = "{{ (\na\n+\nb\n)\n*\nc }}";
        $this->assertEquals("18", self::$template->render($template, ['a' => 2, 'b' => 4, 'c' => 3]));
    }

    public function testExpressionWithNewlineInComparison(): void
    {
        $template = "{% if a\n==\n5 %}equal{% endif %}";
        $this->assertEquals("equal", self::$template->render($template, ['a' => 5]));
    }

    public function testExpressionWithNewlineInLogicalOperators(): void
    {
        $template = "{% if a\nand\nb\nor\nc %}yes{% endif %}";
        $this->assertEquals("yes", self::$template->render($template, ['a' => false, 'b' => false, 'c' => true]));
    }

    public function testExpressionWithNewlineInForLoop(): void
    {
        $template = "{% for i\nin\nitems %}{{ i }}{% endfor %}";
        $this->assertEquals("123", self::$template->render($template, ['items' => [1, 2, 3]]));
    }

    public function testExpressionWithNewlineInStringConcatenationAndInString(): void
    {
        $template = "{{ first\n+\n\"\n\"\n+\nsecond }}";
        $this->assertEquals("hello\nworld", self::$template->render($template, ['first' => 'hello', 'second' => 'world']));
    }

    public function testExpressionWithNewlineBeforeFilter(): void
    {
        $template = "{{ name\n|capitalize }}";
        $this->assertEquals("World", self::$template->render($template, ['name' => 'world'], ['capitalize' => 'ucfirst']));
    }

    public function testExpressionWithNewlineInFilterArguments(): void
    {
        $template = "{{ name\n|dateFormat(\n\"Y-m-d\"\n) }}";
        $this->assertEquals("1980-05-13", self::$template->render($template, ['name' => 'May 13, 1980'], ['dateFormat' => fn(string $date, string $format) => date($format, strtotime($date) ?: null)]));
    }

    public function testExpressionWithCarriageReturnNewline(): void
    {
        $template = "{{ a\r\n+\r\nb }}";
        $this->assertEquals("15", self::$template->render($template, ['a' => 10, 'b' => 5]));
    }

    public function testExpressionWithMixedWhitespaceAndNewlines(): void
    {
        $template = "{{ a  \n  +  \n  b  \n  *  \n  c }}";
        $this->assertEquals("14", self::$template->render($template, ['a' => 2, 'b' => 3, 'c' => 4]));
    }

    public function testExpressionWithNewlineInElseIfCondition(): void
    {
        $template = "{% if a\n>\n10 %}first{% elseif a\n>\n5 %}second{% else %}third{% endif %}";
        $this->assertEquals("second", self::$template->render($template, ['a' => 7]));
    }

    // Tests for builtin test functions

    public function testIsDefined(): void
    {
        $tmpl = "{% if variable is defined %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['variable' => 'value']);
        $this->assertEquals("yes", $result);

        // Test undefined variable
        $tmpl = "{% if missing is defined %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, []);
        $this->assertEquals("no", $result);
    }

    public function testIsUndefined(): void
    {
        $tmpl = "{% if missing is undefined %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, []);
        $this->assertEquals("yes", $result);

        // Test defined variable
        $tmpl = "{% if variable is undefined %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['variable' => 'value']);
        $this->assertEquals("no", $result);
    }

    public function testIsEven(): void
    {
        $tmpl = "{% if num is even %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['num' => 4]);
        $this->assertEquals("yes", $result);

        // Test odd number
        $result = self::$template->render($tmpl, ['num' => 3]);
        $this->assertEquals("no", $result);
    }

    public function testIsOdd(): void
    {
        $tmpl = "{% if num is odd %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['num' => 3]);
        $this->assertEquals("yes", $result);

        // Test even number
        $result = self::$template->render($tmpl, ['num' => 4]);
        $this->assertEquals("no", $result);
    }

    public function testIsDivisibleBy(): void
    {
        $tmpl = "{% if num is divisibleby(3) %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['num' => 9]);
        $this->assertEquals("yes", $result);

        // Test not divisible
        $result = self::$template->render($tmpl, ['num' => 10]);
        $this->assertEquals("no", $result);

        // Test divisible by 2
        $tmpl = "{% if num is divisibleby(2) %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['num' => 10]);
        $this->assertEquals("yes", $result);
    }

    public function testIsIterable(): void
    {
        $tmpl = "{% if items is iterable %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['items' => [1, 2, 3]]);
        $this->assertEquals("yes", $result);

        // Test non-iterable
        $result = self::$template->render($tmpl, ['items' => 42]);
        $this->assertEquals("no", $result);

        // Test string is iterable
        $result = self::$template->render($tmpl, ['items' => 'hello']);
        $this->assertEquals("yes", $result);
    }

    public function testIsNull(): void
    {
        $tmpl = "{% if value is null %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => null]);
        $this->assertEquals("yes", $result);

        // Test non-null
        $result = self::$template->render($tmpl, ['value' => 'something']);
        $this->assertEquals("no", $result);
    }

    public function testIsNumber(): void
    {
        $tmpl = "{% if value is number %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => 42]);
        $this->assertEquals("yes", $result);

        // Test float
        $result = self::$template->render($tmpl, ['value' => 3.14]);
        $this->assertEquals("yes", $result);

        // Test string number
        $result = self::$template->render($tmpl, ['value' => '123']);
        $this->assertEquals("yes", $result);

        // Test non-number string
        $result = self::$template->render($tmpl, ['value' => 'abc']);
        $this->assertEquals("no", $result);
    }

    public function testIsString(): void
    {
        $tmpl = "{% if value is string %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => 'hello']);
        $this->assertEquals("yes", $result);

        // Test number
        $result = self::$template->render($tmpl, ['value' => 42]);
        $this->assertEquals("no", $result);
    }

    public function testIsNotTest(): void
    {
        $tmpl = "{% if value is not null %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => 'something']);
        $this->assertEquals("yes", $result);

        // Test with null value
        $result = self::$template->render($tmpl, ['value' => null]);
        $this->assertEquals("no", $result);
    }

    public function testIsTestInVariable(): void
    {
        $tmpl = "{{ num is even }}";
        $result = self::$template->render($tmpl, ['num' => 4]);
        $this->assertEquals("1", $result);

        // Test false case
        $result = self::$template->render($tmpl, ['num' => 3]);
        $this->assertEquals("", $result);
    }

    public function testIsTestWithComplexExpression(): void
    {
        $tmpl = "{% if (value + 1) is even %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => 3]);
        $this->assertEquals("yes", $result);
    }

    public function testMultipleIsTests(): void
    {
        $tmpl = "{% if value is number and value is even %}yes{% else %}no{% endif %}";
        $result = self::$template->render($tmpl, ['value' => 4]);
        $this->assertEquals("yes", $result);
    }

    // Test basic block definition and rendering
    public function testBlockBasic(): void
    {
        $tmpl = "<html>\n{% block title %}Default Title{% endblock %}\n{% block content %}Default Content{% endblock %}\n</html>";
        $expected = "<html>\nDefault Title\nDefault Content\n</html>";

        $template = new Template();
        $result = $template->render($tmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test extends with block overrides
    public function testExtendsWithBlockOverride(): void
    {
        // Create a simple in-memory template loader
        $templates = [
            'base.html' => "<html>\n<head>\n  <title>{% block title %}My Website{% endblock %}</title>\n</head>\n<body>\n  {% block content %}{% endblock %}\n</body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block title %}Home Page{% endblock %}\n\n{% block content %}\n<h1>Welcome to the home page!</h1>\n{% endblock %}";

        $expected = "<html>\n<head>\n  <title>Home Page</title>\n</head>\n<body>\n<h1>Welcome to the home page!</h1>\n</body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test extends with partial block override (some blocks keep default)
    public function testExtendsWithPartialOverride(): void
    {
        $templates = [
            'base.html' => "<html>\n<head>\n  <title>{% block title %}Default Title{% endblock %}</title>\n</head>\n<body>\n  <header>{% block header %}Default Header{% endblock %}</header>\n  <main>{% block content %}Default Content{% endblock %}</main>\n  <footer>{% block footer %}Default Footer{% endblock %}</footer>\n</body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block title %}Custom Title{% endblock %}\n\n{% block content %}<p>Custom content here</p>\n{% endblock %}";

        $expected = "<html>\n<head>\n  <title>Custom Title</title>\n</head>\n<body>\n  <header>Default Header</header>\n  <main><p>Custom content here</p>\n</main>\n  <footer>Default Footer</footer>\n</body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test extends with variables in blocks
    public function testExtendsWithVariables(): void
    {
        $templates = [
            'base.html' => "<html>\n<head>\n  <title>{% block title %}{{ site_name }}{% endblock %}</title>\n</head>\n<body>\n  {% block content %}{% endblock %}\n</body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block title %}{{ page_title }} - {{ site_name }}{% endblock %}\n\n{% block content %}\n<h1>{{ heading }}</h1>\n<p>{{ message }}</p>\n{% endblock %}";

        $data = [
            'site_name' => 'My Site',
            'page_title' => 'About',
            'heading' => 'About Us',
            'message' => 'Welcome to our site!',
        ];

        $expected = "<html>\n<head>\n  <title>About - My Site</title>\n</head>\n<body>\n<h1>About Us</h1>\n<p>Welcome to our site!</p>\n</body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, $data);
        $this->assertEquals($expected, $result);
    }

    // Test extends with control structures in blocks
    public function testExtendsWithControlStructures(): void
    {
        $templates = [
            'base.html' => "<html>\n<body>\n  <ul>\n  {% block navigation %}\n    <li><a href=\"/\">Home</a></li>\n  {% endblock %}\n  </ul>\n  {% block content %}{% endblock %}\n</body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block navigation %}\n{% for item in menu %}\n    <li><a href=\"{{ item.url }}\">{{ item.title }}</a></li>\n{% endfor %}\n{% endblock %}\n\n{% block content %}\n<h1>{{ title }}</h1>\n{% if show_list %}\n<ul>\n{% for item in items %}\n  <li>{{ item }}</li>\n{% endfor %}\n</ul>\n{% endif %}\n{% endblock %}";

        $data = [
            'menu' => [
                ['url' => '/', 'title' => 'Home'],
                ['url' => '/about', 'title' => 'About'],
                ['url' => '/contact', 'title' => 'Contact'],
            ],
            'title' => 'My Page',
            'show_list' => true,
            'items' => ['Item 1', 'Item 2', 'Item 3'],
        ];

        $expected = "<html>\n<body>\n  <ul>\n    <li><a href=\"/\">Home</a></li>\n    <li><a href=\"/about\">About</a></li>\n    <li><a href=\"/contact\">Contact</a></li>\n  </ul>\n<h1>My Page</h1>\n<ul>\n  <li>Item 1</li>\n  <li>Item 2</li>\n  <li>Item 3</li>\n</ul>\n</body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, $data);
        $this->assertEquals($expected, $result);
    }

    // Test error when loader not configured
    public function testExtendsWithoutLoader(): void
    {
        $childTmpl = "{% extends 'base.html' %}\n{% block content %}Test{% endblock %}";

        $template = new Template();
        $result = $template->render($childTmpl, []);
        $this->assertStringContainsString('template loader not configured', $result);
    }

    // Test error when parent template not found
    public function testExtendsTemplateNotFound(): void
    {
        $loader = function (string $name): ?string {
            return null;
        };

        $childTmpl = "{% extends 'nonexistent.html' %}\n{% block content %}Test{% endblock %}";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertStringContainsString('template not found', $result);
    }

    // Test nested blocks (blocks within blocks) - inherits nested structure
    public function testNestedBlocks(): void
    {
        $templates = [
            'base.html' => "<div>\n{% block outer %}\n  <div class=\"outer\">\n  {% block inner %}Inner default{% endblock %}\n  </div>\n{% endblock %}\n</div>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        // Override inner block - parent's outer block includes the inner reference
        // so inner will be replaced with child's content
        $childTmpl = "{% extends 'base.html' %}\n\n{% block inner %}Custom inner content{% endblock %}";

        $expected = "<div>\n  <div class=\"outer\">\nCustom inner content\n  </div>\n</div>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test empty blocks
    public function testEmptyBlocks(): void
    {
        $templates = [
            'base.html' => "<html>\n<head>{% block head %}{% endblock %}</head>\n<body>{% block body %}Default body{% endblock %}</body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block head %}<title>Page</title>{% endblock %}\n\n{% block body %}{% endblock %}";

        $expected = "<html>\n<head><title>Page</title></head>\n<body></body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test documenting the current behavior: child blocks do NOT inherit indentation
    public function testBlockInheritanceNoIndentationPreservation(): void
    {
        $templates = [
            'base.html' => "<html>\n  <body>\n    <div>\n      {% block content %}Default{% endblock %}\n    </div>\n  </body>\n</html>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $childTmpl = "{% extends 'base.html' %}\n\n{% block content %}<h1>Title</h1>\n<p>Text</p>{% endblock %}";

        // Expected: child content is NOT indented (replaces block completely)
        $expected = "<html>\n  <body>\n    <div>\n<h1>Title</h1>\n<p>Text</p>\n    </div>\n  </body>\n</html>";

        $template = new Template($loader);
        $result = $template->render($childTmpl, []);
        $this->assertEquals($expected, $result);
    }

    // Test basic include functionality
    public function testIncludeBasic(): void
    {
        $templates = [
            'header.html' => '<header><h1>Site Header</h1></header>',
            'main.html' => "<div>{% include 'header.html' %}\n<main>Main content</main>\n</div>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $template = new Template($loader);
        $result = $template->render($templates['main.html'], []);

        $expected = "<div><header><h1>Site Header</h1></header>\n<main>Main content</main>\n</div>";
        $this->assertEquals($expected, $result);
    }

    // Test include with variables
    public function testIncludeWithVariables(): void
    {
        $templates = [
            'greeting.html' => '<p>Hello, {{ name }}!</p>',
            'main.html' => "<div>{% include 'greeting.html' %}</div>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $data = ['name' => 'Alice'];

        $template = new Template($loader);
        $result = $template->render($templates['main.html'], $data);

        $expected = '<div><p>Hello, Alice!</p></div>';
        $this->assertEquals($expected, $result);
    }

    // Test multiple includes
    public function testMultipleIncludes(): void
    {
        $templates = [
            'header.html' => "<header>Header</header>\n",
            'footer.html' => "<footer>Footer</footer>\n",
            'main.html' => "{% include 'header.html' %}\n<main>Content</main>\n{% include 'footer.html' %}\n",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $template = new Template($loader);
        $result = $template->render($templates['main.html'], []);

        $expected = "<header>Header</header>\n<main>Content</main>\n<footer>Footer</footer>\n";
        $this->assertEquals($expected, $result);
    }

    // Test include with control structures
    public function testIncludeWithControlStructures(): void
    {
        $templates = [
            'item.html' => "{% for item in items %}<li>{{ item }}</li>\n{% endfor %}",
            'main.html' => "<ul>\n{% include 'item.html' %}</ul>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $data = ['items' => ['Apple', 'Banana', 'Cherry']];

        $template = new Template($loader);
        $result = $template->render($templates['main.html'], $data);

        $expected = "<ul>\n<li>Apple</li>\n<li>Banana</li>\n<li>Cherry</li>\n</ul>";
        $this->assertEquals($expected, $result);
    }

    // Test include without loader
    public function testIncludeWithoutLoader(): void
    {
        $template = new Template();
        $result = $template->render("{% include 'header.html' %}", []);
        $this->assertStringContainsString('template loader not configured', $result);
    }

    // Test include template not found
    public function testIncludeTemplateNotFound(): void
    {
        $loader = function (string $name): ?string {
            return null;
        };

        $template = new Template($loader);
        $result = $template->render("{% include 'missing.html' %}", []);
        $this->assertStringContainsString('template not found', $result);
    }

    // Test nested includes
    public function testNestedIncludes(): void
    {
        $templates = [
            'deep.html' => '<span>Deep content</span>',
            'middle.html' => "<div>{% include 'deep.html' %}</div>",
            'top.html' => "<section>{% include 'middle.html' %}</section>",
        ];

        $loader = function (string $name) use ($templates): ?string {
            return $templates[$name] ?? null;
        };

        $template = new Template($loader);
        $result = $template->render($templates['top.html'], []);

        $expected = '<section><div><span>Deep content</span></div></section>';
        $this->assertEquals($expected, $result);
    }

    // Builtin filter tests

    public function testFilterLower(): void
    {
        $result = self::$template->render('{{ text|lower }}', ['text' => 'HELLO WORLD']);
        $this->assertEquals('hello world', $result);
    }

    public function testFilterUpper(): void
    {
        $result = self::$template->render('{{ text|upper }}', ['text' => 'hello world']);
        $this->assertEquals('HELLO WORLD', $result);
    }

    public function testFilterCapitalize(): void
    {
        $result = self::$template->render('{{ text|capitalize }}', ['text' => 'hello world']);
        $this->assertEquals('Hello world', $result);
    }

    public function testFilterTitle(): void
    {
        $result = self::$template->render('{{ text|title }}', ['text' => 'hello world']);
        $this->assertEquals('Hello World', $result);
    }

    public function testFilterTrim(): void
    {
        $result = self::$template->render('{{ text|trim }}', ['text' => '  hello  ']);
        $this->assertEquals('hello', $result);
    }

    public function testFilterTruncate(): void
    {
        $result = self::$template->render('{{ text|truncate(8) }}', ['text' => 'Hello World']);
        $this->assertEquals('Hello...', $result);
    }

    public function testFilterTruncateCustomEnd(): void
    {
        $result = self::$template->render('{{ text|truncate(10, ">>")|raw }}', ['text' => 'Hello World']);
        $this->assertEquals('Hello Wo>>', $result);
    }

    public function testFilterTruncateNoTruncation(): void
    {
        $result = self::$template->render('{{ text|truncate(20) }}', ['text' => 'Hello']);
        $this->assertEquals('Hello', $result);
    }

    public function testFilterReplace(): void
    {
        $result = self::$template->render('{{ text|replace("Hello", "Goodbye") }}', ['text' => 'Hello World']);
        $this->assertEquals('Goodbye World', $result);
    }

    public function testFilterReplaceWithCount(): void
    {
        $result = self::$template->render('{{ text|replace("a", "o", 2) }}', ['text' => 'banana']);
        $this->assertEquals('bonona', $result);
    }

    public function testFilterSplit(): void
    {
        $result = self::$template->render('{{ text|split(",")|join("|") }}', ['text' => '1,2,3']);
        $this->assertEquals('1|2|3', $result);
    }

    public function testFilterSplitChars(): void
    {
        $result = self::$template->render('{{ text|split|join("|") }}', ['text' => 'abc']);
        $this->assertEquals('a|b|c', $result);
    }

    public function testFilterURLEncode(): void
    {
        $result = self::$template->render('{{ text|urlencode }}', ['text' => 'hello world']);
        $this->assertEquals('hello+world', $result);
    }

    public function testFilterURLEncodeSpecialChars(): void
    {
        $result = self::$template->render('{{ text|urlencode }}', ['text' => 'hello&world=test']);
        $this->assertEquals('hello%26world%3Dtest', $result);
    }

    public function testFilterAbs(): void
    {
        $result = self::$template->render('{{ num|abs }}', ['num' => -42]);
        $this->assertEquals('42', $result);
    }

    public function testFilterAbsPositive(): void
    {
        $result = self::$template->render('{{ num|abs }}', ['num' => 42]);
        $this->assertEquals('42', $result);
    }

    public function testFilterRound(): void
    {
        $result = self::$template->render('{{ num|round }}', ['num' => 42.55]);
        $this->assertEquals('43', $result);
    }

    public function testFilterRoundWithPrecision(): void
    {
        $result = self::$template->render('{{ num|round(1, "floor") }}', ['num' => 42.55]);
        $this->assertEquals('42.5', $result);
    }

    public function testFilterRoundCeil(): void
    {
        $result = self::$template->render('{{ num|round(0, "ceil") }}', ['num' => 42.1]);
        $this->assertEquals('43', $result);
    }

    public function testFilterSprintf(): void
    {
        $result = self::$template->render('{{ num|sprintf("%.2f") }}', ['num' => 3.14159]);
        $this->assertEquals('3.14', $result);
    }

    public function testFilterFileSizeFormat(): void
    {
        $result = self::$template->render('{{ size|filesizeformat }}', ['size' => 13000]);
        $this->assertEquals('13.0 kB', $result);
    }

    public function testFilterFileSizeFormatBinary(): void
    {
        $result = self::$template->render('{{ size|filesizeformat(true) }}', ['size' => 1024]);
        $this->assertEquals('1.0 KiB', $result);
    }

    public function testFilterFileSizeFormatLarge(): void
    {
        $result = self::$template->render('{{ size|filesizeformat }}', ['size' => 1500000]);
        $this->assertEquals('1.5 MB', $result);
    }

    public function testFilterLength(): void
    {
        $result = self::$template->render('{{ items|length }}', ['items' => [1, 2, 3]]);
        $this->assertEquals('3', $result);
    }

    public function testFilterCount(): void
    {
        $result = self::$template->render('{{ items|count }}', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals('4', $result);
    }

    public function testFilterLengthString(): void
    {
        $result = self::$template->render('{{ text|length }}', ['text' => 'hello']);
        $this->assertEquals('5', $result);
    }

    public function testFilterFirst(): void
    {
        $result = self::$template->render('{{ items|first }}', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals('1', $result);
    }

    public function testFilterFirstMultiple(): void
    {
        $result = self::$template->render('{{ items|first(2)|join(",") }}', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals('1,2', $result);
    }

    public function testFilterLast(): void
    {
        $result = self::$template->render('{{ items|last }}', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals('4', $result);
    }

    public function testFilterLastMultiple(): void
    {
        $result = self::$template->render('{{ items|last(2)|join(",") }}', ['items' => [1, 2, 3, 4]]);
        $this->assertEquals('3,4', $result);
    }

    public function testFilterJoin(): void
    {
        $result = self::$template->render('{{ items|join("|") }}', ['items' => [1, 2, 3]]);
        $this->assertEquals('1|2|3', $result);
    }

    public function testFilterJoinNoSeparator(): void
    {
        $result = self::$template->render('{{ items|join }}', ['items' => [1, 2, 3]]);
        $this->assertEquals('123', $result);
    }

    public function testFilterJoinAttribute(): void
    {
        $users = [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ];
        $result = self::$template->render('{{ users|join(", ", "name") }}', ['users' => $users]);
        $this->assertEquals('Alice, Bob', $result);
    }

    public function testFilterReverse(): void
    {
        $result = self::$template->render('{{ items|reverse|join(",") }}', ['items' => [1, 2, 3]]);
        $this->assertEquals('3,2,1', $result);
    }

    public function testFilterReverseString(): void
    {
        $result = self::$template->render('{{ text|reverse }}', ['text' => 'hello']);
        $this->assertEquals('olleh', $result);
    }

    public function testFilterSum(): void
    {
        $result = self::$template->render('{{ items|sum }}', ['items' => [1, 2, 3]]);
        $this->assertEquals('6', $result);
    }

    public function testFilterSumAttribute(): void
    {
        $items = [
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ];
        $result = self::$template->render('{{ items|sum("price") }}', ['items' => $items]);
        $this->assertEquals('60', $result);
    }

    public function testFilterDefault(): void
    {
        // Use a nil value instead of missing to test default filter
        $result = self::$template->render('{{ value|default("N/A") }}', ['value' => null]);
        $this->assertEquals('N/A', $result);
    }

    public function testFilterDefaultWithValue(): void
    {
        $result = self::$template->render('{{ value|default("N/A") }}', ['value' => 'exists']);
        $this->assertEquals('exists', $result);
    }

    public function testFilterDefaultBoolean(): void
    {
        $result = self::$template->render('{{ value|default("empty", true) }}', ['value' => '']);
        $this->assertEquals('empty', $result);
    }

    public function testFilterDefaultBooleanZero(): void
    {
        $result = self::$template->render('{{ value|default("zero", true) }}', ['value' => 0]);
        $this->assertEquals('zero', $result);
    }

    public function testFilterAttr(): void
    {
        $data = [
            'user' => [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ],
        ];
        $result = self::$template->render('{{ user|attr("email") }}', $data);
        $this->assertEquals('alice@example.com', $result);
    }

    public function testFilterAttrMissing(): void
    {
        $data = [
            'user' => [
                'name' => 'Alice',
            ],
        ];
        $result = self::$template->render('{{ user|attr("missing") }}', $data);
        $this->assertEquals('', $result);
    }

    public function testFilterDebug(): void
    {
        $data = [
            'user' => [
                'name' => 'Alice',
                'age' => 30,
            ],
        ];
        $result = self::$template->render('{{ user|debug|raw }}', $data);
        // Should contain JSON formatted output
        $this->assertStringContainsString('"name"', $result);
        $this->assertStringContainsString('"Alice"', $result);
    }

    public function testFilterDebugAlias(): void
    {
        $result = self::$template->render('{{ value|d }}', ['value' => 42]);
        $this->assertEquals('42', $result);
    }

    public function testFilterRaw(): void
    {
        $result = self::$template->render('{{ html|raw }}', ['html' => '<strong>Bold</strong>']);
        $this->assertEquals('<strong>Bold</strong>', $result);
    }

    public function testFilterRawWithoutEscaping(): void
    {
        $result = self::$template->render('{{ html }}', ['html' => '<strong>Bold</strong>']);
        $this->assertEquals('&lt;strong&gt;Bold&lt;/strong&gt;', $result);
    }

    // Filter chaining tests

    public function testFilterChaining(): void
    {
        $result = self::$template->render('{{ text|trim|upper|replace("WORLD", "FRIEND") }}', ['text' => '  hello world  ']);
        $this->assertEquals('HELLO FRIEND', $result);
    }

    public function testFilterChainingArrays(): void
    {
        $result = self::$template->render('{{ items|first(3)|reverse|join(", ") }}', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertEquals('3, 2, 1', $result);
    }

    public function testFilterChainingComplex(): void
    {
        $users = [
            ['name' => 'alice'],
            ['name' => 'bob'],
            ['name' => 'charlie'],
        ];
        $result = self::$template->render('{{ users|join(", ", "name")|upper }}', ['users' => $users]);
        $this->assertEquals('ALICE, BOB, CHARLIE', $result);
    }

    // Edge case tests

    public function testFilterEmptyArray(): void
    {
        $result = self::$template->render('{{ items|length }}', ['items' => []]);
        $this->assertEquals('0', $result);
    }

    public function testFilterEmptyString(): void
    {
        $result = self::$template->render('{{ text|upper }}', ['text' => '']);
        $this->assertEquals('', $result);
    }

    public function testFilterNilValue(): void
    {
        $result = self::$template->render('{{ value|default("nil") }}', ['value' => null]);
        $this->assertEquals('nil', $result);
    }

    public function testFilterNumericString(): void
    {
        $result = self::$template->render('{{ num|abs }}', ['num' => '-42']);
        $this->assertEquals('42', $result);
    }
}

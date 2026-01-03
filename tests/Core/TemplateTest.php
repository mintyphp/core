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
        $template = new Template('none');
        $this->assertEquals('<script>alert("xss")</script>', $template->render('{{ a }}', ['a' => '<script>alert("xss")</script>'], []));
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
        $expected = "<ul>\n\n    <li>apple</li>\n\n    <li>banana</li>\n\n    <li>cherry</li>\n\n</ul>";
        $this->assertEquals($expected, self::$template->render($template, ['items' => ['apple', 'banana', 'cherry']]));
    }

    public function testMultilineForLoopWithIndentation(): void
    {
        $template = "<div>\n    <ul>\n    {% for user in users %}\n        <li>{{ user }}</li>\n    {% endfor %}\n    </ul>\n</div>";
        $expected = "<div>\n    <ul>\n    \n        <li>Alice</li>\n    \n        <li>Bob</li>\n    \n    </ul>\n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['users' => ['Alice', 'Bob']]));
    }

    public function testMultilineIfWithWhitespace(): void
    {
        $template = "<div>\n    {% if active %}\n        <span>Active</span>\n    {% endif %}\n</div>";
        $expected = "<div>\n    \n        <span>Active</span>\n    \n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['active' => true]));
    }

    public function testMultilineIfElseWithWhitespace(): void
    {
        $template = "<div>\n    {% if active %}\n        <span>Active</span>\n    {% else %}\n        <span>Inactive</span>\n    {% endif %}\n</div>";
        $expected = "<div>\n    \n        <span>Inactive</span>\n    \n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['active' => false]));
    }

    public function testMultilineNestedForLoops(): void
    {
        $template = "<table>\n{% for row in rows %}\n    <tr>\n    {% for cell in row %}\n        <td>{{ cell }}</td>\n    {% endfor %}\n    </tr>\n{% endfor %}\n</table>";
        $expected = "<table>\n\n    <tr>\n    \n        <td>1</td>\n    \n        <td>2</td>\n    \n    </tr>\n\n    <tr>\n    \n        <td>3</td>\n    \n        <td>4</td>\n    \n    </tr>\n\n</table>";
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

        $expected = "<!DOCTYPE html>\n<html>\n<head>\n    <title>My Page</title>\n</head>\n<body>\n    <ul id=\"navigation\">\n    \n        <li><a href=\"/home\">Home</a></li>\n    \n        <li><a href=\"/about\">About</a></li>\n    \n    </ul>\n    <h1>Welcome</h1>\n</body>\n</html>";

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
        $expected = "<ul>\n\n</ul>";
        $this->assertEquals($expected, self::$template->render($template, ['items' => []]));
    }

    public function testMultilineIfWithFalseCondition(): void
    {
        $template = "<div>\n    Content before\n    {% if show %}\n        This should not appear\n    {% endif %}\n    Content after\n</div>";
        $expected = "<div>\n    Content before\n    \n    Content after\n</div>";
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
        $expected = "<p>\n    Text content\n    Hello\n    \n        <strong>Important</strong>\n    \n    More text\n</p>";
        $this->assertEquals($expected, self::$template->render($template, ['text' => 'Hello', 'show' => true, 'emphasis' => 'Important']));
    }

    public function testMultilineHtmlListWithData(): void
    {
        $template = "<h1>Members</h1>\n<ul>\n{% for user in users %}\n  <li>{{ user.username }}</li>\n{% endfor %}\n</ul>";
        $expected = "<h1>Members</h1>\n<ul>\n\n  <li>alice</li>\n\n  <li>bob</li>\n\n  <li>charlie</li>\n\n</ul>";
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
        $expected = "<div>\n\n    <div class=\"outer\">\n    \n        <div class=\"inner\">Content</div>\n    \n    </div>\n\n</div>";
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
        $expected = "<div>\n    \n    Data\n    \n</div>";
        $this->assertEquals($expected, self::$template->render($template, ['content' => 'Data']));
    }

    public function testMultilineForLoopWithComplexData(): void
    {
        $template = "<dl>\n{% for item in items %}\n  <dt>{{ item.key }}</dt>\n  <dd>{{ item.value }}</dd>\n{% endfor %}\n</dl>";
        $expected = "<dl>\n\n  <dt>Name</dt>\n  <dd>John</dd>\n\n  <dt>Age</dt>\n  <dd>30</dd>\n\n</dl>";
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
        $expected = "Line 1\n\nLine 2";
        $this->assertEquals($expected, self::$template->render($template, []));
    }

    public function testCommentWithControlStructures(): void
    {
        $this->assertEquals("result", self::$template->render('{# comment #}{% if true %}result{% endif %}{# another #}', []));
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
        $expected = "\n<div>\n    \n    Data\n</div>\n";
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
}

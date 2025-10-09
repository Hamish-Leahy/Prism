<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\CssParserService;
use Monolog\Logger;

class CssParserServiceTest extends TestCase
{
    private CssParserService $cssParser;
    private Logger $logger;

    protected function setUp(): void
    {
        $config = [
            'enabled' => true,
            'debug' => false,
            'parsing' => [
                'remove_comments' => true,
                'normalize_whitespace' => true,
                'minify' => false
            ],
            'computation' => [
                'apply_inheritance' => true,
                'apply_defaults' => true,
                'resolve_variables' => true
            ]
        ];
        
        $this->logger = new Logger('test');
        $this->cssParser = new CssParserService($config, $this->logger);
    }

    public function testParseBasicCss()
    {
        $css = '
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            .container {
                width: 100%;
                max-width: 1200px;
                margin: 0 auto;
            }
            #header {
                background-color: #333;
                color: white;
                padding: 20px;
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('media_queries', $result);
        $this->assertArrayHasKey('keyframes', $result);
        $this->assertArrayHasKey('variables', $result);
        $this->assertArrayHasKey('stats', $result);

        $this->assertCount(3, $result['rules']);
        
        // Test first rule (body)
        $bodyRule = $result['rules'][0];
        $this->assertArrayHasKey('selectors', $bodyRule);
        $this->assertArrayHasKey('declarations', $bodyRule);
        $this->assertArrayHasKey('specificity', $bodyRule);
        
        $this->assertEquals('body', $bodyRule['selectors'][0]['raw']);
        $this->assertEquals('element', $bodyRule['selectors'][0]['type']);
        
        $this->assertCount(3, $bodyRule['declarations']);
        $this->assertEquals('margin', $bodyRule['declarations'][0]['property']);
        $this->assertEquals('0', $bodyRule['declarations'][0]['value']);
    }

    public function testParseMediaQueries()
    {
        $css = '
            .container {
                width: 100%;
            }
            @media (max-width: 768px) {
                .container {
                    width: 90%;
                    padding: 10px;
                }
            }
            @media (min-width: 1200px) {
                .container {
                    width: 1200px;
                }
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $this->assertCount(2, $result['media_queries']);
        
        $mobileQuery = $result['media_queries'][0];
        $this->assertStringContains('max-width: 768px', $mobileQuery['query']);
        $this->assertEquals('screen', $mobileQuery['type']);
        $this->assertCount(1, $mobileQuery['rules']);
    }

    public function testParseKeyframes()
    {
        $css = '
            @keyframes fadeIn {
                0% {
                    opacity: 0;
                    transform: translateY(20px);
                }
                100% {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .fade-in {
                animation: fadeIn 0.5s ease-in-out;
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $this->assertCount(1, $result['keyframes']);
        
        $keyframe = $result['keyframes'][0];
        $this->assertEquals('fadeIn', $keyframe['name']);
        $this->assertCount(2, $keyframe['steps']);
        
        $firstStep = $keyframe['steps'][0];
        $this->assertContains('0%', $firstStep['selectors']);
        $this->assertCount(2, $firstStep['declarations']);
    }

    public function testParseCssVariables()
    {
        $css = '
            :root {
                --primary-color: #007bff;
                --secondary-color: #6c757d;
                --font-size-base: 16px;
                --spacing-unit: 8px;
            }
            .button {
                background-color: var(--primary-color);
                color: white;
                padding: var(--spacing-unit) calc(var(--spacing-unit) * 2);
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $this->assertCount(4, $result['variables']);
        
        $primaryColor = $result['variables'][0];
        $this->assertEquals('primary-color', $primaryColor['name']);
        $this->assertEquals('#007bff', $primaryColor['value']);
        $this->assertEquals('root', $primaryColor['scope']);
    }

    public function testParseSelectors()
    {
        $css = '
            .class-selector { color: red; }
            #id-selector { color: blue; }
            [data-attr="value"] { color: green; }
            .parent .child { color: purple; }
            .parent > .direct-child { color: orange; }
            .sibling + .adjacent { color: yellow; }
            .sibling ~ .general { color: pink; }
            .pseudo:hover { color: cyan; }
            .pseudo::before { content: ""; }
        ';

        $result = $this->cssParser->parseCss($css);

        $this->assertCount(9, $result['rules']);
        
        // Test class selector
        $classRule = $result['rules'][0];
        $this->assertEquals('class', $classRule['selectors'][0]['type']);
        $this->assertEquals('.class-selector', $classRule['selectors'][0]['raw']);
        
        // Test ID selector
        $idRule = $result['rules'][1];
        $this->assertEquals('id', $idRule['selectors'][0]['type']);
        $this->assertEquals('#id-selector', $idRule['selectors'][0]['raw']);
        
        // Test attribute selector
        $attrRule = $result['rules'][2];
        $this->assertEquals('attribute', $attrRule['selectors'][0]['type']);
        $this->assertCount(1, $attrRule['selectors'][0]['attributes']);
        $this->assertEquals('data-attr', $attrRule['selectors'][0]['attributes'][0]['name']);
        $this->assertEquals('value', $attrRule['selectors'][0]['attributes'][0]['value']);
    }

    public function testParseShorthandProperties()
    {
        $css = '
            .shorthand {
                margin: 10px 20px 30px 40px;
                padding: 5px 10px;
                border: 2px solid #ccc;
                background: #f0f0f0 url("image.jpg") no-repeat center;
                font: 14px/1.5 Arial, sans-serif;
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $rule = $result['rules'][0];
        $this->assertCount(5, $rule['declarations']);
        
        // Test margin shorthand
        $marginDecl = $rule['declarations'][0];
        $this->assertEquals('margin', $marginDecl['property']);
        $this->assertTrue($marginDecl['shorthand']);
        
        // Test font shorthand
        $fontDecl = $rule['declarations'][4];
        $this->assertEquals('font', $fontDecl['property']);
        $this->assertTrue($fontDecl['shorthand']);
    }

    public function testParseImportantDeclarations()
    {
        $css = '
            .important {
                color: red !important;
                background: blue;
                font-size: 16px !important;
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $rule = $result['rules'][0];
        $this->assertCount(3, $rule['declarations']);
        
        $this->assertTrue($rule['declarations'][0]['important']); // color
        $this->assertFalse($rule['declarations'][1]['important']); // background
        $this->assertTrue($rule['declarations'][2]['important']); // font-size
    }

    public function testParseImports()
    {
        $css = '
            @import url("reset.css");
            @import "normalize.css" screen;
            @import url("print.css") print;
        ';

        $result = $this->cssParser->parseCss($css, 'https://example.com/styles/');

        $this->assertCount(3, $result['imports']);
        
        $firstImport = $result['imports'][0];
        $this->assertEquals('reset.css', $firstImport['url']);
        $this->assertEquals('https://example.com/styles/reset.css', $firstImport['absolute_url']);
        $this->assertEquals('css', $firstImport['type']);
    }

    public function testComputeStyles()
    {
        $css = '
            .container {
                width: 100%;
                background-color: #f0f0f0;
                padding: 20px;
            }
            .highlighted {
                background-color: yellow !important;
                font-weight: bold;
            }
        ';

        $this->cssParser->parseCss($css);
        
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => ['container', 'highlighted'],
            'attributes' => []
        ];

        $styles = $this->cssParser->computeStyles([], $element);
        
        $this->assertIsArray($styles);
        // Note: This is a simplified test as the actual implementation
        // would require more complex element matching logic
    }

    public function testCalculateStats()
    {
        $css = '
            body { margin: 0; padding: 0; }
            .container { width: 100%; }
            #header { background: blue; }
            @media (max-width: 768px) {
                .container { width: 90%; }
            }
        ';

        $result = $this->cssParser->parseCss($css);

        $stats = $result['stats'];
        $this->assertArrayHasKey('total_rules', $stats);
        $this->assertArrayHasKey('total_selectors', $stats);
        $this->assertArrayHasKey('total_declarations', $stats);
        $this->assertArrayHasKey('property_counts', $stats);
        $this->assertArrayHasKey('selector_types', $stats);
        $this->assertArrayHasKey('specificity_distribution', $stats);

        $this->assertEquals(3, $stats['total_rules']);
        $this->assertEquals(3, $stats['total_selectors']);
        $this->assertEquals(5, $stats['total_declarations']);
    }

    public function testSetAndGetVariables()
    {
        $variables = [
            'primary-color' => '#007bff',
            'secondary-color' => '#6c757d'
        ];

        $this->cssParser->setVariables($variables);
        $retrieved = $this->cssParser->getVariables();

        $this->assertEquals($variables, $retrieved);
    }

    public function testClearStyles()
    {
        $css = 'body { margin: 0; }';
        $this->cssParser->parseCss($css);
        
        $this->cssParser->clearStyles();
        
        $this->assertEmpty($this->cssParser->getComputedStyles());
        $this->assertEmpty($this->cssParser->getVariables());
    }

    public function testInvalidCssHandling()
    {
        $invalidCss = '
            .invalid {
                invalid-property: invalid-value;
                color: red;
                { unmatched-brace
            }
        ';

        // Should not throw exception
        $result = $this->cssParser->parseCss($invalidCss);
        
        $this->assertIsArray($result);
        // Should still parse valid parts
        $this->assertArrayHasKey('rules', $result);
    }
}

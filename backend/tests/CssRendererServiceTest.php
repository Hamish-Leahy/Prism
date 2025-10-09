<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\CssRendererService;
use Monolog\Logger;

class CssRendererServiceTest extends TestCase
{
    private CssRendererService $cssRenderer;
    private Logger $logger;

    protected function setUp(): void
    {
        $config = [
            'enabled' => true,
            'debug' => false,
            'computation' => [
                'apply_inheritance' => true,
                'apply_defaults' => true,
                'resolve_variables' => true,
                'resolve_relative_units' => true
            ],
            'properties' => [
                'shorthand_expansion' => true,
                'supported_properties' => [
                    'display', 'position', 'width', 'height', 'margin', 'padding',
                    'color', 'background-color', 'font-size', 'font-family'
                ]
            ]
        ];
        
        $this->logger = new Logger('test');
        $this->cssRenderer = new CssRendererService($config, $this->logger);
    }

    public function testRenderElement()
    {
        $element = [
            'tagName' => 'div',
            'id' => 'test-element',
            'classList' => ['container', 'highlighted'],
            'attributes' => [
                'data-test' => 'value'
            ]
        ];

        $computedStyles = [
            'display' => ['value' => 'block', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'width' => ['value' => '100%', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'height' => ['value' => '200px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'background-color' => ['value' => '#f0f0f0', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'color' => ['value' => '#333', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'margin' => ['value' => '10px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'padding' => ['value' => '20px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $this->assertIsArray($rendered);
        $this->assertArrayHasKey('element', $rendered);
        $this->assertArrayHasKey('layout', $rendered);
        $this->assertArrayHasKey('paint', $rendered);
        $this->assertArrayHasKey('composite', $rendered);
        $this->assertArrayHasKey('visual', $rendered);
        $this->assertArrayHasKey('bounds', $rendered);
        $this->assertArrayHasKey('z_index', $rendered);
        $this->assertArrayHasKey('stacking_context', $rendered);

        // Test layout properties
        $layout = $rendered['layout'];
        $this->assertEquals('block', $layout['display']);
        $this->assertArrayHasKey('box_model', $layout);
        $this->assertArrayHasKey('dimensions', $layout);
        $this->assertArrayHasKey('flow', $layout);

        // Test paint properties
        $paint = $rendered['paint'];
        $this->assertArrayHasKey('background', $paint);
        $this->assertArrayHasKey('border', $paint);
        $this->assertArrayHasKey('text', $paint);
        $this->assertArrayHasKey('shadows', $paint);
        $this->assertArrayHasKey('filters', $paint);

        // Test bounds calculation
        $bounds = $rendered['bounds'];
        $this->assertArrayHasKey('x', $bounds);
        $this->assertArrayHasKey('y', $bounds);
        $this->assertArrayHasKey('width', $bounds);
        $this->assertArrayHasKey('height', $bounds);
        $this->assertArrayHasKey('total_width', $bounds);
        $this->assertArrayHasKey('total_height', $bounds);
    }

    public function testCalculateLayout()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'display' => ['value' => 'flex', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'position' => ['value' => 'relative', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'float' => ['value' => 'none', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'width' => ['value' => '300px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'height' => ['value' => '200px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'margin' => ['value' => '10px 20px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'padding' => ['value' => '15px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'border' => ['value' => '1px solid #ccc', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $layout = $rendered['layout'];
        $this->assertEquals('flex', $layout['display']);
        $this->assertEquals('relative', $layout['position']);
        $this->assertEquals('none', $layout['float']);

        // Test box model
        $boxModel = $layout['box_model'];
        $this->assertArrayHasKey('margin', $boxModel);
        $this->assertArrayHasKey('padding', $boxModel);
        $this->assertArrayHasKey('border', $boxModel);

        // Test dimensions
        $dimensions = $layout['dimensions'];
        $this->assertArrayHasKey('width', $dimensions);
        $this->assertArrayHasKey('height', $dimensions);

        // Test flow
        $flow = $layout['flow'];
        $this->assertTrue($flow['is_block']);
        $this->assertTrue($flow['is_positioned']);
        $this->assertFalse($flow['is_floated']);
    }

    public function testCalculatePaint()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'background-color' => ['value' => '#ff0000', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'color' => ['value' => '#ffffff', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'font-family' => ['value' => 'Arial, sans-serif', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'font-size' => ['value' => '16px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'box-shadow' => ['value' => '0 2px 4px rgba(0,0,0,0.1)', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'opacity' => ['value' => '0.8', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $paint = $rendered['paint'];
        
        // Test background
        $background = $paint['background'];
        $this->assertArrayHasKey('color', $background);
        $this->assertEquals('#ff0000', $background['color']['hex']);

        // Test text
        $text = $paint['text'];
        $this->assertArrayHasKey('color', $text);
        $this->assertEquals('#ffffff', $text['color']['hex']);
        $this->assertEquals('Arial, sans-serif', $text['font_family']);

        // Test shadows
        $shadows = $paint['shadows'];
        $this->assertArrayHasKey('box_shadow', $shadows);
        $this->assertArrayHasKey('text_shadow', $shadows);

        // Test opacity
        $this->assertEquals(0.8, $paint['opacity']);
    }

    public function testCalculateComposite()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'z-index' => ['value' => '10', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'transform' => ['value' => 'translateX(10px) rotate(45deg)', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'opacity' => ['value' => '0.9', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'position' => ['value' => 'absolute', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $composite = $rendered['composite'];
        $this->assertEquals(10, $composite['z_index']);
        $this->assertEquals(0.9, $composite['opacity']);
        $this->assertArrayHasKey('transform', $composite);
        $this->assertTrue($composite['stacking_context']);
    }

    public function testParseColor()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'color' => ['value' => 'rgb(255, 0, 0)', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'background-color' => ['value' => '#00ff00', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $paint = $rendered['paint'];
        
        // Test RGB color parsing
        $textColor = $paint['text']['color'];
        $this->assertEquals('rgb', $textColor['type']);
        $this->assertEquals(255, $textColor['r']);
        $this->assertEquals(0, $textColor['g']);
        $this->assertEquals(0, $textColor['b']);
        $this->assertEquals('#ff0000', $textColor['hex']);

        // Test hex color parsing
        $bgColor = $paint['background']['color'];
        $this->assertEquals('hex', $bgColor['type']);
        $this->assertEquals('#00ff00', $bgColor['hex']);
    }

    public function testParseLength()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'width' => ['value' => '300px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'height' => ['value' => '200px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'margin' => ['value' => '10px 20px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $layout = $rendered['layout'];
        $dimensions = $layout['dimensions'];
        
        // Test width parsing
        $width = $dimensions['width'];
        $this->assertEquals(300, $width['value']);
        $this->assertEquals('px', $width['unit']);
        $this->assertEquals('length', $width['type']);

        // Test height parsing
        $height = $dimensions['height'];
        $this->assertEquals(200, $height['value']);
        $this->assertEquals('px', $height['unit']);
        $this->assertEquals('length', $height['type']);
    }

    public function testParseBoxProperty()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'margin' => ['value' => '10px 20px 30px 40px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
            'padding' => ['value' => '15px', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $rendered = $this->cssRenderer->renderElement($element, $computedStyles);

        $layout = $rendered['layout'];
        $boxModel = $layout['box_model'];
        
        // Test 4-value margin
        $margin = $boxModel['margin'];
        $this->assertEquals(10, $margin['top']['value']);
        $this->assertEquals(20, $margin['right']['value']);
        $this->assertEquals(30, $margin['bottom']['value']);
        $this->assertEquals(40, $margin['left']['value']);

        // Test single-value padding
        $padding = $boxModel['padding'];
        $this->assertEquals(15, $padding['top']['value']);
        $this->assertEquals(15, $padding['right']['value']);
        $this->assertEquals(15, $padding['bottom']['value']);
        $this->assertEquals(15, $padding['left']['value']);
    }

    public function testRenderPage()
    {
        $elements = [
            [
                'tagName' => 'div',
                'id' => 'container',
                'classList' => ['main'],
                'attributes' => []
            ],
            [
                'tagName' => 'p',
                'id' => '',
                'classList' => ['text'],
                'attributes' => []
            ]
        ];

        $styles = [
            'rules' => [
                [
                    'selectors' => [['raw' => '.main', 'type' => 'class']],
                    'declarations' => [
                        ['property' => 'width', 'value' => '100%', 'important' => false]
                    ],
                    'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]
                ]
            ]
        ];

        $rendered = $this->cssRenderer->renderPage($elements, $styles);

        $this->assertIsArray($rendered);
        $this->assertArrayHasKey('elements', $rendered);
        $this->assertArrayHasKey('stacking_contexts', $rendered);
        $this->assertArrayHasKey('viewport', $rendered);

        $this->assertCount(2, $rendered['elements']);
        
        $viewport = $rendered['viewport'];
        $this->assertArrayHasKey('x', $viewport);
        $this->assertArrayHasKey('y', $viewport);
        $this->assertArrayHasKey('width', $viewport);
        $this->assertArrayHasKey('height', $viewport);
    }

    public function testGetRenderedStyles()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'color' => ['value' => 'red', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $this->cssRenderer->renderElement($element, $computedStyles);
        
        $renderedStyles = $this->cssRenderer->getRenderedStyles();
        $this->assertIsArray($renderedStyles);
    }

    public function testClearCache()
    {
        $element = [
            'tagName' => 'div',
            'id' => '',
            'classList' => [],
            'attributes' => []
        ];

        $computedStyles = [
            'color' => ['value' => 'red', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
        ];

        $this->cssRenderer->renderElement($element, $computedStyles);
        $this->cssRenderer->clearCache();
        
        $renderedStyles = $this->cssRenderer->getRenderedStyles();
        $this->assertEmpty($renderedStyles);
    }

    public function testErrorHandling()
    {
        $invalidElement = [
            'tagName' => '',
            'id' => null,
            'classList' => null,
            'attributes' => null
        ];

        $invalidStyles = [
            'invalid' => 'value'
        ];

        // Should not throw exception
        $rendered = $this->cssRenderer->renderElement($invalidElement, $invalidStyles);
        
        $this->assertIsArray($rendered);
        $this->assertArrayHasKey('element', $rendered);
    }
}

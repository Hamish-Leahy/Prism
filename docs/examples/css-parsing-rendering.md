# CSS Parsing and Rendering

This document provides examples and explanations for the CSS parsing and rendering capabilities in the Prism Browser engine.

## Overview

The Prism Browser includes comprehensive CSS parsing and rendering capabilities through two main services:

- **CssParserService**: Parses CSS content and extracts rules, media queries, keyframes, and variables
- **CssRendererService**: Renders elements with computed styles and generates visual representations

## CSS Parser Service

### Basic Usage

```php
use Prism\Backend\Services\CssParserService;
use Monolog\Logger;

$config = require 'config/css_parser.php';
$logger = new Logger('css-parser');
$cssParser = new CssParserService($config, $logger);

// Parse CSS content
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
';

$result = $cssParser->parseCss($css);
```

### Parsed Data Structure

The parser returns a comprehensive data structure:

```php
[
    'rules' => [
        [
            'selectors' => [
                [
                    'raw' => 'body',
                    'type' => 'element',
                    'specificity' => ['ids' => 0, 'classes' => 0, 'elements' => 1],
                    'pseudo_classes' => [],
                    'pseudo_elements' => [],
                    'attributes' => [],
                    'combinators' => []
                ]
            ],
            'declarations' => [
                [
                    'property' => 'margin',
                    'value' => '0',
                    'important' => false,
                    'type' => 'length',
                    'shorthand' => false
                ]
            ],
            'specificity' => ['ids' => 0, 'classes' => 0, 'elements' => 1],
            'source' => 'inline'
        ]
    ],
    'media_queries' => [],
    'keyframes' => [],
    'variables' => [],
    'imports' => [],
    'stats' => [
        'total_rules' => 2,
        'total_selectors' => 2,
        'total_declarations' => 5,
        'property_counts' => ['margin' => 1, 'padding' => 1, 'font-family' => 1],
        'selector_types' => ['element' => 2],
        'specificity_distribution' => ['low' => 2, 'medium' => 0, 'high' => 0]
    ]
]
```

### Supported CSS Features

#### Selectors
- Element selectors (`div`, `p`, `span`)
- Class selectors (`.class-name`)
- ID selectors (`#element-id`)
- Attribute selectors (`[data-attr="value"]`)
- Pseudo-classes (`:hover`, `:focus`, `:nth-child()`)
- Pseudo-elements (`::before`, `::after`)
- Combinators (descendant, child `>`, adjacent sibling `+`, general sibling `~`)

#### Properties
- Layout properties (`display`, `position`, `float`, `width`, `height`)
- Box model (`margin`, `padding`, `border`)
- Typography (`font-family`, `font-size`, `color`, `text-align`)
- Backgrounds (`background-color`, `background-image`)
- Transforms (`transform`, `transform-origin`)
- Animations (`animation`, `transition`)
- Flexbox (`flex`, `justify-content`, `align-items`)
- Grid (`grid`, `grid-template-columns`)

#### Media Queries
```css
@media (max-width: 768px) {
    .container {
        width: 90%;
        padding: 10px;
    }
}

@media (min-width: 1200px) and (orientation: landscape) {
    .sidebar {
        width: 300px;
    }
}
```

#### Keyframes
```css
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
```

#### CSS Variables
```css
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
```

## CSS Renderer Service

### Basic Usage

```php
use Prism\Backend\Services\CssRendererService;
use Monolog\Logger;

$config = require 'config/css_parser.php';
$logger = new Logger('css-renderer');
$cssRenderer = new CssRendererService($config, $logger);

// Render an element
$element = [
    'tagName' => 'div',
    'id' => 'container',
    'classList' => ['main', 'highlighted'],
    'attributes' => ['data-test' => 'value']
];

$computedStyles = [
    'display' => ['value' => 'block', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
    'width' => ['value' => '100%', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]],
    'background-color' => ['value' => '#f0f0f0', 'important' => false, 'specificity' => ['ids' => 0, 'classes' => 1, 'elements' => 0]]
];

$rendered = $cssRenderer->renderElement($element, $computedStyles);
```

### Rendered Element Structure

```php
[
    'element' => [...], // Original element data
    'layout' => [
        'display' => 'block',
        'position' => 'static',
        'float' => 'none',
        'box_model' => [
            'box_sizing' => 'content-box',
            'margin' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'padding' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'border' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'content_width' => ['value' => 100, 'unit' => '%', 'type' => 'length'],
            'content_height' => ['value' => 0, 'unit' => 'auto', 'type' => 'keyword']
        ],
        'dimensions' => [
            'width' => ['value' => 100, 'unit' => '%', 'type' => 'length'],
            'height' => ['value' => 0, 'unit' => 'auto', 'type' => 'keyword'],
            'min_width' => ['value' => 0, 'unit' => 'px', 'type' => 'length'],
            'max_width' => ['value' => 0, 'unit' => 'none', 'type' => 'keyword'],
            'aspect_ratio' => null
        ],
        'flow' => [
            'is_block' => true,
            'is_inline' => false,
            'is_positioned' => false,
            'is_floated' => false,
            'creates_stacking_context' => false,
            'creates_block_formatting_context' => true
        ],
        'containment' => [
            'layout' => false,
            'paint' => false,
            'size' => false,
            'style' => false,
            'strict' => false,
            'content' => false
        ]
    ],
    'paint' => [
        'background' => [
            'color' => ['type' => 'hex', 'value' => '#f0f0f0', 'hex' => '#f0f0f0'],
            'image' => 'none',
            'repeat' => 'repeat',
            'position' => '0% 0%',
            'size' => 'auto',
            'attachment' => 'scroll'
        ],
        'border' => [
            'width' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'style' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'color' => ['top' => [...], 'right' => [...], 'bottom' => [...], 'left' => [...]],
            'radius' => ['top_left' => [...], 'top_right' => [...], 'bottom_right' => [...], 'bottom_left' => [...]],
            'image' => 'none'
        ],
        'text' => [
            'color' => ['type' => 'named', 'value' => 'inherit', 'hex' => '#000000'],
            'font_family' => 'inherit',
            'font_size' => ['value' => 0, 'unit' => 'inherit', 'type' => 'keyword'],
            'font_weight' => 'normal',
            'font_style' => 'normal',
            'line_height' => ['type' => 'normal', 'value' => 1.2],
            'text_align' => 'left',
            'text_decoration' => 'none',
            'text_transform' => 'none',
            'letter_spacing' => ['value' => 0, 'unit' => 'normal', 'type' => 'keyword'],
            'word_spacing' => ['value' => 0, 'unit' => 'normal', 'type' => 'keyword']
        ],
        'shadows' => [
            'box_shadow' => [],
            'text_shadow' => []
        ],
        'filters' => [
            'filter' => [],
            'backdrop_filter' => [],
            'mix_blend_mode' => 'normal'
        ],
        'opacity' => 1.0,
        'visibility' => 'visible'
    ],
    'composite' => [
        'z_index' => null,
        'transform' => [],
        'opacity' => 1.0,
        'stacking_context' => false,
        'will_change' => 'auto',
        'contain' => 'none'
    ],
    'visual' => [
        'background' => [...],
        'border' => [...],
        'text' => [...],
        'shadows' => [...],
        'filters' => [...],
        'transform' => [...],
        'opacity' => 1.0
    ],
    'bounds' => [
        'x' => 0,
        'y' => 0,
        'width' => 100,
        'height' => 0,
        'margin_top' => 0,
        'margin_right' => 0,
        'margin_bottom' => 0,
        'margin_left' => 0,
        'total_width' => 100,
        'total_height' => 0
    ],
    'z_index' => null,
    'stacking_context' => false
]
```

### Color Parsing

The renderer supports various color formats:

```php
// Named colors
'color' => 'red' // Parsed as ['type' => 'named', 'value' => 'red', 'hex' => '#ff0000']

// Hex colors
'color' => '#ff0000' // Parsed as ['type' => 'hex', 'value' => '#ff0000', 'hex' => '#ff0000']

// RGB colors
'color' => 'rgb(255, 0, 0)' // Parsed as ['type' => 'rgb', 'r' => 255, 'g' => 0, 'b' => 0, 'a' => 1.0, 'hex' => '#ff0000']

// RGBA colors
'color' => 'rgba(255, 0, 0, 0.5)' // Parsed as ['type' => 'rgb', 'r' => 255, 'g' => 0, 'b' => 0, 'a' => 0.5, 'hex' => '#ff0000']

// HSL colors
'color' => 'hsl(0, 100%, 50%)' // Parsed as ['type' => 'hsl', 'h' => 0, 's' => 100, 'l' => 50, 'a' => 1.0, 'hex' => '#ff0000']
```

### Length Parsing

Supports various length units:

```php
// Pixels
'width' => '300px' // Parsed as ['value' => 300, 'unit' => 'px', 'type' => 'length']

// Ems
'font-size' => '1.5em' // Parsed as ['value' => 1.5, 'unit' => 'em', 'type' => 'length']

// Percentages
'width' => '50%' // Parsed as ['value' => 50, 'unit' => '%', 'type' => 'length']

// Viewport units
'width' => '100vw' // Parsed as ['value' => 100, 'unit' => 'vw', 'type' => 'length']

// Auto/Inherit
'width' => 'auto' // Parsed as ['value' => 0, 'unit' => 'auto', 'type' => 'keyword']
```

### Transform Parsing

Supports CSS transforms:

```css
transform: translateX(10px) rotate(45deg) scale(1.2);
```

Parsed as:
```php
[
    ['type' => 'translateX', 'values' => [['value' => 10, 'unit' => 'px', 'type' => 'length']]],
    ['type' => 'rotate', 'values' => [['value' => 45, 'unit' => 'deg', 'type' => 'angle']]],
    ['type' => 'scale', 'values' => [['value' => 1.2, 'unit' => '', 'type' => 'number']]]
]
```

## Integration with PrismEngine

The CSS parsing and rendering services are integrated into the PrismEngine:

```php
use Prism\Backend\Services\Engines\PrismEngine;

$config = [
    'css_parsing' => true,
    'css_rendering' => true,
    // ... other config
];

$engine = new PrismEngine($config);
$engine->initialize();

// Navigate to a page (automatically parses CSS)
$engine->navigate('https://example.com');

// Get parsed CSS data
$cssData = $engine->getCssData();
$rules = $engine->getCssRules();
$mediaQueries = $engine->getMediaQueries();
$keyframes = $engine->getKeyframes();
$variables = $engine->getCssVariables();

// Compute styles for an element
$element = ['tagName' => 'div', 'classList' => ['container']];
$computedStyles = $engine->computeElementStyles($element);

// Render an element
$rendered = $engine->renderElement($element);

// Render the entire page
$pageRendered = $engine->renderPage();

// Get CSS statistics
$stats = $engine->getCssStats();
```

## Configuration

CSS parsing and rendering can be configured through the `config/css_parser.php` file:

```php
return [
    'enabled' => true,
    'debug' => false,
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'path' => __DIR__ . '/../cache/css'
    ],
    'parsing' => [
        'remove_comments' => true,
        'normalize_whitespace' => true,
        'minify' => false,
        'preserve_formatting' => false
    ],
    'computation' => [
        'apply_inheritance' => true,
        'apply_defaults' => true,
        'resolve_variables' => true,
        'resolve_relative_units' => true,
        'calculate_specificity' => true
    ],
    'media_queries' => [
        'enabled' => true,
        'default_breakpoints' => [
            'mobile' => '320px',
            'tablet' => '768px',
            'desktop' => '1024px',
            'large' => '1200px'
        ]
    ],
    'performance' => [
        'max_rules_per_selector' => 1000,
        'max_declarations_per_rule' => 100,
        'max_nesting_depth' => 10,
        'enable_selector_caching' => true,
        'enable_property_caching' => true,
        'memory_limit' => '128M'
    ]
];
```

## Performance Considerations

- CSS parsing is cached by default with a 1-hour TTL
- Selector and property caching can be enabled for better performance
- Memory limits are enforced to prevent excessive memory usage
- Large CSS files are processed in chunks to avoid memory issues

## Error Handling

Both services include comprehensive error handling:

- Invalid CSS is gracefully handled without breaking the parser
- Malformed selectors are logged but don't stop processing
- Missing properties fall back to default values
- Rendering errors are caught and logged

## Testing

The CSS services include comprehensive test suites:

```bash
# Run CSS parser tests
phpunit backend/tests/CssParserServiceTest.php

# Run CSS renderer tests
phpunit backend/tests/CssRendererServiceTest.php
```

## Future Enhancements

Planned improvements include:

- CSS Grid layout support
- Advanced animation handling
- CSS-in-JS support
- Real-time style updates
- Performance profiling tools
- CSS optimization and minification
- Advanced selector matching algorithms

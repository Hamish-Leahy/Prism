<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class CssRendererService
{
    private Logger $logger;
    private array $config;
    private array $renderedStyles = [];
    private array $layoutCache = [];
    private array $paintCache = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function renderElement(array $element, array $computedStyles, array $parentStyles = []): array
    {
        try {
            $this->logger->debug("Rendering element", [
                'tag' => $element['tagName'] ?? 'unknown',
                'id' => $element['id'] ?? null,
                'classes' => $element['classList'] ?? []
            ]);

            // Calculate layout
            $layout = $this->calculateLayout($element, $computedStyles, $parentStyles);
            
            // Calculate paint properties
            $paint = $this->calculatePaint($element, $computedStyles);
            
            // Calculate composite properties
            $composite = $this->calculateComposite($element, $computedStyles);
            
            // Generate visual representation
            $visual = $this->generateVisual($element, $layout, $paint, $composite);
            
            $rendered = [
                'element' => $element,
                'layout' => $layout,
                'paint' => $paint,
                'composite' => $composite,
                'visual' => $visual,
                'bounds' => $this->calculateBounds($layout),
                'z_index' => $composite['z_index'],
                'stacking_context' => $composite['stacking_context']
            ];

            $this->logger->debug("Element rendered successfully", [
                'bounds' => $rendered['bounds'],
                'z_index' => $rendered['z_index']
            ]);

            return $rendered;

        } catch (\Exception $e) {
            $this->logger->error("Element rendering failed: " . $e->getMessage());
            throw new \RuntimeException("Element rendering failed: " . $e->getMessage());
        }
    }

    private function calculateLayout(array $element, array $computedStyles, array $parentStyles): array
    {
        $display = $computedStyles['display']['value'] ?? 'block';
        $position = $computedStyles['position']['value'] ?? 'static';
        $float = $computedStyles['float']['value'] ?? 'none';
        
        $layout = [
            'display' => $display,
            'position' => $position,
            'float' => $float,
            'box_model' => $this->calculateBoxModel($computedStyles),
            'dimensions' => $this->calculateDimensions($element, $computedStyles, $parentStyles),
            'flow' => $this->calculateFlow($display, $position, $float),
            'containment' => $this->calculateContainment($computedStyles)
        ];
        
        return $layout;
    }

    private function calculateBoxModel(array $computedStyles): array
    {
        $boxSizing = $computedStyles['box-sizing']['value'] ?? 'content-box';
        
        $margin = $this->parseBoxProperty($computedStyles['margin']['value'] ?? '0');
        $padding = $this->parseBoxProperty($computedStyles['padding']['value'] ?? '0');
        $border = $this->parseBoxProperty($computedStyles['border']['value'] ?? '0');
        
        return [
            'box_sizing' => $boxSizing,
            'margin' => $margin,
            'padding' => $padding,
            'border' => $border,
            'content_width' => $this->parseLength($computedStyles['width']['value'] ?? 'auto'),
            'content_height' => $this->parseLength($computedStyles['height']['value'] ?? 'auto')
        ];
    }

    private function parseBoxProperty(string $value): array
    {
        $values = explode(' ', trim($value));
        $count = count($values);
        
        if ($count === 1) {
            return [
                'top' => $this->parseLength($values[0]),
                'right' => $this->parseLength($values[0]),
                'bottom' => $this->parseLength($values[0]),
                'left' => $this->parseLength($values[0])
            ];
        } elseif ($count === 2) {
            return [
                'top' => $this->parseLength($values[0]),
                'right' => $this->parseLength($values[1]),
                'bottom' => $this->parseLength($values[0]),
                'left' => $this->parseLength($values[1])
            ];
        } elseif ($count === 3) {
            return [
                'top' => $this->parseLength($values[0]),
                'right' => $this->parseLength($values[1]),
                'bottom' => $this->parseLength($values[2]),
                'left' => $this->parseLength($values[1])
            ];
        } else {
            return [
                'top' => $this->parseLength($values[0]),
                'right' => $this->parseLength($values[1]),
                'bottom' => $this->parseLength($values[2]),
                'left' => $this->parseLength($values[3])
            ];
        }
    }

    private function parseLength(string $value): array
    {
        $value = trim($value);
        
        if ($value === 'auto' || $value === 'inherit' || $value === 'initial') {
            return ['value' => 0, 'unit' => 'auto', 'type' => 'keyword'];
        }
        
        if (preg_match('/^(\d+(?:\.\d+)?)(px|em|rem|%|vh|vw|vmin|vmax)$/', $value, $matches)) {
            return [
                'value' => (float)$matches[1],
                'unit' => $matches[2],
                'type' => 'length'
            ];
        }
        
        return ['value' => 0, 'unit' => 'px', 'type' => 'unknown'];
    }

    private function calculateDimensions(array $element, array $computedStyles, array $parentStyles): array
    {
        $width = $this->parseLength($computedStyles['width']['value'] ?? 'auto');
        $height = $this->parseLength($computedStyles['height']['value'] ?? 'auto');
        $minWidth = $this->parseLength($computedStyles['min-width']['value'] ?? '0');
        $maxWidth = $this->parseLength($computedStyles['max-width']['value'] ?? 'none');
        $minHeight = $this->parseLength($computedStyles['min-height']['value'] ?? '0');
        $maxHeight = $this->parseLength($computedStyles['max-height']['value'] ?? 'none');
        
        // Calculate actual dimensions based on content and constraints
        $actualWidth = $this->resolveDimension($width, $parentStyles, 'width');
        $actualHeight = $this->resolveDimension($height, $parentStyles, 'height');
        
        return [
            'width' => $actualWidth,
            'height' => $actualHeight,
            'min_width' => $minWidth,
            'max_width' => $maxWidth,
            'min_height' => $minHeight,
            'max_height' => $maxHeight,
            'aspect_ratio' => $this->calculateAspectRatio($computedStyles)
        ];
    }

    private function resolveDimension(array $dimension, array $parentStyles, string $type): array
    {
        if ($dimension['type'] === 'keyword' && $dimension['unit'] === 'auto') {
            // Auto-sizing logic would go here
            return ['value' => 100, 'unit' => 'px', 'type' => 'calculated'];
        }
        
        if ($dimension['unit'] === '%') {
            $parentValue = $parentStyles[$type]['value'] ?? 100;
            return [
                'value' => ($dimension['value'] / 100) * $parentValue,
                'unit' => 'px',
                'type' => 'calculated'
            ];
        }
        
        return $dimension;
    }

    private function calculateAspectRatio(array $computedStyles): ?float
    {
        $aspectRatio = $computedStyles['aspect-ratio']['value'] ?? null;
        if (!$aspectRatio) {
            return null;
        }
        
        if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $aspectRatio, $matches)) {
            return (float)$matches[1] / (float)$matches[2];
        }
        
        return null;
    }

    private function calculateFlow(string $display, string $position, string $float): array
    {
        return [
            'is_block' => in_array($display, ['block', 'list-item', 'table', 'flex', 'grid']),
            'is_inline' => in_array($display, ['inline', 'inline-block', 'inline-table', 'inline-flex', 'inline-grid']),
            'is_positioned' => in_array($position, ['relative', 'absolute', 'fixed', 'sticky']),
            'is_floated' => $float !== 'none',
            'creates_stacking_context' => $this->createsStackingContext($display, $position),
            'creates_block_formatting_context' => $this->createsBlockFormattingContext($display, $float)
        ];
    }

    private function createsStackingContext(string $display, string $position): bool
    {
        return in_array($position, ['absolute', 'fixed', 'sticky']) ||
               in_array($display, ['flex', 'grid']) ||
               $display === 'table';
    }

    private function createsBlockFormattingContext(string $display, string $float): bool
    {
        return $float !== 'none' ||
               in_array($display, ['flex', 'grid', 'table-cell', 'table-caption']) ||
               $display === 'block';
    }

    private function calculateContainment(array $computedStyles): array
    {
        $contain = $computedStyles['contain']['value'] ?? 'none';
        
        return [
            'layout' => strpos($contain, 'layout') !== false,
            'paint' => strpos($contain, 'paint') !== false,
            'size' => strpos($contain, 'size') !== false,
            'style' => strpos($contain, 'style') !== false,
            'strict' => $contain === 'strict',
            'content' => $contain === 'content'
        ];
    }

    private function calculatePaint(array $element, array $computedStyles): array
    {
        return [
            'background' => $this->calculateBackground($computedStyles),
            'border' => $this->calculateBorder($computedStyles),
            'text' => $this->calculateText($computedStyles),
            'shadows' => $this->calculateShadows($computedStyles),
            'filters' => $this->calculateFilters($computedStyles),
            'opacity' => $this->parseOpacity($computedStyles['opacity']['value'] ?? '1'),
            'visibility' => $computedStyles['visibility']['value'] ?? 'visible'
        ];
    }

    private function calculateBackground(array $computedStyles): array
    {
        $backgroundColor = $this->parseColor($computedStyles['background-color']['value'] ?? 'transparent');
        $backgroundImage = $computedStyles['background-image']['value'] ?? 'none';
        $backgroundRepeat = $computedStyles['background-repeat']['value'] ?? 'repeat';
        $backgroundPosition = $computedStyles['background-position']['value'] ?? '0% 0%';
        $backgroundSize = $computedStyles['background-size']['value'] ?? 'auto';
        
        return [
            'color' => $backgroundColor,
            'image' => $backgroundImage,
            'repeat' => $backgroundRepeat,
            'position' => $backgroundPosition,
            'size' => $backgroundSize,
            'attachment' => $computedStyles['background-attachment']['value'] ?? 'scroll'
        ];
    }

    private function parseColor(string $value): array
    {
        $value = trim($value);
        
        // Named colors
        if (preg_match('/^[a-zA-Z]+$/', $value)) {
            return ['type' => 'named', 'value' => $value, 'hex' => $this->nameToHex($value)];
        }
        
        // Hex colors
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
            return ['type' => 'hex', 'value' => $value, 'hex' => $value];
        }
        
        // RGB/RGBA
        if (preg_match('/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/', $value, $matches)) {
            $r = (int)$matches[1];
            $g = (int)$matches[2];
            $b = (int)$matches[3];
            $a = isset($matches[4]) ? (float)$matches[4] : 1.0;
            
            return [
                'type' => 'rgb',
                'value' => $value,
                'r' => $r,
                'g' => $g,
                'b' => $b,
                'a' => $a,
                'hex' => sprintf('#%02x%02x%02x', $r, $g, $b)
            ];
        }
        
        // HSL/HSLA
        if (preg_match('/^hsla?\((\d+),\s*(\d+)%,\s*(\d+)%(?:,\s*([\d.]+))?\)$/', $value, $matches)) {
            $h = (int)$matches[1];
            $s = (int)$matches[2];
            $l = (int)$matches[3];
            $a = isset($matches[4]) ? (float)$matches[4] : 1.0;
            
            return [
                'type' => 'hsl',
                'value' => $value,
                'h' => $h,
                's' => $s,
                'l' => $l,
                'a' => $a,
                'hex' => $this->hslToHex($h, $s, $l)
            ];
        }
        
        return ['type' => 'unknown', 'value' => $value, 'hex' => '#000000'];
    }

    private function nameToHex(string $name): string
    {
        $colors = [
            'black' => '#000000',
            'white' => '#ffffff',
            'red' => '#ff0000',
            'green' => '#00ff00',
            'blue' => '#0000ff',
            'yellow' => '#ffff00',
            'cyan' => '#00ffff',
            'magenta' => '#ff00ff',
            'transparent' => 'transparent'
        ];
        
        return $colors[strtolower($name)] ?? '#000000';
    }

    private function hslToHex(int $h, int $s, int $l): string
    {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;
        
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
        $m = $l - $c / 2;
        
        if ($h < 1/6) {
            $r = $c; $g = $x; $b = 0;
        } elseif ($h < 2/6) {
            $r = $x; $g = $c; $b = 0;
        } elseif ($h < 3/6) {
            $r = 0; $g = $c; $b = $x;
        } elseif ($h < 4/6) {
            $r = 0; $g = $x; $b = $c;
        } elseif ($h < 5/6) {
            $r = $x; $g = 0; $b = $c;
        } else {
            $r = $c; $g = 0; $b = $x;
        }
        
        $r = round(($r + $m) * 255);
        $g = round(($g + $m) * 255);
        $b = round(($b + $m) * 255);
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function calculateBorder(array $computedStyles): array
    {
        $borderWidth = $this->parseBoxProperty($computedStyles['border-width']['value'] ?? '0');
        $borderStyle = $this->parseBoxProperty($computedStyles['border-style']['value'] ?? 'none');
        $borderColor = $this->parseBoxProperty($computedStyles['border-color']['value'] ?? 'currentColor');
        
        return [
            'width' => $borderWidth,
            'style' => $borderStyle,
            'color' => $borderColor,
            'radius' => $this->parseBorderRadius($computedStyles['border-radius']['value'] ?? '0'),
            'image' => $computedStyles['border-image']['value'] ?? 'none'
        ];
    }

    private function parseBorderRadius(string $value): array
    {
        $values = explode(' ', trim($value));
        $count = count($values);
        
        if ($count === 1) {
            return [
                'top_left' => $this->parseLength($values[0]),
                'top_right' => $this->parseLength($values[0]),
                'bottom_right' => $this->parseLength($values[0]),
                'bottom_left' => $this->parseLength($values[0])
            ];
        } elseif ($count === 2) {
            return [
                'top_left' => $this->parseLength($values[0]),
                'top_right' => $this->parseLength($values[1]),
                'bottom_right' => $this->parseLength($values[0]),
                'bottom_left' => $this->parseLength($values[1])
            ];
        } elseif ($count === 3) {
            return [
                'top_left' => $this->parseLength($values[0]),
                'top_right' => $this->parseLength($values[1]),
                'bottom_right' => $this->parseLength($values[2]),
                'bottom_left' => $this->parseLength($values[1])
            ];
        } else {
            return [
                'top_left' => $this->parseLength($values[0]),
                'top_right' => $this->parseLength($values[1]),
                'bottom_right' => $this->parseLength($values[2]),
                'bottom_left' => $this->parseLength($values[3])
            ];
        }
    }

    private function calculateText(array $computedStyles): array
    {
        return [
            'color' => $this->parseColor($computedStyles['color']['value'] ?? 'inherit'),
            'font_family' => $computedStyles['font-family']['value'] ?? 'inherit',
            'font_size' => $this->parseLength($computedStyles['font-size']['value'] ?? 'inherit'),
            'font_weight' => $computedStyles['font-weight']['value'] ?? 'normal',
            'font_style' => $computedStyles['font-style']['value'] ?? 'normal',
            'line_height' => $this->parseLineHeight($computedStyles['line-height']['value'] ?? 'normal'),
            'text_align' => $computedStyles['text-align']['value'] ?? 'left',
            'text_decoration' => $computedStyles['text-decoration']['value'] ?? 'none',
            'text_transform' => $computedStyles['text-transform']['value'] ?? 'none',
            'letter_spacing' => $this->parseLength($computedStyles['letter-spacing']['value'] ?? 'normal'),
            'word_spacing' => $this->parseLength($computedStyles['word-spacing']['value'] ?? 'normal')
        ];
    }

    private function parseLineHeight(string $value): array
    {
        $value = trim($value);
        
        if ($value === 'normal') {
            return ['type' => 'normal', 'value' => 1.2];
        }
        
        if (is_numeric($value)) {
            return ['type' => 'number', 'value' => (float)$value];
        }
        
        return $this->parseLength($value);
    }

    private function calculateShadows(array $computedStyles): array
    {
        return [
            'box_shadow' => $this->parseBoxShadow($computedStyles['box-shadow']['value'] ?? 'none'),
            'text_shadow' => $this->parseTextShadow($computedStyles['text-shadow']['value'] ?? 'none')
        ];
    }

    private function parseBoxShadow(string $value): array
    {
        if ($value === 'none') {
            return [];
        }
        
        $shadows = [];
        $parts = explode(',', $value);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(.+)$/', $part, $matches)) {
                $shadows[] = [
                    'offset_x' => $this->parseLength($matches[1]),
                    'offset_y' => $this->parseLength($matches[2]),
                    'blur_radius' => $this->parseLength($matches[3]),
                    'spread_radius' => $this->parseLength($matches[4]),
                    'color' => $this->parseColor($matches[5]),
                    'inset' => false
                ];
            }
        }
        
        return $shadows;
    }

    private function parseTextShadow(string $value): array
    {
        if ($value === 'none') {
            return [];
        }
        
        $shadows = [];
        $parts = explode(',', $value);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(-?\d+(?:\.\d+)?(?:px|em|rem|%)?)\s+(.+)$/', $part, $matches)) {
                $shadows[] = [
                    'offset_x' => $this->parseLength($matches[1]),
                    'offset_y' => $this->parseLength($matches[2]),
                    'blur_radius' => $this->parseLength($matches[3]),
                    'color' => $this->parseColor($matches[4])
                ];
            }
        }
        
        return $shadows;
    }

    private function calculateFilters(array $computedStyles): array
    {
        $filter = $computedStyles['filter']['value'] ?? 'none';
        $backdropFilter = $computedStyles['backdrop-filter']['value'] ?? 'none';
        
        return [
            'filter' => $this->parseFilter($filter),
            'backdrop_filter' => $this->parseFilter($backdropFilter),
            'mix_blend_mode' => $computedStyles['mix-blend-mode']['value'] ?? 'normal'
        ];
    }

    private function parseFilter(string $value): array
    {
        if ($value === 'none') {
            return [];
        }
        
        $filters = [];
        $parts = explode(' ', $value);
        
        foreach ($parts as $part) {
            if (preg_match('/^(blur|brightness|contrast|grayscale|hue-rotate|invert|opacity|saturate|sepia)\(([^)]+)\)$/', $part, $matches)) {
                $filters[] = [
                    'type' => $matches[1],
                    'value' => $this->parseLength($matches[2])
                ];
            }
        }
        
        return $filters;
    }

    private function parseOpacity(string $value): float
    {
        $value = trim($value);
        if (is_numeric($value)) {
            return max(0, min(1, (float)$value));
        }
        return 1.0;
    }

    private function calculateComposite(array $element, array $computedStyles): array
    {
        $zIndex = $computedStyles['z-index']['value'] ?? 'auto';
        $transform = $computedStyles['transform']['value'] ?? 'none';
        $opacity = $this->parseOpacity($computedStyles['opacity']['value'] ?? '1');
        
        return [
            'z_index' => $this->parseZIndex($zIndex),
            'transform' => $this->parseTransform($transform),
            'opacity' => $opacity,
            'stacking_context' => $this->createsStackingContext(
                $computedStyles['display']['value'] ?? 'block',
                $computedStyles['position']['value'] ?? 'static'
            ),
            'will_change' => $computedStyles['will-change']['value'] ?? 'auto',
            'contain' => $computedStyles['contain']['value'] ?? 'none'
        ];
    }

    private function parseZIndex(string $value): ?int
    {
        if ($value === 'auto') {
            return null;
        }
        
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        return 0;
    }

    private function parseTransform(string $value): array
    {
        if ($value === 'none') {
            return [];
        }
        
        $transforms = [];
        $parts = explode(' ', $value);
        
        foreach ($parts as $part) {
            if (preg_match('/^(translate|translateX|translateY|translateZ|translate3d|rotate|rotateX|rotateY|rotateZ|rotate3d|scale|scaleX|scaleY|scaleZ|scale3d|skew|skewX|skewY|matrix|matrix3d|perspective)\(([^)]+)\)$/', $part, $matches)) {
                $transforms[] = [
                    'type' => $matches[1],
                    'values' => array_map([$this, 'parseLength'], explode(',', $matches[2]))
                ];
            }
        }
        
        return $transforms;
    }

    private function generateVisual(array $element, array $layout, array $paint, array $composite): array
    {
        return [
            'background' => $this->generateBackgroundVisual($paint['background']),
            'border' => $this->generateBorderVisual($paint['border']),
            'text' => $this->generateTextVisual($paint['text']),
            'shadows' => $this->generateShadowVisual($paint['shadows']),
            'filters' => $this->generateFilterVisual($paint['filters']),
            'transform' => $this->generateTransformVisual($composite['transform']),
            'opacity' => $composite['opacity']
        ];
    }

    private function generateBackgroundVisual(array $background): array
    {
        return [
            'color' => $background['color'],
            'image' => $background['image'],
            'gradient' => $this->parseGradient($background['image']),
            'pattern' => $this->parsePattern($background['image'])
        ];
    }

    private function parseGradient(string $value): ?array
    {
        if (preg_match('/^(linear-gradient|radial-gradient|conic-gradient)\(([^)]+)\)$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'stops' => $this->parseGradientStops($matches[2])
            ];
        }
        return null;
    }

    private function parseGradientStops(string $stops): array
    {
        // Simplified gradient stop parsing
        $parts = explode(',', $stops);
        $parsed = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(.+?)\s+(\d+(?:\.\d+)?%?)$/', $part, $matches)) {
                $parsed[] = [
                    'color' => $this->parseColor($matches[1]),
                    'position' => $matches[2]
                ];
            }
        }
        
        return $parsed;
    }

    private function parsePattern(string $value): ?array
    {
        if (preg_match('/^url\(["\']?([^"\')\s]+)["\']?\)$/', $value, $matches)) {
            return [
                'type' => 'image',
                'url' => $matches[1]
            ];
        }
        return null;
    }

    private function generateBorderVisual(array $border): array
    {
        return [
            'width' => $border['width'],
            'style' => $border['style'],
            'color' => $border['color'],
            'radius' => $border['radius'],
            'image' => $border['image']
        ];
    }

    private function generateTextVisual(array $text): array
    {
        return [
            'color' => $text['color'],
            'font' => [
                'family' => $text['font_family'],
                'size' => $text['font_size'],
                'weight' => $text['font_weight'],
                'style' => $text['font_style']
            ],
            'decoration' => $text['text_decoration'],
            'transform' => $text['text_transform'],
            'spacing' => [
                'letter' => $text['letter_spacing'],
                'word' => $text['word_spacing']
            ]
        ];
    }

    private function generateShadowVisual(array $shadows): array
    {
        return [
            'box_shadow' => $shadows['box_shadow'],
            'text_shadow' => $shadows['text_shadow']
        ];
    }

    private function generateFilterVisual(array $filters): array
    {
        return [
            'filters' => $filters['filter'],
            'backdrop_filters' => $filters['backdrop_filter'],
            'blend_mode' => $filters['mix_blend_mode']
        ];
    }

    private function generateTransformVisual(array $transforms): array
    {
        return [
            'transforms' => $transforms,
            'matrix' => $this->calculateTransformMatrix($transforms)
        ];
    }

    private function calculateTransformMatrix(array $transforms): array
    {
        // Simplified matrix calculation
        $matrix = [1, 0, 0, 1, 0, 0]; // Identity matrix
        
        foreach ($transforms as $transform) {
            switch ($transform['type']) {
                case 'translate':
                    $x = $transform['values'][0]['value'] ?? 0;
                    $y = $transform['values'][1]['value'] ?? 0;
                    $matrix[4] += $x;
                    $matrix[5] += $y;
                    break;
                case 'scale':
                    $sx = $transform['values'][0]['value'] ?? 1;
                    $sy = $transform['values'][1]['value'] ?? $sx;
                    $matrix[0] *= $sx;
                    $matrix[3] *= $sy;
                    break;
                case 'rotate':
                    $angle = ($transform['values'][0]['value'] ?? 0) * M_PI / 180;
                    $cos = cos($angle);
                    $sin = sin($angle);
                    $newMatrix = [
                        $matrix[0] * $cos - $matrix[1] * $sin,
                        $matrix[0] * $sin + $matrix[1] * $cos,
                        $matrix[2] * $cos - $matrix[3] * $sin,
                        $matrix[2] * $sin + $matrix[3] * $cos,
                        $matrix[4],
                        $matrix[5]
                    ];
                    $matrix = $newMatrix;
                    break;
            }
        }
        
        return $matrix;
    }

    private function calculateBounds(array $layout): array
    {
        $boxModel = $layout['box_model'];
        $dimensions = $layout['dimensions'];
        
        $width = $dimensions['width']['value'] ?? 0;
        $height = $dimensions['height']['value'] ?? 0;
        
        $marginTop = $boxModel['margin']['top']['value'] ?? 0;
        $marginRight = $boxModel['margin']['right']['value'] ?? 0;
        $marginBottom = $boxModel['margin']['bottom']['value'] ?? 0;
        $marginLeft = $boxModel['margin']['left']['value'] ?? 0;
        
        return [
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
            'margin_top' => $marginTop,
            'margin_right' => $marginRight,
            'margin_bottom' => $marginBottom,
            'margin_left' => $marginLeft,
            'total_width' => $width + $marginLeft + $marginRight,
            'total_height' => $height + $marginTop + $marginBottom
        ];
    }

    public function renderPage(array $elements, array $styles): array
    {
        $renderedElements = [];
        $stackingContexts = [];
        
        foreach ($elements as $element) {
            $computedStyles = $this->computeStylesForElement($element, $styles);
            $rendered = $this->renderElement($element, $computedStyles);
            $renderedElements[] = $rendered;
            
            if ($rendered['composite']['stacking_context']) {
                $stackingContexts[] = $rendered;
            }
        }
        
        // Sort by z-index
        usort($renderedElements, function($a, $b) {
            $zA = $a['composite']['z_index'] ?? 0;
            $zB = $b['composite']['z_index'] ?? 0;
            return $zA <=> $zB;
        });
        
        return [
            'elements' => $renderedElements,
            'stacking_contexts' => $stackingContexts,
            'viewport' => $this->calculateViewport($renderedElements)
        ];
    }

    private function computeStylesForElement(array $element, array $styles): array
    {
        // This would integrate with CssParserService
        // For now, return empty array
        return [];
    }

    private function calculateViewport(array $elements): array
    {
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = PHP_FLOAT_MIN;
        $maxY = PHP_FLOAT_MIN;
        
        foreach ($elements as $element) {
            $bounds = $element['bounds'];
            $minX = min($minX, $bounds['x']);
            $minY = min($minY, $bounds['y']);
            $maxX = max($maxX, $bounds['x'] + $bounds['total_width']);
            $maxY = max($maxY, $bounds['y'] + $bounds['total_height']);
        }
        
        return [
            'x' => $minX === PHP_FLOAT_MAX ? 0 : $minX,
            'y' => $minY === PHP_FLOAT_MAX ? 0 : $minY,
            'width' => $maxX === PHP_FLOAT_MIN ? 0 : $maxX - $minX,
            'height' => $maxY === PHP_FLOAT_MIN ? 0 : $maxY - $minY
        ];
    }

    public function getRenderedStyles(): array
    {
        return $this->renderedStyles;
    }

    public function clearCache(): void
    {
        $this->renderedStyles = [];
        $this->layoutCache = [];
        $this->paintCache = [];
    }
}

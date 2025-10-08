<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class CssParserService
{
    private Logger $logger;
    private array $config;
    private array $parsedStyles = [];
    private array $computedStyles = [];
    private array $mediaQueries = [];
    private array $keyframes = [];
    private array $variables = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function parseCss(string $css, string $baseUrl = ''): array
    {
        try {
            $this->logger->debug("Starting CSS parsing", [
                'css_length' => strlen($css),
                'base_url' => $baseUrl
            ]);

            // Preprocess CSS
            $css = $this->preprocessCss($css);
            
            // Parse CSS rules
            $rules = $this->parseRules($css);
            
            // Parse media queries
            $mediaQueries = $this->parseMediaQueries($css);
            
            // Parse keyframes
            $keyframes = $this->parseKeyframes($css);
            
            // Parse CSS variables
            $variables = $this->parseVariables($css);
            
            // Parse imports
            $imports = $this->parseImports($css, $baseUrl);
            
            $result = [
                'rules' => $rules,
                'media_queries' => $mediaQueries,
                'keyframes' => $keyframes,
                'variables' => $variables,
                'imports' => $imports,
                'stats' => $this->calculateStats($rules)
            ];

            $this->logger->debug("CSS parsing completed", [
                'rules_count' => count($rules),
                'media_queries_count' => count($mediaQueries),
                'keyframes_count' => count($keyframes),
                'variables_count' => count($variables)
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("CSS parsing failed: " . $e->getMessage());
            throw new \RuntimeException("CSS parsing failed: " . $e->getMessage());
        }
    }

    private function preprocessCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Normalize whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary spaces
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Normalize line endings
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        
        return trim($css);
    }

    private function parseRules(string $css): array
    {
        $rules = [];
        
        // Match CSS rules (selectors and declarations)
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selectors = trim($match[1]);
            $declarations = trim($match[2]);
            
            if (empty($selectors) || empty($declarations)) {
                continue;
            }
            
            $rule = [
                'selectors' => $this->parseSelectors($selectors),
                'declarations' => $this->parseDeclarations($declarations),
                'specificity' => $this->calculateSpecificity($selectors),
                'source' => 'inline'
            ];
            
            $rules[] = $rule;
        }
        
        return $rules;
    }

    private function parseSelectors(string $selectors): array
    {
        $selectors = explode(',', $selectors);
        $parsed = [];
        
        foreach ($selectors as $selector) {
            $selector = trim($selector);
            if (empty($selector)) {
                continue;
            }
            
            $parsed[] = [
                'raw' => $selector,
                'type' => $this->getSelectorType($selector),
                'specificity' => $this->calculateSelectorSpecificity($selector),
                'pseudo_classes' => $this->extractPseudoClasses($selector),
                'pseudo_elements' => $this->extractPseudoElements($selector),
                'attributes' => $this->extractAttributes($selector),
                'combinators' => $this->extractCombinators($selector)
            ];
        }
        
        return $parsed;
    }

    private function getSelectorType(string $selector): string
    {
        if (strpos($selector, '#') === 0) {
            return 'id';
        } elseif (strpos($selector, '.') === 0) {
            return 'class';
        } elseif (preg_match('/^[a-zA-Z]/', $selector)) {
            return 'element';
        } elseif (strpos($selector, '[') !== false) {
            return 'attribute';
        } elseif (strpos($selector, ':') !== false) {
            return 'pseudo';
        } else {
            return 'universal';
        }
    }

    private function calculateSelectorSpecificity(string $selector): array
    {
        $specificity = ['ids' => 0, 'classes' => 0, 'elements' => 0];
        
        // Count IDs
        $specificity['ids'] = substr_count($selector, '#');
        
        // Count classes and attributes
        $specificity['classes'] = substr_count($selector, '.') + substr_count($selector, '[');
        
        // Count elements (simplified)
        $specificity['elements'] = preg_match_all('/[a-zA-Z][a-zA-Z0-9]*/', $selector);
        
        return $specificity;
    }

    private function extractPseudoClasses(string $selector): array
    {
        preg_match_all('/:([a-zA-Z-]+)/', $selector, $matches);
        return $matches[1] ?? [];
    }

    private function extractPseudoElements(string $selector): array
    {
        preg_match_all('/::([a-zA-Z-]+)/', $selector, $matches);
        return $matches[1] ?? [];
    }

    private function extractAttributes(string $selector): array
    {
        preg_match_all('/\[([^=]+)(?:=([^\]]+))?\]/', $selector, $matches, PREG_SET_ORDER);
        $attributes = [];
        
        foreach ($matches as $match) {
            $attributes[] = [
                'name' => $match[1],
                'value' => $match[2] ?? null,
                'operator' => $this->getAttributeOperator($match[0])
            ];
        }
        
        return $attributes;
    }

    private function getAttributeOperator(string $attribute): string
    {
        if (strpos($attribute, '~=') !== false) {
            return 'contains';
        } elseif (strpos($attribute, '|=') !== false) {
            return 'starts_with';
        } elseif (strpos($attribute, '^=') !== false) {
            return 'prefix';
        } elseif (strpos($attribute, '$=') !== false) {
            return 'suffix';
        } elseif (strpos($attribute, '*=') !== false) {
            return 'substring';
        } else {
            return 'equals';
        }
    }

    private function extractCombinators(string $selector): array
    {
        $combinators = [];
        
        if (strpos($selector, ' ') !== false) {
            $combinators[] = 'descendant';
        }
        if (strpos($selector, '>') !== false) {
            $combinators[] = 'child';
        }
        if (strpos($selector, '+') !== false) {
            $combinators[] = 'adjacent_sibling';
        }
        if (strpos($selector, '~') !== false) {
            $combinators[] = 'general_sibling';
        }
        
        return $combinators;
    }

    private function parseDeclarations(string $declarations): array
    {
        $declarations = explode(';', $declarations);
        $parsed = [];
        
        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if (empty($declaration)) {
                continue;
            }
            
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $property = trim($parts[0]);
            $value = trim($parts[1]);
            
            $parsed[] = [
                'property' => $property,
                'value' => $value,
                'important' => $this->isImportant($value),
                'type' => $this->getPropertyType($property),
                'shorthand' => $this->isShorthandProperty($property)
            ];
        }
        
        return $parsed;
    }

    private function isImportant(string $value): bool
    {
        return strpos($value, '!important') !== false;
    }

    private function getPropertyType(string $property): string
    {
        $types = [
            'color' => ['color', 'background-color', 'border-color', 'text-decoration-color'],
            'length' => ['width', 'height', 'margin', 'padding', 'border-width', 'font-size'],
            'percentage' => ['width', 'height', 'margin', 'padding', 'top', 'left', 'right', 'bottom'],
            'angle' => ['transform', 'rotate', 'skew'],
            'time' => ['transition-duration', 'animation-duration', 'animation-delay'],
            'frequency' => ['pitch', 'voice-pitch'],
            'resolution' => ['image-resolution'],
            'url' => ['background-image', 'list-style-image', 'cursor'],
            'keyword' => ['display', 'position', 'float', 'clear', 'visibility', 'overflow']
        ];
        
        foreach ($types as $type => $properties) {
            if (in_array($property, $properties)) {
                return $type;
            }
        }
        
        return 'unknown';
    }

    private function isShorthandProperty(string $property): bool
    {
        $shorthand = [
            'margin', 'padding', 'border', 'background', 'font', 'list-style',
            'outline', 'text-decoration', 'transition', 'animation', 'flex',
            'grid', 'columns', 'border-radius', 'border-width', 'border-style',
            'border-color', 'border-image', 'text-shadow', 'box-shadow'
        ];
        
        return in_array($property, $shorthand);
    }

    private function parseMediaQueries(string $css): array
    {
        $mediaQueries = [];
        
        preg_match_all('/@media\s+([^{]+)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $query = trim($match[1]);
            $content = trim($match[2]);
            
            $mediaQuery = [
                'query' => $query,
                'features' => $this->parseMediaFeatures($query),
                'rules' => $this->parseRules($content),
                'type' => $this->getMediaType($query)
            ];
            
            $mediaQueries[] = $mediaQuery;
        }
        
        return $mediaQueries;
    }

    private function parseMediaFeatures(string $query): array
    {
        $features = [];
        
        preg_match_all('/(?:\(([^)]+)\)|([a-zA-Z-]+))/', $query, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                // Feature with value
                $parts = explode(':', $match[1], 2);
                $features[] = [
                    'name' => trim($parts[0]),
                    'value' => isset($parts[1]) ? trim($parts[1]) : null,
                    'type' => 'feature'
                ];
            } elseif (!empty($match[2])) {
                // Media type
                $features[] = [
                    'name' => trim($match[2]),
                    'value' => null,
                    'type' => 'type'
                ];
            }
        }
        
        return $features;
    }

    private function getMediaType(string $query): string
    {
        $types = ['all', 'screen', 'print', 'speech', 'handheld', 'tv', 'projection'];
        
        foreach ($types as $type) {
            if (stripos($query, $type) !== false) {
                return $type;
            }
        }
        
        return 'all';
    }

    private function parseKeyframes(string $css): array
    {
        $keyframes = [];
        
        preg_match_all('/@keyframes\s+([a-zA-Z-]+)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = trim($match[1]);
            $content = trim($match[2]);
            
            $keyframe = [
                'name' => $name,
                'steps' => $this->parseKeyframeSteps($content)
            ];
            
            $keyframes[] = $keyframe;
        }
        
        return $keyframes;
    }

    private function parseKeyframeSteps(string $content): array
    {
        $steps = [];
        
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selectors = trim($match[1]);
            $declarations = trim($match[2]);
            
            $step = [
                'selectors' => array_map('trim', explode(',', $selectors)),
                'declarations' => $this->parseDeclarations($declarations)
            ];
            
            $steps[] = $step;
        }
        
        return $steps;
    }

    private function parseVariables(string $css): array
    {
        $variables = [];
        
        preg_match_all('/--([a-zA-Z-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $variables[] = [
                'name' => $match[1],
                'value' => trim($match[2]),
                'scope' => 'root'
            ];
        }
        
        return $variables;
    }

    private function parseImports(string $css, string $baseUrl): array
    {
        $imports = [];
        
        preg_match_all('/@import\s+(?:url\()?["\']?([^"\')\s]+)["\']?\)?/', $css, $matches);
        
        foreach ($matches[1] as $url) {
            $imports[] = [
                'url' => $url,
                'absolute_url' => $this->resolveUrl($url, $baseUrl),
                'type' => $this->getImportType($url)
            ];
        }
        
        return $imports;
    }

    private function getImportType(string $url): string
    {
        if (preg_match('/\.css$/', $url)) {
            return 'css';
        } elseif (preg_match('/\.scss$/', $url)) {
            return 'scss';
        } elseif (preg_match('/\.less$/', $url)) {
            return 'less';
        } else {
            return 'unknown';
        }
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($baseUrl)) {
            return $url;
        }
        
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
        
        $base = parse_url($baseUrl);
        if (!$base) {
            return $url;
        }
        
        if (strpos($url, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $url;
        } else {
            $basePath = isset($base['path']) ? dirname($base['path']) : '/';
            if ($basePath === '.') {
                $basePath = '/';
            }
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $basePath . '/' . $url;
        }
    }

    private function calculateSpecificity(string $selectors): array
    {
        $specificity = ['ids' => 0, 'classes' => 0, 'elements' => 0];
        
        $selectorList = explode(',', $selectors);
        
        foreach ($selectorList as $selector) {
            $selector = trim($selector);
            $selSpecificity = $this->calculateSelectorSpecificity($selector);
            
            $specificity['ids'] = max($specificity['ids'], $selSpecificity['ids']);
            $specificity['classes'] = max($specificity['classes'], $selSpecificity['classes']);
            $specificity['elements'] = max($specificity['elements'], $selSpecificity['elements']);
        }
        
        return $specificity;
    }

    private function calculateStats(array $rules): array
    {
        $stats = [
            'total_rules' => count($rules),
            'total_selectors' => 0,
            'total_declarations' => 0,
            'property_counts' => [],
            'selector_types' => [],
            'specificity_distribution' => ['low' => 0, 'medium' => 0, 'high' => 0]
        ];
        
        foreach ($rules as $rule) {
            $stats['total_selectors'] += count($rule['selectors']);
            $stats['total_declarations'] += count($rule['declarations']);
            
            foreach ($rule['selectors'] as $selector) {
                $type = $selector['type'];
                $stats['selector_types'][$type] = ($stats['selector_types'][$type] ?? 0) + 1;
            }
            
            foreach ($rule['declarations'] as $declaration) {
                $property = $declaration['property'];
                $stats['property_counts'][$property] = ($stats['property_counts'][$property] ?? 0) + 1;
            }
            
            $specificity = $rule['specificity'];
            $total = $specificity['ids'] * 100 + $specificity['classes'] * 10 + $specificity['elements'];
            
            if ($total < 10) {
                $stats['specificity_distribution']['low']++;
            } elseif ($total < 100) {
                $stats['specificity_distribution']['medium']++;
            } else {
                $stats['specificity_distribution']['high']++;
            }
        }
        
        return $stats;
    }

    public function computeStyles(array $rules, array $element, array $parentStyles = []): array
    {
        $computedStyles = [];
        
        foreach ($rules as $rule) {
            if ($this->matchesElement($rule['selectors'], $element)) {
                foreach ($rule['declarations'] as $declaration) {
                    $property = $declaration['property'];
                    $value = $declaration['value'];
                    $important = $declaration['important'];
                    
                    // Check if this property should override existing value
                    if (!isset($computedStyles[$property]) || 
                        $important || 
                        $this->hasHigherSpecificity($rule['specificity'], $computedStyles[$property]['specificity'])) {
                        
                        $computedStyles[$property] = [
                            'value' => $this->resolveValue($value),
                            'important' => $important,
                            'specificity' => $rule['specificity'],
                            'source' => $rule['source']
                        ];
                    }
                }
            }
        }
        
        // Apply inheritance
        $computedStyles = $this->applyInheritance($computedStyles, $parentStyles);
        
        // Apply default values
        $computedStyles = $this->applyDefaults($computedStyles, $element);
        
        return $computedStyles;
    }

    private function matchesElement(array $selectors, array $element): bool
    {
        foreach ($selectors as $selector) {
            if ($this->matchesSelector($selector, $element)) {
                return true;
            }
        }
        return false;
    }

    private function matchesSelector(array $selector, array $element): bool
    {
        // Simplified selector matching
        $raw = $selector['raw'];
        
        // Check tag name
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*/', $raw, $matches)) {
            if (strtolower($matches[0]) !== strtolower($element['tagName'])) {
                return false;
            }
        }
        
        // Check ID
        if (preg_match('/#([a-zA-Z-]+)/', $raw, $matches)) {
            if ($element['id'] !== $matches[1]) {
                return false;
            }
        }
        
        // Check class
        if (preg_match('/\.([a-zA-Z-]+)/', $raw, $matches)) {
            if (!in_array($matches[1], $element['classList'])) {
                return false;
            }
        }
        
        // Check attributes
        foreach ($selector['attributes'] as $attr) {
            if (!$this->matchesAttribute($attr, $element)) {
                return false;
            }
        }
        
        return true;
    }

    private function matchesAttribute(array $attr, array $element): bool
    {
        $value = $element['attributes'][$attr['name']] ?? null;
        
        if ($value === null) {
            return false;
        }
        
        switch ($attr['operator']) {
            case 'equals':
                return $value === $attr['value'];
            case 'contains':
                return strpos($value, $attr['value']) !== false;
            case 'starts_with':
                return strpos($value, $attr['value']) === 0;
            case 'prefix':
                return strpos($value, $attr['value'] . '-') === 0;
            case 'suffix':
                return substr($value, -strlen($attr['value'])) === $attr['value'];
            case 'substring':
                return strpos($value, $attr['value']) !== false;
            default:
                return true;
        }
    }

    private function hasHigherSpecificity(array $specificity1, array $specificity2): bool
    {
        $score1 = $specificity1['ids'] * 100 + $specificity1['classes'] * 10 + $specificity1['elements'];
        $score2 = $specificity2['ids'] * 100 + $specificity2['classes'] * 10 + $specificity2['elements'];
        
        return $score1 > $score2;
    }

    private function resolveValue(string $value): string
    {
        // Remove !important
        $value = str_replace('!important', '', $value);
        $value = trim($value);
        
        // Resolve CSS variables
        $value = $this->resolveVariables($value);
        
        // Resolve relative units
        $value = $this->resolveRelativeUnits($value);
        
        return $value;
    }

    private function resolveVariables(string $value): string
    {
        // Simple CSS variable resolution
        preg_match_all('/var\(--([a-zA-Z-]+)\)/', $value, $matches);
        
        foreach ($matches[1] as $varName) {
            $varValue = $this->variables[$varName] ?? '';
            $value = str_replace("var(--$varName)", $varValue, $value);
        }
        
        return $value;
    }

    private function resolveRelativeUnits(string $value): string
    {
        // Convert relative units to pixels (simplified)
        $value = preg_replace('/(\d+)em/', '${1}px', $value);
        $value = preg_replace('/(\d+)rem/', '${1}px', $value);
        $value = preg_replace('/(\d+)%/', '${1}px', $value);
        
        return $value;
    }

    private function applyInheritance(array $styles, array $parentStyles): array
    {
        $inheritable = [
            'color', 'font-family', 'font-size', 'font-weight', 'font-style',
            'line-height', 'text-align', 'text-decoration', 'text-transform',
            'letter-spacing', 'word-spacing', 'visibility', 'cursor'
        ];
        
        foreach ($inheritable as $property) {
            if (!isset($styles[$property]) && isset($parentStyles[$property])) {
                $styles[$property] = $parentStyles[$property];
            }
        }
        
        return $styles;
    }

    private function applyDefaults(array $styles, array $element): array
    {
        $defaults = [
            'display' => 'block',
            'position' => 'static',
            'float' => 'none',
            'clear' => 'none',
            'visibility' => 'visible',
            'overflow' => 'visible',
            'z-index' => 'auto',
            'width' => 'auto',
            'height' => 'auto',
            'margin' => '0',
            'padding' => '0',
            'border' => 'none',
            'background' => 'transparent',
            'color' => 'inherit',
            'font-family' => 'inherit',
            'font-size' => 'inherit',
            'font-weight' => 'normal',
            'font-style' => 'normal',
            'text-align' => 'left',
            'text-decoration' => 'none',
            'text-transform' => 'none',
            'line-height' => 'normal',
            'letter-spacing' => 'normal',
            'word-spacing' => 'normal',
            'white-space' => 'normal',
            'cursor' => 'auto'
        ];
        
        foreach ($defaults as $property => $defaultValue) {
            if (!isset($styles[$property])) {
                $styles[$property] = [
                    'value' => $defaultValue,
                    'important' => false,
                    'specificity' => ['ids' => 0, 'classes' => 0, 'elements' => 0],
                    'source' => 'default'
                ];
            }
        }
        
        return $styles;
    }

    public function getComputedStyles(): array
    {
        return $this->computedStyles;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function clearStyles(): void
    {
        $this->parsedStyles = [];
        $this->computedStyles = [];
        $this->variables = [];
    }
}

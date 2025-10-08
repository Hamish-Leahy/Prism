<?php

return [
    'enabled' => true,
    'debug' => false,
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
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
        ],
        'supported_features' => [
            'width', 'height', 'min-width', 'max-width',
            'min-height', 'max-height', 'orientation',
            'aspect-ratio', 'resolution', 'color',
            'color-index', 'monochrome', 'scan', 'grid'
        ]
    ],
    'keyframes' => [
        'enabled' => true,
        'supported_properties' => [
            'transform', 'opacity', 'color', 'background-color',
            'width', 'height', 'margin', 'padding', 'border',
            'box-shadow', 'text-shadow', 'filter', 'backdrop-filter'
        ]
    ],
    'variables' => [
        'enabled' => true,
        'scope' => 'root',
        'fallback_support' => true,
        'cascade_support' => true
    ],
    'selectors' => [
        'supported_types' => [
            'element', 'id', 'class', 'attribute', 'pseudo',
            'universal', 'descendant', 'child', 'adjacent_sibling',
            'general_sibling'
        ],
        'pseudo_classes' => [
            'hover', 'focus', 'active', 'visited', 'link',
            'first-child', 'last-child', 'nth-child', 'nth-of-type',
            'only-child', 'only-of-type', 'empty', 'not',
            'target', 'enabled', 'disabled', 'checked', 'indeterminate'
        ],
        'pseudo_elements' => [
            'before', 'after', 'first-line', 'first-letter',
            'selection', 'placeholder', 'backdrop'
        ]
    ],
    'properties' => [
        'shorthand_expansion' => true,
        'vendor_prefixes' => [
            'webkit', 'moz', 'ms', 'o'
        ],
        'supported_properties' => [
            // Layout
            'display', 'position', 'top', 'right', 'bottom', 'left',
            'float', 'clear', 'z-index', 'overflow', 'overflow-x', 'overflow-y',
            'clip', 'clip-path', 'visibility', 'opacity',
            
            // Box Model
            'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
            'border', 'border-width', 'border-style', 'border-color',
            'border-top', 'border-right', 'border-bottom', 'border-left',
            'border-radius', 'border-top-left-radius', 'border-top-right-radius',
            'border-bottom-left-radius', 'border-bottom-right-radius',
            'box-sizing', 'box-shadow', 'outline', 'outline-width', 'outline-style',
            'outline-color', 'outline-offset',
            
            // Typography
            'font', 'font-family', 'font-size', 'font-weight', 'font-style',
            'font-variant', 'font-stretch', 'font-size-adjust', 'font-synthesis',
            'text-align', 'text-align-last', 'text-decoration', 'text-decoration-line',
            'text-decoration-style', 'text-decoration-color', 'text-decoration-skip',
            'text-underline-position', 'text-indent', 'text-justify', 'text-overflow',
            'text-shadow', 'text-transform', 'text-rendering', 'text-size-adjust',
            'line-height', 'letter-spacing', 'word-spacing', 'white-space',
            'word-wrap', 'word-break', 'hyphens', 'tab-size', 'writing-mode',
            'text-orientation', 'direction', 'unicode-bidi',
            
            // Colors and Backgrounds
            'color', 'background', 'background-color', 'background-image',
            'background-repeat', 'background-position', 'background-size',
            'background-attachment', 'background-clip', 'background-origin',
            'background-blend-mode', 'opacity',
            
            // Transforms and Animations
            'transform', 'transform-origin', 'transform-style', 'perspective',
            'perspective-origin', 'backface-visibility', 'transition',
            'transition-property', 'transition-duration', 'transition-timing-function',
            'transition-delay', 'animation', 'animation-name', 'animation-duration',
            'animation-timing-function', 'animation-delay', 'animation-iteration-count',
            'animation-direction', 'animation-fill-mode', 'animation-play-state',
            
            // Flexbox
            'flex', 'flex-direction', 'flex-wrap', 'flex-flow', 'justify-content',
            'align-items', 'align-self', 'align-content', 'order', 'flex-grow',
            'flex-shrink', 'flex-basis',
            
            // Grid
            'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows',
            'grid-template-areas', 'grid-gap', 'grid-column-gap', 'grid-row-gap',
            'grid-auto-columns', 'grid-auto-rows', 'grid-auto-flow',
            'grid-column', 'grid-row', 'grid-area', 'justify-items', 'justify-self',
            'align-items', 'align-self', 'place-items', 'place-self',
            
            // Filters and Effects
            'filter', 'backdrop-filter', 'mix-blend-mode', 'isolation',
            'object-fit', 'object-position', 'clip-path', 'mask', 'mask-image',
            'mask-mode', 'mask-repeat', 'mask-position', 'mask-size',
            'mask-origin', 'mask-clip', 'mask-composite',
            
            // Interactivity
            'cursor', 'pointer-events', 'user-select', 'resize', 'appearance',
            'outline', 'outline-width', 'outline-style', 'outline-color',
            'outline-offset',
            
            // Paging and Columns
            'page-break-before', 'page-break-after', 'page-break-inside',
            'break-before', 'break-after', 'break-inside', 'orphans', 'widows',
            'columns', 'column-count', 'column-width', 'column-gap', 'column-rule',
            'column-span', 'column-fill',
            
            // Lists
            'list-style', 'list-style-type', 'list-style-position', 'list-style-image',
            'counter-reset', 'counter-increment', 'content', 'quotes',
            
            // Tables
            'table-layout', 'border-collapse', 'border-spacing', 'caption-side',
            'empty-cells', 'vertical-align',
            
            // Ruby
            'ruby-align', 'ruby-merge', 'ruby-position',
            
            // Speech
            'speak', 'speak-as', 'voice-family', 'voice-rate', 'voice-pitch',
            'voice-range', 'voice-stress', 'voice-volume', 'pause', 'pause-before',
            'pause-after', 'rest', 'rest-before', 'rest-after', 'cue', 'cue-before',
            'cue-after', 'play-during', 'voice-balance', 'voice-duration',
            'voice-pitch-range', 'voice-speak-header', 'voice-speak-numeral',
            'voice-speak-punctuation', 'voice-stress', 'voice-volume'
        ]
    ],
    'units' => [
        'absolute' => ['px', 'pt', 'pc', 'in', 'cm', 'mm', 'q'],
        'relative' => ['em', 'rem', 'ex', 'ch', 'vw', 'vh', 'vmin', 'vmax', '%'],
        'angle' => ['deg', 'rad', 'grad', 'turn'],
        'time' => ['s', 'ms'],
        'frequency' => ['Hz', 'kHz'],
        'resolution' => ['dpi', 'dpcm', 'dppx']
    ],
    'colors' => [
        'named_colors' => [
            'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure',
            'beige', 'bisque', 'black', 'blanchedalmond', 'blue',
            'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
            'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson',
            'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray',
            'darkgreen', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange',
            'darkorchid', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue',
            'darkslategray', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue',
            'dimgray', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen',
            'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod',
            'gray', 'green', 'greenyellow', 'honeydew', 'hotpink',
            'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
            'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral',
            'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightpink',
            'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightsteelblue',
            'lightyellow', 'lime', 'limegreen', 'linen', 'magenta',
            'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
            'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred',
            'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite',
            'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
            'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise',
            'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink',
            'plum', 'powderblue', 'purple', 'rebeccapurple', 'red',
            'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown',
            'seagreen', 'seashell', 'sienna', 'silver', 'skyblue',
            'slateblue', 'slategray', 'snow', 'springgreen', 'steelblue',
            'tan', 'teal', 'thistle', 'tomato', 'turquoise',
            'violet', 'wheat', 'white', 'whitesmoke', 'yellow',
            'yellowgreen'
        ],
        'color_functions' => [
            'rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch'
        ]
    ],
    'performance' => [
        'max_rules_per_selector' => 1000,
        'max_declarations_per_rule' => 100,
        'max_nesting_depth' => 10,
        'enable_selector_caching' => true,
        'enable_property_caching' => true,
        'memory_limit' => '128M'
    ],
    'validation' => [
        'strict_mode' => false,
        'warn_invalid_properties' => true,
        'warn_invalid_values' => true,
        'warn_unknown_selectors' => true,
        'warn_deprecated_features' => true
    ]
];

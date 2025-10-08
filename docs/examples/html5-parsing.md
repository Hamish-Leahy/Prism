# HTML5 Parsing with DOMDocument

This document demonstrates how to use the advanced HTML5 parsing capabilities in the Prism Browser backend.

## Overview

The HTML5 Parser Service provides comprehensive parsing of HTML5 documents with support for:

- **Semantic HTML5 elements** (header, nav, main, article, section, aside, footer)
- **Metadata extraction** (Open Graph, Twitter Cards, Dublin Core, Schema.org)
- **Form analysis** with field extraction and validation
- **Media content** (images, videos, audio, iframes)
- **Accessibility information** (ARIA labels, roles, landmarks)
- **Microdata and JSON-LD** structured data
- **DOM querying** with CSS selectors and XPath
- **Performance metrics** and complexity analysis

## Basic Usage

### Simple HTML Parsing

```php
use Prism\Backend\Services\Html5ParserService;
use Monolog\Logger;

$logger = new Logger('html-parser');
$parser = new Html5ParserService([
    'preserve_whitespace' => false,
    'format_output' => false,
    'normalize_whitespace' => true
], $logger);

$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Example Page</title>
    <meta name="description" content="An example page">
</head>
<body>
    <h1>Hello World</h1>
    <p>This is a test paragraph.</p>
</body>
</html>';

$result = $parser->parseHtml($html, 'https://example.com');

// Access basic information
echo "Title: " . $result['basic_info']['title'] . "\n";
echo "Description: " . $result['basic_info']['description'] . "\n";
echo "Language: " . $result['basic_info']['language'] . "\n";
```

### Using with PrismEngine

```php
use Prism\Backend\Services\Engines\PrismEngine;

$engine = new PrismEngine([
    'html_parsing' => true,
    'preserve_whitespace' => false,
    'normalize_whitespace' => true
]);

$engine->initialize();
$engine->navigate('https://example.com');

// Get parsed data
$parsedData = $engine->getParsedData();

// Access specific information
$headings = $engine->getHeadings();
$links = $engine->getPageLinks();
$images = $engine->getImages();
$forms = $engine->getPageForms();

$engine->close();
```

## Advanced Features

### Semantic HTML5 Elements

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/about">About</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <article>
            <h1>Article Title</h1>
            <p>Article content</p>
        </article>
        <aside>
            <h2>Sidebar</h2>
            <p>Sidebar content</p>
        </aside>
    </main>
    <footer>
        <p>Footer content</p>
    </footer>
</body>
</html>';

$result = $parser->parseHtml($html);

// Access semantic elements
$headers = $result['semantic']['headers'];
$navs = $result['semantic']['navs'];
$mains = $result['semantic']['mains'];
$articles = $result['semantic']['articles'];
$asides = $result['semantic']['asides'];
$footers = $result['semantic']['footers'];

foreach ($headers as $header) {
    echo "Header ID: " . $header['id'] . "\n";
    echo "Header Text: " . $header['text'] . "\n";
}
```

### Metadata Extraction

```php
$html = '<!DOCTYPE html>
<html>
<head>
    <!-- Open Graph -->
    <meta property="og:title" content="Page Title">
    <meta property="og:description" content="Page Description">
    <meta property="og:image" content="https://example.com/image.jpg">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Twitter Title">
    
    <!-- Dublin Core -->
    <meta name="dc.title" content="DC Title">
    <meta name="dc.creator" content="Author Name">
    
    <!-- Custom Meta -->
    <meta name="custom.key" content="Custom Value">
</head>
<body></body>
</html>';

$result = $parser->parseHtml($html);

// Access different metadata types
$openGraph = $result['metadata']['open_graph'];
$twitterCard = $result['metadata']['twitter_card'];
$dublinCore = $result['metadata']['dublin_core'];
$custom = $result['metadata']['custom'];

echo "OG Title: " . $openGraph['og:title'] . "\n";
echo "Twitter Card: " . $twitterCard['twitter:card'] . "\n";
echo "DC Creator: " . $dublinCore['dc.creator'] . "\n";
echo "Custom Key: " . $custom['custom.key'] . "\n";
```

### Form Analysis

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <form action="/submit" method="post" enctype="multipart/form-data">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email">
        <input type="password" name="password" placeholder="Password" required>
        <textarea name="message" placeholder="Message"></textarea>
        <select name="country">
            <option value="us">United States</option>
            <option value="ca">Canada</option>
        </select>
        <input type="checkbox" name="newsletter" value="1">
        <input type="radio" name="gender" value="male">
        <input type="radio" name="gender" value="female">
        <button type="submit">Submit</button>
    </form>
</body>
</html>';

$result = $parser->parseHtml($html);
$forms = $result['forms'];

foreach ($forms as $form) {
    echo "Form Action: " . $form['action'] . "\n";
    echo "Form Method: " . $form['method'] . "\n";
    echo "Form Enctype: " . $form['enctype'] . "\n";
    
    foreach ($form['fields'] as $field) {
        echo "Field: " . $field['name'] . " (" . $field['type'] . ")\n";
        if ($field['required']) {
            echo "  - Required\n";
        }
        if ($field['placeholder']) {
            echo "  - Placeholder: " . $field['placeholder'] . "\n";
        }
    }
}
```

### Media Content Extraction

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <img src="image.jpg" alt="Test Image" width="100" height="100" loading="lazy">
    <video src="video.mp4" controls poster="poster.jpg" width="640" height="480">
        <source src="video.webm" type="video/webm">
        <source src="video.ogv" type="video/ogg">
    </video>
    <audio src="audio.mp3" controls>
        <source src="audio.ogg" type="audio/ogg">
    </audio>
    <iframe src="https://example.com" width="800" height="600" sandbox="allow-scripts"></iframe>
</body>
</html>';

$result = $parser->parseHtml($html);
$media = $result['media'];

// Images
foreach ($media['images'] as $image) {
    echo "Image: " . $image['src'] . "\n";
    echo "Alt: " . $image['alt'] . "\n";
    echo "Size: " . $image['width'] . "x" . $image['height'] . "\n";
}

// Videos
foreach ($media['videos'] as $video) {
    echo "Video: " . $video['src'] . "\n";
    echo "Poster: " . $video['poster'] . "\n";
    echo "Controls: " . ($video['controls'] ? 'Yes' : 'No') . "\n";
    echo "Sources: " . count($video['sources']) . "\n";
}

// Audio
foreach ($media['audio'] as $audio) {
    echo "Audio: " . $audio['src'] . "\n";
    echo "Controls: " . ($audio['controls'] ? 'Yes' : 'No') . "\n";
}

// Iframes
foreach ($media['iframes'] as $iframe) {
    echo "Iframe: " . $iframe['src'] . "\n";
    echo "Sandbox: " . $iframe['sandbox'] . "\n";
}
```

### Accessibility Analysis

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <div role="banner" aria-label="Site Header">
        <h1>Site Title</h1>
    </div>
    <nav role="navigation" aria-label="Main Navigation">
        <ul>
            <li><a href="/">Home</a></li>
        </ul>
    </nav>
    <main role="main">
        <img src="image.jpg" alt="Descriptive alt text">
        <button aria-label="Close dialog">×</button>
        <div aria-hidden="true">Hidden content</div>
    </main>
</body>
</html>';

$result = $parser->parseHtml($html);
$accessibility = $result['accessibility'];

// ARIA labels
foreach ($accessibility['aria_labels'] as $label) {
    echo "ARIA Label: " . $label['label'] . " on " . $label['tag'] . "\n";
}

// Roles
foreach ($accessibility['roles'] as $role) {
    echo "Role: " . $role['role'] . " on " . $role['tag'] . "\n";
}

// Landmarks
foreach ($accessibility['landmarks'] as $landmark) {
    echo "Landmark: " . $landmark['role'] . " (" . $landmark['tag'] . ")\n";
}

// Alt texts
foreach ($accessibility['alt_texts'] as $alt) {
    echo "Image: " . $alt['src'] . " - Alt: " . $alt['alt'] . "\n";
}
```

### Structured Data Extraction

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <!-- Microdata -->
    <div itemscope itemtype="https://schema.org/Person">
        <span itemprop="name">John Doe</span>
        <span itemprop="email">john@example.com</span>
        <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
            <span itemprop="city">New York</span>
            <span itemprop="country">USA</span>
        </div>
    </div>
    
    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Example Corp",
        "url": "https://example.com",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "123 Main St",
            "addressLocality": "New York",
            "addressCountry": "US"
        }
    }
    </script>
</body>
</html>';

$result = $parser->parseHtml($html);

// Microdata
foreach ($result['microdata'] as $item) {
    echo "Item Type: " . $item['type'] . "\n";
    foreach ($item['properties'] as $prop) {
        echo "  " . $prop['name'] . ": " . $prop['value'] . "\n";
    }
}

// JSON-LD
foreach ($result['json_ld'] as $jsonLd) {
    echo "JSON-LD Type: " . $jsonLd['@type'] . "\n";
    echo "Name: " . $jsonLd['name'] . "\n";
    echo "URL: " . $jsonLd['url'] . "\n";
}
```

## DOM Querying

### CSS Selectors

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <div id="container" class="main-content">
        <h1 class="title">Hello World</h1>
        <p class="description">This is a test</p>
        <ul class="list">
            <li class="item">Item 1</li>
            <li class="item">Item 2</li>
        </ul>
    </div>
</body>
</html>';

$parser->parseHtml($html);

// Get element by ID
$container = $parser->getElementById('container');
if ($container) {
    echo "Container found: " . $container->tagName . "\n";
}

// Get elements by class name
$items = $parser->getElementsByClassName('item');
echo "Found " . count($items) . " items\n";

// Get elements by tag name
$paragraphs = $parser->getElementsByTagName('p');
echo "Found " . count($paragraphs) . " paragraphs\n";

// CSS selector queries
$title = $parser->querySelector('.title');
if ($title) {
    echo "Title: " . $parser->getTextContent($title) . "\n";
}

$allItems = $parser->querySelectorAll('.item');
foreach ($allItems as $item) {
    echo "Item: " . $parser->getTextContent($item) . "\n";
}
```

### XPath Queries

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <div class="article">
        <h1>Article Title</h1>
        <p class="author">By John Doe</p>
        <div class="content">
            <p>First paragraph</p>
            <p>Second paragraph</p>
        </div>
    </div>
</body>
</html>';

$parser->parseHtml($html);

// XPath queries
$nodes = $parser->query('//h1');
echo "Found " . $nodes->length . " h1 elements\n";

$nodes = $parser->query('//p[@class="author"]');
echo "Found " . $nodes->length . " author paragraphs\n";

$nodes = $parser->query('//div[@class="content"]//p');
echo "Found " . $nodes->length . " content paragraphs\n";
```

## Performance Analysis

```php
$html = '<!DOCTYPE html>
<html>
<body>
    <h1>Title</h1>
    <p>Paragraph 1</p>
    <p>Paragraph 2</p>
    <div>
        <span>Nested content</span>
        <div>
            <p>Deeply nested</p>
        </div>
    </div>
</body>
</html>';

$result = $parser->parseHtml($html);
$performance = $result['performance'];

echo "DOM Elements: " . $performance['dom_elements'] . "\n";
echo "Text Nodes: " . $performance['text_nodes'] . "\n";
echo "Max Depth: " . $performance['depth'] . "\n";
echo "Complexity: " . $performance['complexity'] . "\n";
```

## Configuration Options

```php
$config = [
    'preserve_whitespace' => false,    // Remove extra whitespace
    'format_output' => false,         // Don't format HTML output
    'strict_error_checking' => false, // Allow malformed HTML
    'recover' => true,                // Recover from parsing errors
    'substitute_entities' => true,    // Convert HTML entities
    'validate_on_parse' => false,     // Don't validate against DTD
    'normalize_whitespace' => true,   // Normalize whitespace
];

$parser = new Html5ParserService($config, $logger);
```

## Error Handling

```php
try {
    $result = $parser->parseHtml($html, $url);
    
    if (empty($result)) {
        echo "No data parsed\n";
    } else {
        echo "Parsing successful\n";
    }
} catch (Exception $e) {
    echo "Parsing failed: " . $e->getMessage() . "\n";
}
```

## Best Practices

1. **Always check for empty results** before accessing parsed data
2. **Use appropriate configuration** based on your needs
3. **Handle errors gracefully** with try-catch blocks
4. **Cache parsed results** for frequently accessed pages
5. **Use specific selectors** for better performance
6. **Validate input HTML** before parsing
7. **Monitor memory usage** for large documents
8. **Use logging** for debugging parsing issues

## Integration with PrismEngine

The HTML5 parser is automatically integrated with the PrismEngine:

```php
$engine = new PrismEngine($config);
$engine->initialize();
$engine->navigate('https://example.com');

// All HTML5 parsing methods are available
$headings = $engine->getHeadings();
$links = $engine->getPageLinks();
$forms = $engine->getPageForms();
$media = $engine->getPageMedia();
$accessibility = $engine->getAccessibilityInfo();

// DOM querying
$element = $engine->querySelector('#main-content');
$elements = $engine->querySelectorAll('.article');

$engine->close();
```

This comprehensive HTML5 parsing system provides everything needed for advanced web content analysis and manipulation in the Prism Browser.

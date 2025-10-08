<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Html5ParserService;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

class Html5ParserServiceTest extends TestCase
{
    private Html5ParserService $parser;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        $config = [
            'preserve_whitespace' => false,
            'format_output' => false,
            'strict_error_checking' => false,
            'recover' => true,
            'substitute_entities' => true,
            'validate_on_parse' => false,
            'normalize_whitespace' => true,
        ];

        $logger = new Logger('test');
        $this->logHandler = new TestHandler();
        $logger->pushHandler($this->logHandler);

        $this->parser = new Html5ParserService($config, $logger);
    }

    public function testBasicHtmlParsing(): void
    {
        $html = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Test Page</title>
            <meta name="description" content="A test page">
        </head>
        <body>
            <h1>Hello World</h1>
            <p>This is a test paragraph.</p>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html, 'https://example.com');

        $this->assertArrayHasKey('basic_info', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('structure', $result);
        $this->assertArrayHasKey('content', $result);

        $this->assertEquals('Test Page', $result['basic_info']['title']);
        $this->assertEquals('A test page', $result['basic_info']['description']);
        $this->assertEquals('en', $result['basic_info']['language']);
    }

    public function testHtml5SemanticElements(): void
    {
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

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('semantic', $result);
        $this->assertNotEmpty($result['semantic']['headers']);
        $this->assertNotEmpty($result['semantic']['navs']);
        $this->assertNotEmpty($result['semantic']['mains']);
        $this->assertNotEmpty($result['semantic']['articles']);
        $this->assertNotEmpty($result['semantic']['asides']);
        $this->assertNotEmpty($result['semantic']['footers']);
    }

    public function testMetadataExtraction(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta property="og:title" content="Open Graph Title">
            <meta property="og:description" content="Open Graph Description">
            <meta property="og:image" content="https://example.com/image.jpg">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="Twitter Title">
            <meta name="dc.title" content="Dublin Core Title">
            <meta name="custom.meta" content="Custom Meta Value">
        </head>
        <body></body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('open_graph', $result['metadata']);
        $this->assertArrayHasKey('twitter_card', $result['metadata']);
        $this->assertArrayHasKey('dublin_core', $result['metadata']);
        $this->assertArrayHasKey('custom', $result['metadata']);

        $this->assertEquals('Open Graph Title', $result['metadata']['open_graph']['og:title']);
        $this->assertEquals('Twitter Title', $result['metadata']['twitter_card']['twitter:title']);
        $this->assertEquals('Dublin Core Title', $result['metadata']['dublin_core']['dc.title']);
        $this->assertEquals('Custom Meta Value', $result['metadata']['custom']['custom.meta']);
    }

    public function testFormExtraction(): void
    {
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
                <button type="submit">Submit</button>
            </form>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('forms', $result);
        $this->assertCount(1, $result['forms']);

        $form = $result['forms'][0];
        $this->assertEquals('/submit', $form['action']);
        $this->assertEquals('post', $form['method']);
        $this->assertEquals('multipart/form-data', $form['enctype']);
        $this->assertCount(5, $form['fields']);

        $usernameField = $form['fields'][0];
        $this->assertEquals('text', $usernameField['type']);
        $this->assertEquals('username', $usernameField['name']);
        $this->assertTrue($usernameField['required']);
    }

    public function testMediaExtraction(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <img src="image.jpg" alt="Test Image" width="100" height="100">
            <video src="video.mp4" controls poster="poster.jpg" width="640" height="480">
                <source src="video.webm" type="video/webm">
                <source src="video.ogv" type="video/ogg">
            </video>
            <audio src="audio.mp3" controls>
                <source src="audio.ogg" type="audio/ogg">
            </audio>
            <iframe src="https://example.com" width="800" height="600"></iframe>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('media', $result);
        $this->assertCount(1, $result['media']['images']);
        $this->assertCount(1, $result['media']['videos']);
        $this->assertCount(1, $result['media']['audio']);
        $this->assertCount(1, $result['media']['iframes']);

        $image = $result['media']['images'][0];
        $this->assertEquals('image.jpg', $image['src']);
        $this->assertEquals('Test Image', $image['alt']);
        $this->assertEquals('100', $image['width']);

        $video = $result['media']['videos'][0];
        $this->assertEquals('video.mp4', $video['src']);
        $this->assertEquals('poster.jpg', $video['poster']);
        $this->assertTrue($video['controls']);
        $this->assertCount(2, $video['sources']);
    }

    public function testLinkExtraction(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <a href="/internal">Internal Link</a>
            <a href="https://external.com">External Link</a>
            <a href="mailto:test@example.com">Email Link</a>
            <a href="tel:+1234567890">Phone Link</a>
            <a href="#section">Anchor Link</a>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html, 'https://example.com');

        $this->assertArrayHasKey('links', $result);
        $this->assertCount(5, $result['links']);

        $internalLink = $result['links'][0];
        $this->assertEquals('/internal', $internalLink['href']);
        $this->assertEquals('https://example.com/internal', $internalLink['absolute_url']);
        $this->assertEquals('internal', $internalLink['type']);

        $emailLink = $result['links'][2];
        $this->assertEquals('mailto:test@example.com', $emailLink['href']);
        $this->assertEquals('email', $emailLink['type']);
    }

    public function testAccessibilityInfo(): void
    {
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
            </main>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('accessibility', $result);
        $this->assertNotEmpty($result['accessibility']['roles']);
        $this->assertNotEmpty($result['accessibility']['landmarks']);
        $this->assertNotEmpty($result['accessibility']['aria_labels']);
        $this->assertNotEmpty($result['accessibility']['alt_texts']);

        $this->assertEquals('banner', $result['accessibility']['roles'][0]['role']);
        $this->assertEquals('Site Header', $result['accessibility']['aria_labels'][0]['label']);
    }

    public function testMicrodataExtraction(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <div itemscope itemtype="https://schema.org/Person">
                <span itemprop="name">John Doe</span>
                <span itemprop="email">john@example.com</span>
                <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                    <span itemprop="city">New York</span>
                </div>
            </div>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('microdata', $result);
        $this->assertCount(1, $result['microdata']);

        $item = $result['microdata'][0];
        $this->assertEquals('https://schema.org/Person', $item['type']);
        $this->assertCount(3, $item['properties']);

        $nameProp = $item['properties'][0];
        $this->assertEquals('name', $nameProp['name']);
        $this->assertEquals('John Doe', $nameProp['value']);
    }

    public function testJsonLdExtraction(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Organization",
                "name": "Example Corp",
                "url": "https://example.com"
            }
            </script>
        </head>
        <body></body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('json_ld', $result);
        $this->assertCount(1, $result['json_ld']);

        $jsonLd = $result['json_ld'][0];
        $this->assertEquals('https://schema.org', $jsonLd['@context']);
        $this->assertEquals('Organization', $jsonLd['@type']);
        $this->assertEquals('Example Corp', $jsonLd['name']);
    }

    public function testPerformanceInfo(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <h1>Title</h1>
            <p>Paragraph 1</p>
            <p>Paragraph 2</p>
            <div>
                <span>Nested content</span>
            </div>
        </body>
        </html>';

        $result = $this->parser->parseHtml($html);

        $this->assertArrayHasKey('performance', $result);
        $this->assertGreaterThan(0, $result['performance']['dom_elements']);
        $this->assertGreaterThan(0, $result['performance']['text_nodes']);
        $this->assertGreaterThan(0, $result['performance']['depth']);
        $this->assertGreaterThan(0, $result['performance']['complexity']);
    }

    public function testDomQueryMethods(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <div id="container" class="main-content">
                <h1 class="title">Hello World</h1>
                <p class="description">This is a test</p>
            </div>
        </body>
        </html>';

        $this->parser->parseHtml($html);

        // Test getElementById
        $element = $this->parser->getElementById('container');
        $this->assertNotNull($element);
        $this->assertEquals('div', $element->tagName);

        // Test getElementsByTagName
        $elements = $this->parser->getElementsByTagName('h1');
        $this->assertCount(1, $elements);

        // Test getElementsByClassName
        $elements = $this->parser->getElementsByClassName('title');
        $this->assertCount(1, $elements);

        // Test query method
        $nodes = $this->parser->query('//h1[@class="title"]');
        $this->assertCount(1, $nodes);
    }

    public function testHtmlOutput(): void
    {
        $html = '<!DOCTYPE html><html><body><h1>Test</h1></body></html>';
        $this->parser->parseHtml($html);

        $output = $this->parser->getHtml();
        $this->assertStringContainsString('<h1>Test</h1>', $output);
    }

    public function testElementMethods(): void
    {
        $html = '<!DOCTYPE html>
        <html>
        <body>
            <div id="test" class="container" data-value="123">
                <p>Inner content</p>
            </div>
        </body>
        </html>';

        $this->parser->parseHtml($html);
        $element = $this->parser->getElementById('test');

        $this->assertNotNull($element);

        // Test getAttribute
        $this->assertEquals('container', $this->parser->getAttribute($element, 'class'));
        $this->assertEquals('123', $this->parser->getAttribute($element, 'data-value'));

        // Test hasAttribute
        $this->assertTrue($this->parser->hasAttribute($element, 'class'));
        $this->assertFalse($this->parser->hasAttribute($element, 'nonexistent'));

        // Test getTextContent
        $this->assertEquals('Inner content', $this->parser->getTextContent($element));

        // Test getInnerHtml
        $innerHtml = $this->parser->getInnerHtml($element);
        $this->assertStringContainsString('<p>Inner content</p>', $innerHtml);
    }

    public function testErrorHandling(): void
    {
        // Test with malformed HTML
        $malformedHtml = '<html><body><div>Unclosed div<p>Paragraph</body></html>';
        
        // Should not throw exception
        $result = $this->parser->parseHtml($malformedHtml);
        $this->assertIsArray($result);
    }

    public function testLogging(): void
    {
        $html = '<!DOCTYPE html><html><body><h1>Test</h1></body></html>';
        $this->parser->parseHtml($html);

        $records = $this->logHandler->getRecords();
        $this->assertNotEmpty($records);

        $hasDebugLog = false;
        foreach ($records as $record) {
            if (strpos($record['message'], 'HTML5 parsing') !== false) {
                $hasDebugLog = true;
                break;
            }
        }

        $this->assertTrue($hasDebugLog, 'Should log parsing debug information');
    }
}

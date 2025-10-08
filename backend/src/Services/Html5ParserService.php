<?php

namespace Prism\Backend\Services;

use DOMDocument;
use DOMXPath;
use DOMElement;
use DOMNodeList;
use Monolog\Logger;

class Html5ParserService
{
    private DOMDocument $dom;
    private ?DOMXPath $xpath = null;
    private Logger $logger;
    private array $config;
    private array $parsedData = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeDom();
    }

    private function initializeDom(): void
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        
        // Configure DOMDocument for HTML5 parsing
        $this->dom->preserveWhiteSpace = $this->config['preserve_whitespace'] ?? false;
        $this->dom->formatOutput = $this->config['format_output'] ?? false;
        $this->dom->strictErrorChecking = $this->config['strict_error_checking'] ?? false;
        $this->dom->recover = $this->config['recover'] ?? true;
        $this->dom->substituteEntities = $this->config['substitute_entities'] ?? true;
        $this->dom->validateOnParse = $this->config['validate_on_parse'] ?? false;
        
        // Enable HTML5 parsing features
        libxml_use_internal_errors(true);
        libxml_clear_errors();
    }

    public function parseHtml(string $html, string $url = ''): array
    {
        try {
            $this->logger->debug("Starting HTML5 parsing", [
                'html_length' => strlen($html),
                'url' => $url
            ]);

            // Preprocess HTML for better parsing
            $html = $this->preprocessHtml($html);
            
            // Load HTML with HTML5 support
            $this->dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
            
            // Clear any libxml errors
            $this->clearLibxmlErrors();
            
            // Initialize XPath
            $this->xpath = new DOMXPath($this->dom);
            
            // Register HTML5 namespaces
            $this->registerNamespaces();
            
            // Parse the document
            $this->parsedData = $this->parseDocument($url);
            
            $this->logger->debug("HTML5 parsing completed", [
                'elements_found' => count($this->parsedData['elements'] ?? []),
                'links_found' => count($this->parsedData['links'] ?? []),
                'images_found' => count($this->parsedData['images'] ?? [])
            ]);

            return $this->parsedData;
            
        } catch (\Exception $e) {
            $this->logger->error("HTML5 parsing failed", [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            throw new \RuntimeException("HTML5 parsing failed: " . $e->getMessage());
        }
    }

    private function preprocessHtml(string $html): string
    {
        // Add HTML5 doctype if missing
        if (!preg_match('/<!DOCTYPE\s+html/i', $html)) {
            $html = '<!DOCTYPE html>' . "\n" . $html;
        }

        // Fix common HTML5 issues
        $html = $this->fixHtml5Issues($html);
        
        // Normalize whitespace
        if ($this->config['normalize_whitespace'] ?? true) {
            $html = preg_replace('/\s+/', ' ', $html);
        }

        return $html;
    }

    private function fixHtml5Issues(string $html): string
    {
        // Fix self-closing tags that shouldn't be self-closing in HTML5
        $html = preg_replace('/<(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)\s+[^>]*\/>/i', '<$1$2>', $html);
        
        // Fix void elements
        $voidElements = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        foreach ($voidElements as $element) {
            $html = preg_replace('/<' . $element . '([^>]*?)\s*\/>/i', '<' . $element . '$1>', $html);
        }

        // Fix common HTML5 semantic elements
        $html = preg_replace('/<(article|aside|details|figcaption|figure|footer|header|main|mark|nav|section|summary|time)\s+[^>]*\/>/i', '<$1$2></$1>', $html);

        return $html;
    }

    private function registerNamespaces(): void
    {
        // Register common namespaces for XPath queries
        $this->xpath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
        $this->xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        $this->xpath->registerNamespace('math', 'http://www.w3.org/1998/Math/MathML');
    }

    private function parseDocument(string $url): array
    {
        return [
            'basic_info' => $this->extractBasicInfo(),
            'metadata' => $this->extractMetadata(),
            'structure' => $this->extractStructure(),
            'content' => $this->extractContent(),
            'forms' => $this->extractForms(),
            'media' => $this->extractMedia(),
            'links' => $this->extractLinks($url),
            'scripts' => $this->extractScripts(),
            'styles' => $this->extractStyles(),
            'accessibility' => $this->extractAccessibilityInfo(),
            'semantic' => $this->extractSemanticElements(),
            'microdata' => $this->extractMicrodata(),
            'json_ld' => $this->extractJsonLd(),
            'performance' => $this->extractPerformanceInfo()
        ];
    }

    private function extractBasicInfo(): array
    {
        $info = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'author' => '',
            'viewport' => '',
            'charset' => 'utf-8',
            'language' => 'en',
            'canonical' => '',
            'robots' => ''
        ];

        // Title
        $titleNodes = $this->xpath->query('//title');
        if ($titleNodes->length > 0) {
            $info['title'] = trim($titleNodes->item(0)->textContent);
        }

        // Meta tags
        $metaNodes = $this->xpath->query('//meta[@name or @property]');
        foreach ($metaNodes as $meta) {
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            
            switch (strtolower($name)) {
                case 'description':
                    $info['description'] = $content;
                    break;
                case 'keywords':
                    $info['keywords'] = $content;
                    break;
                case 'author':
                    $info['author'] = $content;
                    break;
                case 'viewport':
                    $info['viewport'] = $content;
                    break;
                case 'robots':
                    $info['robots'] = $content;
                    break;
            }
        }

        // Charset
        $charsetNodes = $this->xpath->query('//meta[@charset]');
        if ($charsetNodes->length > 0) {
            $info['charset'] = $charsetNodes->item(0)->getAttribute('charset');
        }

        // Language
        $htmlNodes = $this->xpath->query('//html[@lang]');
        if ($htmlNodes->length > 0) {
            $info['language'] = $htmlNodes->item(0)->getAttribute('lang');
        }

        // Canonical URL
        $canonicalNodes = $this->xpath->query('//link[@rel="canonical"]');
        if ($canonicalNodes->length > 0) {
            $info['canonical'] = $canonicalNodes->item(0)->getAttribute('href');
        }

        return $info;
    }

    private function extractMetadata(): array
    {
        $metadata = [
            'open_graph' => [],
            'twitter_card' => [],
            'schema_org' => [],
            'dublin_core' => [],
            'custom' => []
        ];

        $metaNodes = $this->xpath->query('//meta[@property or @name]');
        foreach ($metaNodes as $meta) {
            $property = $meta->getAttribute('property');
            $name = $meta->getAttribute('name');
            $content = $meta->getAttribute('content');
            
            $key = $property ?: $name;
            
            if (strpos($key, 'og:') === 0) {
                $metadata['open_graph'][$key] = $content;
            } elseif (strpos($key, 'twitter:') === 0) {
                $metadata['twitter_card'][$key] = $content;
            } elseif (strpos($key, 'dc.') === 0) {
                $metadata['dublin_core'][$key] = $content;
            } elseif (strpos($key, 'schema.') === 0) {
                $metadata['schema_org'][$key] = $content;
            } else {
                $metadata['custom'][$key] = $content;
            }
        }

        return $metadata;
    }

    private function extractStructure(): array
    {
        $structure = [
            'headings' => [],
            'sections' => [],
            'navigation' => [],
            'main_content' => [],
            'sidebar' => [],
            'footer' => [],
            'header' => []
        ];

        // Headings (h1-h6)
        for ($i = 1; $i <= 6; $i++) {
            $headingNodes = $this->xpath->query("//h$i");
            foreach ($headingNodes as $heading) {
                $structure['headings'][] = [
                    'level' => $i,
                    'text' => trim($heading->textContent),
                    'id' => $heading->getAttribute('id'),
                    'class' => $heading->getAttribute('class')
                ];
            }
        }

        // HTML5 semantic elements
        $semanticElements = ['header', 'nav', 'main', 'section', 'article', 'aside', 'footer'];
        foreach ($semanticElements as $element) {
            $nodes = $this->xpath->query("//$element");
            foreach ($nodes as $node) {
                $structure[$element][] = [
                    'text' => trim($node->textContent),
                    'id' => $node->getAttribute('id'),
                    'class' => $node->getAttribute('class'),
                    'role' => $node->getAttribute('role')
                ];
            }
        }

        return $structure;
    }

    private function extractContent(): array
    {
        $content = [
            'text_content' => '',
            'paragraphs' => [],
            'lists' => [],
            'tables' => [],
            'blockquotes' => [],
            'code_blocks' => []
        ];

        // Extract all text content
        $content['text_content'] = trim($this->dom->textContent);

        // Paragraphs
        $paragraphNodes = $this->xpath->query('//p');
        foreach ($paragraphNodes as $p) {
            $text = trim($p->textContent);
            if (!empty($text)) {
                $content['paragraphs'][] = $text;
            }
        }

        // Lists
        $listNodes = $this->xpath->query('//ul | //ol | //dl');
        foreach ($listNodes as $list) {
            $listType = $list->tagName;
            $items = [];
            
            $itemNodes = $this->xpath->query('.//li | .//dt | .//dd', $list);
            foreach ($itemNodes as $item) {
                $items[] = trim($item->textContent);
            }
            
            $content['lists'][] = [
                'type' => $listType,
                'items' => $items
            ];
        }

        // Tables
        $tableNodes = $this->xpath->query('//table');
        foreach ($tableNodes as $table) {
            $rows = [];
            $rowNodes = $this->xpath->query('.//tr', $table);
            foreach ($rowNodes as $row) {
                $cells = [];
                $cellNodes = $this->xpath->query('.//td | .//th', $row);
                foreach ($cellNodes as $cell) {
                    $cells[] = trim($cell->textContent);
                }
                $rows[] = $cells;
            }
            $content['tables'][] = $rows;
        }

        // Blockquotes
        $blockquoteNodes = $this->xpath->query('//blockquote');
        foreach ($blockquoteNodes as $blockquote) {
            $content['blockquotes'][] = trim($blockquote->textContent);
        }

        // Code blocks
        $codeNodes = $this->xpath->query('//pre | //code');
        foreach ($codeNodes as $code) {
            $content['code_blocks'][] = trim($code->textContent);
        }

        return $content;
    }

    private function extractForms(): array
    {
        $forms = [];
        $formNodes = $this->xpath->query('//form');
        
        foreach ($formNodes as $index => $form) {
            $formData = [
                'id' => $form->getAttribute('id'),
                'class' => $form->getAttribute('class'),
                'action' => $form->getAttribute('action'),
                'method' => $form->getAttribute('method') ?: 'get',
                'enctype' => $form->getAttribute('enctype'),
                'fields' => []
            ];

            // Extract form fields
            $fieldNodes = $this->xpath->query('.//input | .//textarea | .//select', $form);
            foreach ($fieldNodes as $field) {
                $fieldData = [
                    'type' => $field->getAttribute('type') ?: 'text',
                    'name' => $field->getAttribute('name'),
                    'id' => $field->getAttribute('id'),
                    'class' => $field->getAttribute('class'),
                    'placeholder' => $field->getAttribute('placeholder'),
                    'required' => $field->hasAttribute('required'),
                    'value' => $field->getAttribute('value')
                ];

                if ($field->tagName === 'textarea') {
                    $fieldData['value'] = trim($field->textContent);
                }

                $formData['fields'][] = $fieldData;
            }

            $forms[] = $formData;
        }

        return $forms;
    }

    private function extractMedia(): array
    {
        $media = [
            'images' => [],
            'videos' => [],
            'audio' => [],
            'iframes' => []
        ];

        // Images
        $imageNodes = $this->xpath->query('//img');
        foreach ($imageNodes as $img) {
            $media['images'][] = [
                'src' => $img->getAttribute('src'),
                'alt' => $img->getAttribute('alt'),
                'title' => $img->getAttribute('title'),
                'width' => $img->getAttribute('width'),
                'height' => $img->getAttribute('height'),
                'class' => $img->getAttribute('class'),
                'loading' => $img->getAttribute('loading')
            ];
        }

        // Videos
        $videoNodes = $this->xpath->query('//video');
        foreach ($videoNodes as $video) {
            $sources = [];
            $sourceNodes = $this->xpath->query('.//source', $video);
            foreach ($sourceNodes as $source) {
                $sources[] = [
                    'src' => $source->getAttribute('src'),
                    'type' => $source->getAttribute('type')
                ];
            }

            $media['videos'][] = [
                'src' => $video->getAttribute('src'),
                'poster' => $video->getAttribute('poster'),
                'width' => $video->getAttribute('width'),
                'height' => $video->getAttribute('height'),
                'controls' => $video->hasAttribute('controls'),
                'autoplay' => $video->hasAttribute('autoplay'),
                'loop' => $video->hasAttribute('loop'),
                'muted' => $video->hasAttribute('muted'),
                'sources' => $sources
            ];
        }

        // Audio
        $audioNodes = $this->xpath->query('//audio');
        foreach ($audioNodes as $audio) {
            $sources = [];
            $sourceNodes = $this->xpath->query('.//source', $audio);
            foreach ($sourceNodes as $source) {
                $sources[] = [
                    'src' => $source->getAttribute('src'),
                    'type' => $source->getAttribute('type')
                ];
            }

            $media['audio'][] = [
                'src' => $audio->getAttribute('src'),
                'controls' => $audio->hasAttribute('controls'),
                'autoplay' => $audio->hasAttribute('autoplay'),
                'loop' => $audio->hasAttribute('loop'),
                'muted' => $audio->hasAttribute('muted'),
                'sources' => $sources
            ];
        }

        // Iframes
        $iframeNodes = $this->xpath->query('//iframe');
        foreach ($iframeNodes as $iframe) {
            $media['iframes'][] = [
                'src' => $iframe->getAttribute('src'),
                'title' => $iframe->getAttribute('title'),
                'width' => $iframe->getAttribute('width'),
                'height' => $iframe->getAttribute('height'),
                'frameborder' => $iframe->getAttribute('frameborder'),
                'sandbox' => $iframe->getAttribute('sandbox'),
                'allowfullscreen' => $iframe->hasAttribute('allowfullscreen')
            ];
        }

        return $media;
    }

    private function extractLinks(string $baseUrl): array
    {
        $links = [];
        $linkNodes = $this->xpath->query('//a[@href]');
        
        foreach ($linkNodes as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            
            // Resolve relative URLs
            $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            
            $links[] = [
                'href' => $href,
                'absolute_url' => $absoluteUrl,
                'text' => $text,
                'title' => $link->getAttribute('title'),
                'target' => $link->getAttribute('target'),
                'rel' => $link->getAttribute('rel'),
                'type' => $this->getLinkType($href)
            ];
        }

        return $links;
    }

    private function extractScripts(): array
    {
        $scripts = [];
        $scriptNodes = $this->xpath->query('//script');
        
        foreach ($scriptNodes as $script) {
            $scriptData = [
                'src' => $script->getAttribute('src'),
                'type' => $script->getAttribute('type') ?: 'text/javascript',
                'async' => $script->hasAttribute('async'),
                'defer' => $script->hasAttribute('defer'),
                'content' => trim($script->textContent)
            ];

            $scripts[] = $scriptData;
        }

        return $scripts;
    }

    private function extractStyles(): array
    {
        $styles = [
            'inline' => [],
            'external' => []
        ];

        // Inline styles
        $styleNodes = $this->xpath->query('//style');
        foreach ($styleNodes as $style) {
            $styles['inline'][] = [
                'type' => $style->getAttribute('type') ?: 'text/css',
                'media' => $style->getAttribute('media'),
                'content' => trim($style->textContent)
            ];
        }

        // External stylesheets
        $linkNodes = $this->xpath->query('//link[@rel="stylesheet"]');
        foreach ($linkNodes as $link) {
            $styles['external'][] = [
                'href' => $link->getAttribute('href'),
                'type' => $link->getAttribute('type') ?: 'text/css',
                'media' => $link->getAttribute('media'),
                'title' => $link->getAttribute('title')
            ];
        }

        return $styles;
    }

    private function extractAccessibilityInfo(): array
    {
        $accessibility = [
            'aria_labels' => [],
            'roles' => [],
            'landmarks' => [],
            'headings_structure' => [],
            'alt_texts' => []
        ];

        // ARIA labels
        $ariaNodes = $this->xpath->query('//*[@aria-label]');
        foreach ($ariaNodes as $node) {
            $accessibility['aria_labels'][] = [
                'tag' => $node->tagName,
                'label' => $node->getAttribute('aria-label'),
                'id' => $node->getAttribute('id'),
                'class' => $node->getAttribute('class')
            ];
        }

        // Roles
        $roleNodes = $this->xpath->query('//*[@role]');
        foreach ($roleNodes as $node) {
            $accessibility['roles'][] = [
                'tag' => $node->tagName,
                'role' => $node->getAttribute('role'),
                'id' => $node->getAttribute('id'),
                'class' => $node->getAttribute('class')
            ];
        }

        // Landmarks
        $landmarkNodes = $this->xpath->query('//*[@role="banner" or @role="navigation" or @role="main" or @role="complementary" or @role="contentinfo"]');
        foreach ($landmarkNodes as $node) {
            $accessibility['landmarks'][] = [
                'tag' => $node->tagName,
                'role' => $node->getAttribute('role'),
                'id' => $node->getAttribute('id'),
                'class' => $node->getAttribute('class')
            ];
        }

        // Alt texts for images
        $imageNodes = $this->xpath->query('//img[@alt]');
        foreach ($imageNodes as $img) {
            $accessibility['alt_texts'][] = [
                'src' => $img->getAttribute('src'),
                'alt' => $img->getAttribute('alt')
            ];
        }

        return $accessibility;
    }

    private function extractSemanticElements(): array
    {
        $semantic = [
            'articles' => [],
            'sections' => [],
            'asides' => [],
            'headers' => [],
            'footers' => [],
            'navs' => [],
            'mains' => []
        ];

        $semanticTags = ['article', 'section', 'aside', 'header', 'footer', 'nav', 'main'];
        
        foreach ($semanticTags as $tag) {
            $nodes = $this->xpath->query("//$tag");
            foreach ($nodes as $node) {
                $semantic[$tag . 's'][] = [
                    'id' => $node->getAttribute('id'),
                    'class' => $node->getAttribute('class'),
                    'role' => $node->getAttribute('role'),
                    'text' => trim($node->textContent),
                    'html' => $this->dom->saveHTML($node)
                ];
            }
        }

        return $semantic;
    }

    private function extractMicrodata(): array
    {
        $microdata = [];
        $itemNodes = $this->xpath->query('//*[@itemscope]');
        
        foreach ($itemNodes as $item) {
            $itemData = [
                'type' => $item->getAttribute('itemtype'),
                'properties' => []
            ];

            $propNodes = $this->xpath->query('.//*[@itemprop]', $item);
            foreach ($propNodes as $prop) {
                $itemData['properties'][] = [
                    'name' => $prop->getAttribute('itemprop'),
                    'value' => trim($prop->textContent),
                    'content' => $prop->getAttribute('content')
                ];
            }

            $microdata[] = $itemData;
        }

        return $microdata;
    }

    private function extractJsonLd(): array
    {
        $jsonLd = [];
        $scriptNodes = $this->xpath->query('//script[@type="application/ld+json"]');
        
        foreach ($scriptNodes as $script) {
            $content = trim($script->textContent);
            if (!empty($content)) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $jsonLd[] = $decoded;
                }
            }
        }

        return $jsonLd;
    }

    private function extractPerformanceInfo(): array
    {
        return [
            'dom_elements' => $this->dom->getElementsByTagName('*')->length,
            'text_nodes' => $this->countTextNodes(),
            'depth' => $this->calculateMaxDepth(),
            'complexity' => $this->calculateComplexity()
        ];
    }

    private function countTextNodes(): int
    {
        $textNodes = $this->xpath->query('//text()');
        return $textNodes->length;
    }

    private function calculateMaxDepth(): int
    {
        $maxDepth = 0;
        $this->calculateNodeDepth($this->dom->documentElement, 0, $maxDepth);
        return $maxDepth;
    }

    private function calculateNodeDepth($node, $currentDepth, &$maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            $maxDepth = $currentDepth;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->calculateNodeDepth($child, $currentDepth + 1, $maxDepth);
            }
        }
    }

    private function calculateComplexity(): int
    {
        // Simple complexity calculation based on element count and nesting
        $elementCount = $this->dom->getElementsByTagName('*')->length;
        $maxDepth = $this->calculateMaxDepth();
        return $elementCount * $maxDepth;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($baseUrl)) {
            return $url;
        }

        // If URL is already absolute, return as-is
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (!$base) {
            return $url;
        }

        if (strpos($url, '/') === 0) {
            // Absolute path
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $url;
        } else {
            // Relative path
            $basePath = isset($base['path']) ? dirname($base['path']) : '/';
            if ($basePath === '.') {
                $basePath = '/';
            }
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $basePath . '/' . $url;
        }
    }

    private function getLinkType(string $url): string
    {
        if (preg_match('/^mailto:/', $url)) {
            return 'email';
        } elseif (preg_match('/^tel:/', $url)) {
            return 'phone';
        } elseif (preg_match('/^#/', $url)) {
            return 'anchor';
        } elseif (preg_match('/^https?:\/\//', $url)) {
            return 'external';
        } else {
            return 'internal';
        }
    }

    private function clearLibxmlErrors(): void
    {
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            $this->logger->debug("LibXML errors cleared", [
                'error_count' => count($errors)
            ]);
            libxml_clear_errors();
        }
    }

    public function getDom(): DOMDocument
    {
        return $this->dom;
    }

    public function getXPath(): ?DOMXPath
    {
        return $this->xpath;
    }

    public function query(string $xpath): DOMNodeList
    {
        if (!$this->xpath) {
            throw new \RuntimeException('XPath not initialized. Parse HTML first.');
        }
        return $this->xpath->query($xpath);
    }

    public function getElementById(string $id): ?DOMElement
    {
        return $this->dom->getElementById($id);
    }

    public function getElementsByTagName(string $tagName): DOMNodeList
    {
        return $this->dom->getElementsByTagName($tagName);
    }

    public function getElementsByClassName(string $className): DOMNodeList
    {
        if (!$this->xpath) {
            throw new \RuntimeException('XPath not initialized. Parse HTML first.');
        }
        return $this->xpath->query("//*[contains(@class, '$className')]");
    }

    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    public function getHtml(): string
    {
        return $this->dom->saveHTML();
    }

    public function getInnerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $this->dom->saveHTML($child);
        }
        return $html;
    }

    public function getTextContent(DOMElement $element): string
    {
        return trim($element->textContent);
    }

    public function getAttribute(DOMElement $element, string $name): ?string
    {
        return $element->getAttribute($name) ?: null;
    }

    public function hasAttribute(DOMElement $element, string $name): bool
    {
        return $element->hasAttribute($name);
    }
}

<?php

namespace Prism\Backend\Services;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;

/**
 * PrismSearchEngine - Custom search engine for Prism Browser
 */
class PrismSearchEngine
{
    private LoggerInterface $logger;
    private Client $httpClient;
    private array $config;
    private array $searchIndex;
    private bool $initialized = false;

    // Popular domains for quick access
    private array $popularDomains = [
        'google' => 'https://www.google.com',
        'youtube' => 'https://www.youtube.com',
        'github' => 'https://www.github.com',
        'twitter' => 'https://www.twitter.com',
        'reddit' => 'https://www.reddit.com',
        'wikipedia' => 'https://www.wikipedia.org',
        'stackoverflow' => 'https://stackoverflow.com',
        'amazon' => 'https://www.amazon.com',
        'facebook' => 'https://www.facebook.com',
        'instagram' => 'https://www.instagram.com',
        'linkedin' => 'https://www.linkedin.com',
        'netflix' => 'https://www.netflix.com',
        'apple' => 'https://www.apple.com',
        'microsoft' => 'https://www.microsoft.com'
    ];

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'index_path' => __DIR__ . '/../../storage/search_index/',
            'max_results' => 20,
            'cache_duration' => 3600
        ], $config);

        $this->httpClient = new Client([
            'timeout' => 10,
            'verify' => false
        ]);

        $this->searchIndex = [];
    }

    public function initialize(): bool
    {
        try {
            // Ensure index directory exists
            $indexPath = $this->config['index_path'];
            if (!is_dir($indexPath)) {
                mkdir($indexPath, 0755, true);
            }

            // Load search index
            $this->loadSearchIndex();

            $this->initialized = true;
            $this->logger->info('PrismSearchEngine initialized successfully');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize PrismSearchEngine: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform a search query
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);
        if (empty($query)) {
            return ['success' => false, 'error' => 'Empty query'];
        }

        $options = array_merge([
            'max_results' => $this->config['max_results'],
            'include_web' => true,
            'include_suggestions' => true
        ], $options);

        try {
            $results = [];

            // Check for direct domain match
            $directMatch = $this->checkDirectMatch($query);
            if ($directMatch) {
                $results['direct_match'] = $directMatch;
            }

            // Search indexed content
            $indexedResults = $this->searchIndex($query, $options['max_results']);
            if (!empty($indexedResults)) {
                $results['indexed'] = $indexedResults;
            }

            // Generate suggestions
            if ($options['include_suggestions']) {
                $results['suggestions'] = $this->generateSuggestions($query);
            }

            // Web search fallback (use DuckDuckGo API)
            if ($options['include_web'] && empty($indexedResults)) {
                $results['web'] = $this->performWebSearch($query, $options['max_results']);
            }

            return [
                'success' => true,
                'query' => $query,
                'results' => $results,
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Search failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check for direct domain matches
     */
    private function checkDirectMatch(string $query): ?array
    {
        $queryLower = strtolower($query);
        
        // Check popular domains
        foreach ($this->popularDomains as $keyword => $url) {
            if (strpos($queryLower, $keyword) !== false) {
                return [
                    'type' => 'popular_site',
                    'title' => ucfirst($keyword),
                    'url' => $url,
                    'description' => 'Popular website'
                ];
            }
        }

        // Check if it looks like a URL
        if (preg_match('/^([a-z0-9\-]+\.)+[a-z]{2,}$/i', $query)) {
            return [
                'type' => 'url',
                'title' => $query,
                'url' => 'https://' . $query,
                'description' => 'Direct URL'
            ];
        }

        return null;
    }

    /**
     * Search the local index
     */
    private function searchIndex(string $query, int $maxResults): array
    {
        $results = [];
        $queryLower = strtolower($query);
        $queryWords = explode(' ', $queryLower);

        foreach ($this->searchIndex as $item) {
            $score = 0;
            $titleLower = strtolower($item['title'] ?? '');
            $descLower = strtolower($item['description'] ?? '');
            $contentLower = strtolower($item['content'] ?? '');

            // Calculate relevance score
            foreach ($queryWords as $word) {
                if (empty($word)) continue;
                
                // Title matches get highest score
                if (strpos($titleLower, $word) !== false) {
                    $score += 10;
                }
                
                // Description matches
                if (strpos($descLower, $word) !== false) {
                    $score += 5;
                }
                
                // Content matches
                if (strpos($contentLower, $word) !== false) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $results[] = array_merge($item, ['score' => $score]);
            }
        }

        // Sort by score
        usort($results, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($results, 0, $maxResults);
    }

    /**
     * Generate search suggestions
     */
    private function generateSuggestions(string $query): array
    {
        $suggestions = [];
        $queryLower = strtolower($query);

        // Popular site suggestions
        foreach ($this->popularDomains as $keyword => $url) {
            if (strpos($keyword, $queryLower) !== false) {
                $suggestions[] = $keyword;
            }
        }

        // Add common search patterns
        $patterns = [
            'how to ' . $query,
            'what is ' . $query,
            $query . ' tutorial',
            $query . ' download',
            $query . ' online'
        ];

        $suggestions = array_merge($suggestions, array_slice($patterns, 0, 5 - count($suggestions)));

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Perform web search using external API
     */
    private function performWebSearch(string $query, int $maxResults): array
    {
        try {
            // Use DuckDuckGo Instant Answer API
            $response = $this->httpClient->get('https://api.duckduckgo.com/', [
                'query' => [
                    'q' => $query,
                    'format' => 'json',
                    'no_html' => 1
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $results = [];

            // Parse DuckDuckGo results
            if (!empty($data['AbstractText'])) {
                $results[] = [
                    'title' => $data['Heading'] ?? $query,
                    'url' => $data['AbstractURL'] ?? '',
                    'description' => $data['AbstractText'],
                    'source' => $data['AbstractSource'] ?? 'Web'
                ];
            }

            // Add related topics
            if (!empty($data['RelatedTopics'])) {
                foreach (array_slice($data['RelatedTopics'], 0, $maxResults - 1) as $topic) {
                    if (isset($topic['Text']) && isset($topic['FirstURL'])) {
                        $results[] = [
                            'title' => $topic['Text'],
                            'url' => $topic['FirstURL'],
                            'description' => $topic['Text'],
                            'source' => 'DuckDuckGo'
                        ];
                    }
                }
            }

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Web search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Index a page for search
     */
    public function indexPage(string $url, string $title, string $content, array $metadata = []): bool
    {
        try {
            $pageId = md5($url);
            
            $indexEntry = [
                'id' => $pageId,
                'url' => $url,
                'title' => $title,
                'description' => substr(strip_tags($content), 0, 200),
                'content' => strip_tags($content),
                'indexed_at' => time(),
                'metadata' => $metadata
            ];

            $this->searchIndex[$pageId] = $indexEntry;
            $this->saveSearchIndex();

            $this->logger->info('Indexed page: ' . $url);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to index page: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get search statistics
     */
    public function getStats(): array
    {
        return [
            'indexed_pages' => count($this->searchIndex),
            'popular_domains' => count($this->popularDomains),
            'initialized' => $this->initialized
        ];
    }

    /**
     * Load search index from disk
     */
    private function loadSearchIndex(): void
    {
        $indexFile = $this->config['index_path'] . 'index.json';
        
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);
            $this->searchIndex = json_decode($content, true) ?? [];
            $this->logger->info('Loaded ' . count($this->searchIndex) . ' indexed pages');
        } else {
            $this->searchIndex = [];
        }
    }

    /**
     * Save search index to disk
     */
    private function saveSearchIndex(): void
    {
        $indexFile = $this->config['index_path'] . 'index.json';
        file_put_contents($indexFile, json_encode($this->searchIndex, JSON_PRETTY_PRINT));
    }

    /**
     * Clear search index
     */
    public function clearIndex(): bool
    {
        $this->searchIndex = [];
        $this->saveSearchIndex();
        return true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}


<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AIAssistantService
{
    private Logger $logger;
    private Client $httpClient;
    private array $config;
    private array $conversationHistory = [];
    private array $userPreferences = [];
    private array $contextData = [];
    private bool $isEnabled = false;
    private array $aiProviders = [];
    private array $assistantCapabilities = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->initializeAICapabilities();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing AI Assistant Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("AI Assistant Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->loadUserPreferences();
            $this->initializeAIProviders();
            
            $this->logger->info("AI Assistant Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("AI Assistant Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function processQuery(string $query, array $context = []): array
    {
        if (!$this->isEnabled) {
            return ['error' => 'AI Assistant is disabled'];
        }

        try {
            $this->addToConversationHistory('user', $query);
            
            // Analyze the query intent
            $intent = $this->analyzeIntent($query);
            
            // Get relevant context
            $enhancedContext = $this->enhanceContext($context);
            
            // Process based on intent
            $response = $this->processIntent($intent, $query, $enhancedContext);
            
            $this->addToConversationHistory('assistant', $response['message']);

        return $response;
        } catch (\Exception $e) {
            $this->logger->error("Error processing AI query", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Sorry, I encountered an error processing your request.',
                'message' => 'Please try again or rephrase your question.'
            ];
        }
    }

    public function getSmartSuggestions(string $url, array $pageData = []): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        try {
            $suggestions = [];
            
            // Analyze page content for suggestions
            $contentAnalysis = $this->analyzePageContent($pageData);
            
            // Generate contextual suggestions
            $suggestions = array_merge($suggestions, $this->generateContentSuggestions($contentAnalysis));
            $suggestions = array_merge($suggestions, $this->generateNavigationSuggestions($url, $contentAnalysis));
            $suggestions = array_merge($suggestions, $this->generateProductivitySuggestions($url, $contentAnalysis));
            
            return array_slice($suggestions, 0, 5); // Return top 5 suggestions
        } catch (\Exception $e) {
            $this->logger->error("Error generating smart suggestions", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getReadingTimeEstimate(string $content): array
    {
        $wordCount = str_word_count(strip_tags($content));
        $averageWordsPerMinute = 200; // Average reading speed
        $readingTimeMinutes = ceil($wordCount / $averageWordsPerMinute);
        
        return [
            'word_count' => $wordCount,
            'reading_time_minutes' => $readingTimeMinutes,
            'reading_time_text' => $this->formatReadingTime($readingTimeMinutes)
        ];
    }

    public function summarizeContent(string $content, int $maxLength = 200): string
    {
        if (!$this->isEnabled) {
            return $this->basicSummarize($content, $maxLength);
        }

        try {
            // Use AI for advanced summarization
            $summary = $this->callAIService('summarize', [
                'content' => $content,
                'max_length' => $maxLength
            ]);
            
            return $summary['summary'] ?? $this->basicSummarize($content, $maxLength);
        } catch (\Exception $e) {
            $this->logger->warning("AI summarization failed, using basic method", [
                'error' => $e->getMessage()
            ]);
            return $this->basicSummarize($content, $maxLength);
        }
    }

    public function detectLanguage(string $text): string
    {
        // Simple language detection based on common patterns
        $patterns = [
            'en' => ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'],
            'es' => ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le'],
            'fr' => ['le', 'de', 'et', 'à', 'un', 'il', 'être', 'et', 'en', 'avoir', 'que', 'pour', 'dans'],
            'de' => ['der', 'die', 'das', 'und', 'in', 'den', 'von', 'zu', 'dem', 'mit', 'sich', 'nicht'],
            'it' => ['il', 'di', 'che', 'e', 'la', 'per', 'in', 'un', 'con', 'da', 'a', 'sono', 'al'],
            'pt' => ['o', 'de', 'e', 'do', 'da', 'em', 'um', 'para', 'com', 'não', 'uma', 'os', 'no'],
            'ru' => ['и', 'в', 'не', 'на', 'я', 'быть', 'с', 'со', 'как', 'а', 'по', 'но', 'они'],
            'zh' => ['的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个'],
            'ja' => ['の', 'に', 'は', 'を', 'た', 'が', 'で', 'て', 'と', 'し', 'れ', 'さ', 'ある']
        ];

        $text = strtolower($text);
        $scores = [];

        foreach ($patterns as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                $score += substr_count($text, ' ' . $word . ' ');
            }
            $scores[$lang] = $score;
        }

        $detectedLang = array_keys($scores, max($scores))[0];
        return $scores[$detectedLang] > 0 ? $detectedLang : 'en';
    }

    public function translateText(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): array
    {
        if (!$this->isEnabled) {
            return ['error' => 'Translation service not available'];
        }

        try {
            if ($sourceLanguage === 'auto') {
                $sourceLanguage = $this->detectLanguage($text);
            }

            if ($sourceLanguage === $targetLanguage) {
                return [
                    'translated_text' => $text,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'confidence' => 1.0
                ];
            }

            $translation = $this->callAIService('translate', [
                'text' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage
            ]);

            return [
                'translated_text' => $translation['translated_text'] ?? $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'confidence' => $translation['confidence'] ?? 0.8
            ];
        } catch (\Exception $e) {
            $this->logger->error("Translation failed", [
                'error' => $e->getMessage(),
                'source' => $sourceLanguage,
                'target' => $targetLanguage
            ]);
            
            return ['error' => 'Translation failed'];
        }
    }

    public function getAccessibilitySuggestions(array $pageData): array
    {
        $suggestions = [];
        
        // Check for missing alt text
        if (isset($pageData['images']) && is_array($pageData['images'])) {
            foreach ($pageData['images'] as $image) {
                if (empty($image['alt'])) {
                    $suggestions[] = [
                        'type' => 'accessibility',
                        'priority' => 'high',
                        'message' => 'Image missing alt text',
                        'element' => $image['src'] ?? 'unknown',
                        'suggestion' => 'Add descriptive alt text for screen readers'
                    ];
                }
            }
        }

        // Check for heading structure
        if (isset($pageData['headings']) && is_array($pageData['headings'])) {
            $headingLevels = array_column($pageData['headings'], 'level');
            if (!empty($headingLevels) && min($headingLevels) > 1) {
                $suggestions[] = [
                    'type' => 'accessibility',
                    'priority' => 'medium',
                    'message' => 'Missing H1 heading',
                    'suggestion' => 'Add a main H1 heading to establish page hierarchy'
                ];
            }
        }

        // Check for color contrast (simplified)
        if (isset($pageData['colors'])) {
            $suggestions[] = [
                'type' => 'accessibility',
                'priority' => 'medium',
                'message' => 'Check color contrast ratios',
                'suggestion' => 'Ensure text has sufficient contrast against background colors'
            ];
        }

        return $suggestions;
    }

    public function getSecurityAnalysis(string $url, array $pageData = []): array
    {
        $analysis = [
            'url' => $url,
            'is_https' => strpos($url, 'https://') === 0,
            'has_mixed_content' => false,
            'security_score' => 0,
            'recommendations' => []
        ];

        // Check for HTTPS
        if (!$analysis['is_https']) {
            $analysis['recommendations'][] = [
                'type' => 'security',
                'priority' => 'high',
                'message' => 'Site is not using HTTPS',
                'suggestion' => 'Consider using HTTPS for secure communication'
            ];
        } else {
            $analysis['security_score'] += 30;
        }

        // Check for mixed content
        if (isset($pageData['resources'])) {
            foreach ($pageData['resources'] as $resource) {
                if (strpos($resource, 'http://') === 0) {
                    $analysis['has_mixed_content'] = true;
                    $analysis['recommendations'][] = [
                        'type' => 'security',
                        'priority' => 'medium',
                        'message' => 'Mixed content detected',
                        'suggestion' => 'Load all resources over HTTPS'
                    ];
                    break;
                }
            }
        }

        if (!$analysis['has_mixed_content']) {
            $analysis['security_score'] += 20;
        }

        // Check for security headers (simplified)
        if (isset($pageData['headers'])) {
            $securityHeaders = ['x-frame-options', 'x-content-type-options', 'x-xss-protection'];
            $presentHeaders = 0;
            
            foreach ($securityHeaders as $header) {
                if (isset($pageData['headers'][$header])) {
                    $presentHeaders++;
                }
            }
            
            $analysis['security_score'] += ($presentHeaders / count($securityHeaders)) * 30;
        }

        // Check for suspicious patterns
        $suspiciousPatterns = ['eval(', 'document.write', 'innerHTML', 'outerHTML'];
        if (isset($pageData['scripts'])) {
            foreach ($pageData['scripts'] as $script) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (strpos($script, $pattern) !== false) {
                        $analysis['recommendations'][] = [
                            'type' => 'security',
                            'priority' => 'low',
                            'message' => 'Potentially unsafe JavaScript detected',
                            'suggestion' => 'Review script content for security implications'
                        ];
                        break;
                    }
                }
            }
        }

        return $analysis;
    }

    public function getPerformanceInsights(array $performanceData): array
    {
        $insights = [];
        
        if (isset($performanceData['load_time'])) {
            $loadTime = $performanceData['load_time'];
            if ($loadTime > 3000) {
                $insights[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'message' => 'Slow page load time',
                    'value' => $loadTime . 'ms',
                    'suggestion' => 'Consider optimizing images, reducing JavaScript, or using a CDN'
                ];
            } elseif ($loadTime < 1000) {
                $insights[] = [
                    'type' => 'performance',
                    'priority' => 'info',
                    'message' => 'Excellent load time',
                    'value' => $loadTime . 'ms'
                ];
            }
        }

        if (isset($performanceData['resource_count'])) {
            $resourceCount = $performanceData['resource_count'];
            if ($resourceCount > 50) {
                $insights[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => 'High number of resources',
                    'value' => $resourceCount,
                    'suggestion' => 'Consider combining CSS/JS files or lazy loading images'
                ];
            }
        }

        if (isset($performanceData['image_size'])) {
            $imageSize = $performanceData['image_size'];
            if ($imageSize > 1024 * 1024) { // 1MB
                $insights[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => 'Large image files detected',
                    'value' => $this->formatBytes($imageSize),
                    'suggestion' => 'Compress images or use modern formats like WebP'
                ];
            }
        }

        return $insights;
    }

    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }

    public function clearConversationHistory(): void
    {
        $this->conversationHistory = [];
        $this->logger->info("Conversation history cleared");
    }

    public function updateUserPreferences(array $preferences): void
    {
        $this->userPreferences = array_merge($this->userPreferences, $preferences);
        $this->logger->info("User preferences updated", ['preferences' => $preferences]);
    }

    public function getUserPreferences(): array
    {
        return $this->userPreferences;
    }

    private function analyzeIntent(string $query): string
    {
        $query = strtolower($query);
        
        $intents = [
            'search' => ['search', 'find', 'look for', 'where is', 'how to find'],
            'help' => ['help', 'how to', 'what is', 'explain', 'tell me about'],
            'navigate' => ['go to', 'open', 'visit', 'navigate to', 'show me'],
            'translate' => ['translate', 'what does this mean', 'in english', 'in spanish'],
            'summarize' => ['summarize', 'summary', 'brief', 'overview', 'key points'],
            'accessibility' => ['accessibility', 'screen reader', 'alt text', 'contrast'],
            'security' => ['security', 'safe', 'secure', 'https', 'privacy'],
            'performance' => ['slow', 'fast', 'performance', 'optimize', 'speed']
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return $intent;
                }
            }
        }
        
        return 'general';
    }

    private function enhanceContext(array $context): array
    {
        return array_merge($context, [
            'user_preferences' => $this->userPreferences,
            'conversation_history' => array_slice($this->conversationHistory, -5), // Last 5 exchanges
            'current_time' => date('Y-m-d H:i:s'),
            'browser_info' => $this->getBrowserInfo()
        ]);
    }

    private function processIntent(string $intent, string $query, array $context): array
    {
        switch ($intent) {
            case 'search':
                return $this->handleSearchIntent($query, $context);
            case 'help':
                return $this->handleHelpIntent($query, $context);
            case 'navigate':
                return $this->handleNavigateIntent($query, $context);
            case 'translate':
                return $this->handleTranslateIntent($query, $context);
            case 'summarize':
                return $this->handleSummarizeIntent($query, $context);
            case 'accessibility':
                return $this->handleAccessibilityIntent($query, $context);
            case 'security':
                return $this->handleSecurityIntent($query, $context);
            case 'performance':
                return $this->handlePerformanceIntent($query, $context);
            default:
                return $this->handleGeneralIntent($query, $context);
        }
    }

    private function handleSearchIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can help you search for information. What would you like to find?',
            'suggestions' => [
                'Search the current page',
                'Search the web',
                'Search your bookmarks',
                'Search your history'
            ],
            'actions' => [
                'search_page' => 'Search current page content',
                'search_web' => 'Search the web',
                'search_bookmarks' => 'Search bookmarks'
            ]
        ];
    }

    private function handleHelpIntent(string $query, array $context): array
    {
        return [
            'message' => 'I\'m here to help! I can assist with searching, translating, summarizing content, and providing accessibility and security insights.',
            'capabilities' => $this->assistantCapabilities,
            'suggestions' => [
                'How do I search for text on this page?',
                'Can you translate this page?',
                'What accessibility issues does this page have?',
                'Is this website secure?'
            ]
        ];
    }

    private function handleNavigateIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can help you navigate. What would you like to visit?',
            'suggestions' => [
                'Open a new tab',
                'Go to your bookmarks',
                'Visit a specific website',
                'Open your downloads'
            ],
            'actions' => [
                'new_tab' => 'Open new tab',
                'bookmarks' => 'Open bookmarks',
                'history' => 'Open history'
            ]
        ];
    }

    private function handleTranslateIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can help translate content. What language would you like to translate to?',
            'supported_languages' => [
                'Spanish', 'French', 'German', 'Italian', 'Portuguese',
                'Russian', 'Chinese', 'Japanese', 'Korean', 'Arabic'
            ],
            'actions' => [
                'translate_page' => 'Translate current page',
                'translate_selection' => 'Translate selected text'
            ]
        ];
    }

    private function handleSummarizeIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can summarize content for you. Would you like me to summarize the current page?',
            'actions' => [
                'summarize_page' => 'Summarize current page',
                'summarize_selection' => 'Summarize selected text'
            ]
        ];
    }

    private function handleAccessibilityIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can analyze this page for accessibility issues and provide recommendations.',
            'actions' => [
                'check_accessibility' => 'Check page accessibility',
                'show_alt_text' => 'Show image alt text',
                'check_contrast' => 'Check color contrast'
            ]
        ];
    }

    private function handleSecurityIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can analyze this website for security issues and provide security recommendations.',
            'actions' => [
                'check_security' => 'Check website security',
                'check_https' => 'Verify HTTPS usage',
                'check_headers' => 'Check security headers'
            ]
        ];
    }

    private function handlePerformanceIntent(string $query, array $context): array
    {
        return [
            'message' => 'I can analyze this page\'s performance and provide optimization suggestions.',
            'actions' => [
                'check_performance' => 'Check page performance',
                'optimize_images' => 'Optimize images',
                'minify_resources' => 'Minify CSS/JS'
            ]
        ];
    }

    private function handleGeneralIntent(string $query, array $context): array
    {
        return [
            'message' => 'I\'m not sure how to help with that. Could you be more specific?',
            'suggestions' => [
                'Ask me to search for something',
                'Ask me to translate content',
                'Ask me to summarize a page',
                'Ask me to check accessibility',
                'Ask me to analyze security'
            ]
        ];
    }

    private function analyzePageContent(array $pageData): array
    {
        return [
            'title' => $pageData['title'] ?? '',
            'description' => $pageData['description'] ?? '',
            'keywords' => $pageData['keywords'] ?? '',
            'word_count' => isset($pageData['content']) ? str_word_count(strip_tags($pageData['content'])) : 0,
            'has_images' => !empty($pageData['images']),
            'has_videos' => !empty($pageData['videos']),
            'has_forms' => !empty($pageData['forms']),
            'language' => $this->detectLanguage($pageData['content'] ?? '')
        ];
    }

    private function generateContentSuggestions(array $analysis): array
    {
        $suggestions = [];
        
        if ($analysis['word_count'] > 1000) {
            $suggestions[] = [
                'type' => 'content',
                'message' => 'This page has a lot of content',
                'action' => 'summarize',
                'icon' => '📄'
            ];
        }
        
        if ($analysis['has_images'] && empty($analysis['title'])) {
            $suggestions[] = [
                'type' => 'accessibility',
                'message' => 'Check image alt text',
                'action' => 'check_accessibility',
                'icon' => '♿'
            ];
        }
        
        return $suggestions;
    }

    private function generateNavigationSuggestions(string $url, array $analysis): array
    {
        $suggestions = [];
        
        if (strpos($url, 'wikipedia.org') !== false) {
            $suggestions[] = [
                'type' => 'navigation',
                'message' => 'Wikipedia article detected',
                'action' => 'summarize',
                'icon' => '📚'
            ];
        }
        
        if (strpos($url, 'youtube.com') !== false) {
            $suggestions[] = [
                'type' => 'navigation',
                'message' => 'YouTube video detected',
                'action' => 'transcribe',
                'icon' => '🎥'
            ];
        }
        
        return $suggestions;
    }

    private function generateProductivitySuggestions(string $url, array $analysis): array
    {
        $suggestions = [];
        
        if ($analysis['has_forms']) {
            $suggestions[] = [
                'type' => 'productivity',
                'message' => 'Form detected - I can help fill it out',
                'action' => 'auto_fill',
                'icon' => '📝'
            ];
        }
        
        if ($analysis['word_count'] > 500) {
            $suggestions[] = [
                'type' => 'productivity',
                'message' => 'Long article - I can create a reading list',
                'action' => 'reading_list',
                'icon' => '📖'
            ];
        }
        
        return $suggestions;
    }

    private function basicSummarize(string $content, int $maxLength): string
    {
        $sentences = preg_split('/[.!?]+/', strip_tags($content));
        $sentences = array_filter($sentences, function($s) { return trim($s) !== ''; });
        
        $summary = '';
        foreach ($sentences as $sentence) {
            if (strlen($summary . $sentence) > $maxLength) {
                break;
            }
            $summary .= $sentence . '. ';
        }
        
        return trim($summary);
    }

    private function formatReadingTime(int $minutes): string
    {
        if ($minutes < 1) {
            return 'Less than 1 minute';
        } elseif ($minutes === 1) {
            return '1 minute';
        } else {
            return $minutes . ' minutes';
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function callAIService(string $action, array $data): array
    {
        // This would integrate with actual AI services like OpenAI, Anthropic, etc.
        // For now, return mock responses
        switch ($action) {
            case 'summarize':
                return ['summary' => $this->basicSummarize($data['content'], $data['max_length'])];
            case 'translate':
        return [
                    'translated_text' => $data['text'],
                    'confidence' => 0.8
                ];
            default:
                return [];
        }
    }

    private function addToConversationHistory(string $role, string $message): void
    {
        $this->conversationHistory[] = [
            'role' => $role,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Keep only last 50 exchanges
        if (count($this->conversationHistory) > 50) {
            $this->conversationHistory = array_slice($this->conversationHistory, -50);
        }
    }

    private function loadUserPreferences(): void
    {
        $this->userPreferences = [
            'language' => 'en',
            'assistance_level' => 'medium',
            'notifications' => true,
            'auto_summarize' => false,
            'auto_translate' => false
        ];
    }

    private function initializeAIProviders(): void
    {
        $this->aiProviders = [
            'openai' => [
                'enabled' => !empty($this->config['openai_api_key']),
                'api_key' => $this->config['openai_api_key'] ?? null
            ],
            'anthropic' => [
                'enabled' => !empty($this->config['anthropic_api_key']),
                'api_key' => $this->config['anthropic_api_key'] ?? null
            ]
        ];
    }

    private function initializeAICapabilities(): void
    {
        $this->assistantCapabilities = [
            'search' => 'Search for information and content',
            'translate' => 'Translate text and web pages',
            'summarize' => 'Summarize long articles and content',
            'accessibility' => 'Check and improve accessibility',
            'security' => 'Analyze website security',
            'performance' => 'Optimize page performance',
            'navigation' => 'Help with browser navigation',
            'productivity' => 'Boost browsing productivity'
        ];
    }

    private function getBrowserInfo(): array
    {
        return [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'platform' => php_uname('s'),
            'version' => '1.0.0'
        ];
    }
}
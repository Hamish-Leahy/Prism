<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class AIAssistantService
{
    private Logger $logger;
    private array $capabilities = [];
    private array $conversationHistory = [];
    private string $assistantName = 'Prism Assistant';
    private array $userPreferences = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeCapabilities();
    }

    public function processQuery(string $query, array $context = []): array
    {
        $this->logger->info('Processing AI assistant query', ['query' => $query]);
        
        // Add to conversation history
        $this->conversationHistory[] = [
            'type' => 'user',
            'message' => $query,
            'timestamp' => time(),
            'context' => $context
        ];

        // Determine intent
        $intent = $this->determineIntent($query);
        
        // Generate response
        $response = $this->generateResponse($query, $intent, $context);
        
        // Add response to history
        $this->conversationHistory[] = [
            'type' => 'assistant',
            'message' => $response['message'],
            'timestamp' => time(),
            'intent' => $intent
        ];

        return $response;
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function setUserPreferences(array $preferences): void
    {
        $this->userPreferences = array_merge($this->userPreferences, $preferences);
        $this->logger->info('User preferences updated', ['preferences' => $preferences]);
    }

    public function getConversationHistory(): array
    {
        return $this->conversationHistory;
    }

    public function clearHistory(): void
    {
        $this->conversationHistory = [];
        $this->logger->info('Conversation history cleared');
    }

    public function getSuggestions(string $partialQuery): array
    {
        $suggestions = [];
        
        foreach ($this->capabilities as $capability) {
            if (stripos($capability['name'], $partialQuery) !== false ||
                stripos($capability['description'], $partialQuery) !== false) {
                $suggestions[] = [
                    'text' => $capability['name'],
                    'description' => $capability['description'],
                    'type' => 'capability'
                ];
            }
        }

        // Add common queries
        $commonQueries = [
            'How do I bookmark a page?',
            'How do I open a new tab?',
            'How do I clear my browsing history?',
            'How do I enable dark mode?',
            'How do I download a file?',
            'How do I manage my passwords?',
            'How do I enable privacy mode?',
            'How do I sync my data?'
        ];

        foreach ($commonQueries as $query) {
            if (stripos($query, $partialQuery) !== false) {
                $suggestions[] = [
                    'text' => $query,
                    'description' => 'Common question',
                    'type' => 'query'
                ];
            }
        }

        return array_slice($suggestions, 0, 5);
    }

    public function performAction(string $action, array $parameters = []): array
    {
        $this->logger->info('Performing AI assistant action', [
            'action' => $action,
            'parameters' => $parameters
        ]);

        switch ($action) {
            case 'open_tab':
                return $this->openTab($parameters);
            case 'bookmark_page':
                return $this->bookmarkPage($parameters);
            case 'search_web':
                return $this->searchWeb($parameters);
            case 'clear_history':
                return $this->clearHistory($parameters);
            case 'enable_dark_mode':
                return $this->enableDarkMode($parameters);
            case 'download_file':
                return $this->downloadFile($parameters);
            case 'manage_passwords':
                return $this->managePasswords($parameters);
            case 'enable_privacy_mode':
                return $this->enablePrivacyMode($parameters);
            case 'sync_data':
                return $this->syncData($parameters);
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown action: ' . $action
                ];
        }
    }

    private function initializeCapabilities(): void
    {
        $this->capabilities = [
            [
                'name' => 'Tab Management',
                'description' => 'Help with opening, closing, and organizing tabs',
                'keywords' => ['tab', 'open', 'close', 'new tab'],
                'actions' => ['open_tab', 'close_tab', 'switch_tab']
            ],
            [
                'name' => 'Bookmark Management',
                'description' => 'Assist with saving and organizing bookmarks',
                'keywords' => ['bookmark', 'save', 'favorite', 'bookmark page'],
                'actions' => ['bookmark_page', 'organize_bookmarks', 'search_bookmarks']
            ],
            [
                'name' => 'Search Assistance',
                'description' => 'Help with web searches and finding information',
                'keywords' => ['search', 'find', 'look up', 'google'],
                'actions' => ['search_web', 'search_history', 'search_bookmarks']
            ],
            [
                'name' => 'Privacy & Security',
                'description' => 'Guide on privacy settings and security features',
                'keywords' => ['privacy', 'security', 'private mode', 'incognito'],
                'actions' => ['enable_privacy_mode', 'clear_data', 'security_scan']
            ],
            [
                'name' => 'Appearance & Themes',
                'description' => 'Help with customizing the browser appearance',
                'keywords' => ['theme', 'dark mode', 'light mode', 'appearance'],
                'actions' => ['enable_dark_mode', 'change_theme', 'customize_ui']
            ],
            [
                'name' => 'Download Management',
                'description' => 'Assist with downloading and managing files',
                'keywords' => ['download', 'file', 'save file'],
                'actions' => ['download_file', 'manage_downloads', 'pause_download']
            ],
            [
                'name' => 'Password Management',
                'description' => 'Help with password saving and management',
                'keywords' => ['password', 'login', 'credentials', 'save password'],
                'actions' => ['manage_passwords', 'save_password', 'generate_password']
            ],
            [
                'name' => 'Data Synchronization',
                'description' => 'Assist with syncing data across devices',
                'keywords' => ['sync', 'cloud', 'backup', 'restore'],
                'actions' => ['sync_data', 'backup_data', 'restore_data']
            ],
            [
                'name' => 'Performance Optimization',
                'description' => 'Help optimize browser performance',
                'keywords' => ['performance', 'speed', 'slow', 'optimize'],
                'actions' => ['optimize_memory', 'clear_cache', 'performance_scan']
            ],
            [
                'name' => 'Accessibility',
                'description' => 'Assist with accessibility features and settings',
                'keywords' => ['accessibility', 'screen reader', 'zoom', 'high contrast'],
                'actions' => ['enable_accessibility', 'adjust_zoom', 'screen_reader']
            ]
        ];
    }

    private function determineIntent(string $query): string
    {
        $query = strtolower($query);
        
        foreach ($this->capabilities as $capability) {
            foreach ($capability['keywords'] as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return $capability['name'];
                }
            }
        }
        
        return 'general';
    }

    private function generateResponse(string $query, string $intent, array $context): array
    {
        $response = [
            'message' => '',
            'intent' => $intent,
            'actions' => [],
            'suggestions' => []
        ];

        switch ($intent) {
            case 'Tab Management':
                $response['message'] = $this->getTabManagementResponse($query);
                $response['actions'] = ['open_tab', 'close_tab'];
                break;
                
            case 'Bookmark Management':
                $response['message'] = $this->getBookmarkManagementResponse($query);
                $response['actions'] = ['bookmark_page', 'organize_bookmarks'];
                break;
                
            case 'Search Assistance':
                $response['message'] = $this->getSearchResponse($query);
                $response['actions'] = ['search_web'];
                break;
                
            case 'Privacy & Security':
                $response['message'] = $this->getPrivacyResponse($query);
                $response['actions'] = ['enable_privacy_mode', 'clear_data'];
                break;
                
            case 'Appearance & Themes':
                $response['message'] = $this->getAppearanceResponse($query);
                $response['actions'] = ['enable_dark_mode', 'change_theme'];
                break;
                
            case 'Download Management':
                $response['message'] = $this->getDownloadResponse($query);
                $response['actions'] = ['download_file', 'manage_downloads'];
                break;
                
            case 'Password Management':
                $response['message'] = $this->getPasswordResponse($query);
                $response['actions'] = ['manage_passwords', 'save_password'];
                break;
                
            case 'Data Synchronization':
                $response['message'] = $this->getSyncResponse($query);
                $response['actions'] = ['sync_data', 'backup_data'];
                break;
                
            case 'Performance Optimization':
                $response['message'] = $this->getPerformanceResponse($query);
                $response['actions'] = ['optimize_memory', 'clear_cache'];
                break;
                
            case 'Accessibility':
                $response['message'] = $this->getAccessibilityResponse($query);
                $response['actions'] = ['enable_accessibility', 'adjust_zoom'];
                break;
                
            default:
                $response['message'] = $this->getGeneralResponse($query);
                $response['suggestions'] = $this->getSuggestions($query);
        }

        return $response;
    }

    private function getTabManagementResponse(string $query): string
    {
        if (strpos($query, 'open') !== false || strpos($query, 'new') !== false) {
            return "I can help you open a new tab. You can press Ctrl+T (or Cmd+T on Mac) or click the '+' button in the tab bar. Would you like me to open a specific website?";
        } elseif (strpos($query, 'close') !== false) {
            return "To close a tab, you can press Ctrl+W (or Cmd+W on Mac) or click the 'X' on the tab. Would you like me to close the current tab?";
        } else {
            return "I can help you manage your tabs. You can open new tabs, close existing ones, or switch between them. What would you like to do?";
        }
    }

    private function getBookmarkManagementResponse(string $query): string
    {
        if (strpos($query, 'save') !== false || strpos($query, 'bookmark') !== false) {
            return "To bookmark a page, you can press Ctrl+D (or Cmd+D on Mac) or click the star icon in the address bar. Would you like me to bookmark the current page?";
        } else {
            return "I can help you manage your bookmarks. You can save pages, organize them into folders, or search through your existing bookmarks. What would you like to do?";
        }
    }

    private function getSearchResponse(string $query): string
    {
        return "I can help you search the web. You can type your search query in the address bar or use Ctrl+K (or Cmd+K on Mac) to focus the search. What would you like to search for?";
    }

    private function getPrivacyResponse(string $query): string
    {
        if (strpos($query, 'private') !== false || strpos($query, 'incognito') !== false) {
            return "To enable private browsing, you can press Ctrl+Shift+N (or Cmd+Shift+N on Mac) or use the menu. Private mode prevents saving browsing history, cookies, and other data.";
        } else {
            return "I can help you with privacy and security settings. You can enable private browsing, clear your data, or adjust privacy preferences. What would you like to do?";
        }
    }

    private function getAppearanceResponse(string $query): string
    {
        if (strpos($query, 'dark') !== false) {
            return "To enable dark mode, go to Settings > Appearance and select 'Dark' theme. This will make the browser interface easier on your eyes in low-light conditions.";
        } else {
            return "I can help you customize the browser appearance. You can change themes, enable dark mode, or adjust other visual settings. What would you like to customize?";
        }
    }

    private function getDownloadResponse(string $query): string
    {
        return "I can help you with downloads. You can download files by clicking download links, manage your downloads in the downloads panel, or pause/resume downloads. What would you like to do?";
    }

    private function getPasswordResponse(string $query): string
    {
        return "I can help you manage passwords. The browser can save and auto-fill passwords, generate strong passwords, and help you organize your login credentials securely. What would you like to do?";
    }

    private function getSyncResponse(string $query): string
    {
        return "I can help you sync your data across devices. You can enable cloud sync to keep your bookmarks, passwords, and settings synchronized. Would you like me to help you set up sync?";
    }

    private function getPerformanceResponse(string $query): string
    {
        return "I can help optimize your browser performance. You can clear cache, close unused tabs, or run a performance scan to identify issues. What would you like me to help with?";
    }

    private function getAccessibilityResponse(string $query): string
    {
        return "I can help you with accessibility features. You can enable screen reader support, adjust zoom levels, or use high contrast themes. What accessibility feature would you like to use?";
    }

    private function getGeneralResponse(string $query): string
    {
        return "I'm here to help you with your browser! I can assist with tabs, bookmarks, search, privacy settings, themes, downloads, passwords, and more. What would you like to know?";
    }

    // Action implementations
    private function openTab(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Opening new tab...',
            'action' => 'open_tab',
            'url' => $parameters['url'] ?? null
        ];
    }

    private function bookmarkPage(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Bookmarking current page...',
            'action' => 'bookmark_page'
        ];
    }

    private function searchWeb(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Searching the web...',
            'action' => 'search_web',
            'query' => $parameters['query'] ?? ''
        ];
    }

    private function clearHistory(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Clearing browsing history...',
            'action' => 'clear_history'
        ];
    }

    private function enableDarkMode(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Enabling dark mode...',
            'action' => 'enable_dark_mode'
        ];
    }

    private function downloadFile(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Starting download...',
            'action' => 'download_file',
            'url' => $parameters['url'] ?? ''
        ];
    }

    private function managePasswords(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Opening password manager...',
            'action' => 'manage_passwords'
        ];
    }

    private function enablePrivacyMode(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Enabling privacy mode...',
            'action' => 'enable_privacy_mode'
        ];
    }

    private function syncData(array $parameters): array
    {
        return [
            'success' => true,
            'message' => 'Starting data synchronization...',
            'action' => 'sync_data'
        ];
    }
}

<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class VoiceCommandService
{
    private Logger $logger;
    private array $commands = [];
    private bool $isListening = false;
    private string $language = 'en-US';

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeCommands();
    }

    public function startListening(): bool
    {
        try {
            $this->isListening = true;
            $this->logger->info('Voice command listening started');
            
            // In a real implementation, this would integrate with Web Speech API
            // or a speech recognition service like Google Cloud Speech-to-Text
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to start voice listening: ' . $e->getMessage());
            return false;
        }
    }

    public function stopListening(): bool
    {
        $this->isListening = false;
        $this->logger->info('Voice command listening stopped');
        return true;
    }

    public function processVoiceCommand(string $transcript): array
    {
        $transcript = strtolower(trim($transcript));
        $this->logger->info('Processing voice command', ['transcript' => $transcript]);

        foreach ($this->commands as $command) {
            if ($this->matchesCommand($transcript, $command['patterns'])) {
                return $this->executeCommand($command, $transcript);
            }
        }

        return [
            'success' => false,
            'message' => 'Command not recognized',
            'suggestions' => $this->getCommandSuggestions()
        ];
    }

    public function addCustomCommand(string $name, array $patterns, callable $handler): void
    {
        $this->commands[] = [
            'name' => $name,
            'patterns' => $patterns,
            'handler' => $handler,
            'type' => 'custom'
        ];
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
        $this->logger->info('Voice command language changed', ['language' => $language]);
    }

    public function getAvailableCommands(): array
    {
        return array_map(function($command) {
            return [
                'name' => $command['name'],
                'description' => $command['description'] ?? '',
                'examples' => $command['examples'] ?? []
            ];
        }, $this->commands);
    }

    private function initializeCommands(): void
    {
        $this->commands = [
            [
                'name' => 'navigate',
                'patterns' => ['go to', 'navigate to', 'open', 'visit'],
                'description' => 'Navigate to a website',
                'examples' => ['go to google.com', 'navigate to youtube', 'open github'],
                'handler' => [$this, 'handleNavigate']
            ],
            [
                'name' => 'search',
                'patterns' => ['search for', 'find', 'look up'],
                'description' => 'Search the web',
                'examples' => ['search for weather', 'find restaurants near me'],
                'handler' => [$this, 'handleSearch']
            ],
            [
                'name' => 'new_tab',
                'patterns' => ['new tab', 'open new tab', 'create tab'],
                'description' => 'Open a new tab',
                'examples' => ['new tab', 'open new tab'],
                'handler' => [$this, 'handleNewTab']
            ],
            [
                'name' => 'close_tab',
                'patterns' => ['close tab', 'close current tab'],
                'description' => 'Close the current tab',
                'examples' => ['close tab', 'close current tab'],
                'handler' => [$this, 'handleCloseTab']
            ],
            [
                'name' => 'bookmark',
                'patterns' => ['bookmark', 'save page', 'add bookmark'],
                'description' => 'Bookmark the current page',
                'examples' => ['bookmark this page', 'save page'],
                'handler' => [$this, 'handleBookmark']
            ],
            [
                'name' => 'refresh',
                'patterns' => ['refresh', 'reload', 'refresh page'],
                'description' => 'Refresh the current page',
                'examples' => ['refresh', 'reload page'],
                'handler' => [$this, 'handleRefresh']
            ],
            [
                'name' => 'zoom_in',
                'patterns' => ['zoom in', 'make bigger', 'increase zoom'],
                'description' => 'Zoom in on the page',
                'examples' => ['zoom in', 'make bigger'],
                'handler' => [$this, 'handleZoomIn']
            ],
            [
                'name' => 'zoom_out',
                'patterns' => ['zoom out', 'make smaller', 'decrease zoom'],
                'description' => 'Zoom out on the page',
                'examples' => ['zoom out', 'make smaller'],
                'handler' => [$this, 'handleZoomOut']
            ],
            [
                'name' => 'scroll_up',
                'patterns' => ['scroll up', 'go up', 'page up'],
                'description' => 'Scroll up on the page',
                'examples' => ['scroll up', 'go up'],
                'handler' => [$this, 'handleScrollUp']
            ],
            [
                'name' => 'scroll_down',
                'patterns' => ['scroll down', 'go down', 'page down'],
                'description' => 'Scroll down on the page',
                'examples' => ['scroll down', 'go down'],
                'handler' => [$this, 'handleScrollDown']
            ],
            [
                'name' => 'go_back',
                'patterns' => ['go back', 'back', 'previous page'],
                'description' => 'Go back in browser history',
                'examples' => ['go back', 'back'],
                'handler' => [$this, 'handleGoBack']
            ],
            [
                'name' => 'go_forward',
                'patterns' => ['go forward', 'forward', 'next page'],
                'description' => 'Go forward in browser history',
                'examples' => ['go forward', 'forward'],
                'handler' => [$this, 'handleGoForward']
            ],
            [
                'name' => 'mute',
                'patterns' => ['mute', 'silence', 'turn off sound'],
                'description' => 'Mute audio on the current tab',
                'examples' => ['mute', 'silence'],
                'handler' => [$this, 'handleMute']
            ],
            [
                'name' => 'unmute',
                'patterns' => ['unmute', 'turn on sound', 'enable audio'],
                'description' => 'Unmute audio on the current tab',
                'examples' => ['unmute', 'turn on sound'],
                'handler' => [$this, 'handleUnmute']
            ],
            [
                'name' => 'fullscreen',
                'patterns' => ['fullscreen', 'full screen', 'enter fullscreen'],
                'description' => 'Toggle fullscreen mode',
                'examples' => ['fullscreen', 'full screen'],
                'handler' => [$this, 'handleFullscreen']
            ]
        ];
    }

    private function matchesCommand(string $transcript, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (strpos($transcript, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function executeCommand(array $command, string $transcript): array
    {
        try {
            $result = call_user_func($command['handler'], $transcript);
            return [
                'success' => true,
                'command' => $command['name'],
                'result' => $result
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute voice command', [
                'command' => $command['name'],
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'command' => $command['name'],
                'error' => 'Failed to execute command'
            ];
        }
    }

    private function getCommandSuggestions(): array
    {
        return array_slice(array_map(function($command) {
            return $command['examples'][0] ?? $command['name'];
        }, $this->commands), 0, 5);
    }

    // Command handlers
    private function handleNavigate(string $transcript): array
    {
        $url = $this->extractUrl($transcript);
        return ['action' => 'navigate', 'url' => $url];
    }

    private function handleSearch(string $transcript): array
    {
        $query = $this->extractSearchQuery($transcript);
        return ['action' => 'search', 'query' => $query];
    }

    private function handleNewTab(string $transcript): array
    {
        return ['action' => 'new_tab'];
    }

    private function handleCloseTab(string $transcript): array
    {
        return ['action' => 'close_tab'];
    }

    private function handleBookmark(string $transcript): array
    {
        return ['action' => 'bookmark'];
    }

    private function handleRefresh(string $transcript): array
    {
        return ['action' => 'refresh'];
    }

    private function handleZoomIn(string $transcript): array
    {
        return ['action' => 'zoom_in'];
    }

    private function handleZoomOut(string $transcript): array
    {
        return ['action' => 'zoom_out'];
    }

    private function handleScrollUp(string $transcript): array
    {
        return ['action' => 'scroll_up'];
    }

    private function handleScrollDown(string $transcript): array
    {
        return ['action' => 'scroll_down'];
    }

    private function handleGoBack(string $transcript): array
    {
        return ['action' => 'go_back'];
    }

    private function handleGoForward(string $transcript): array
    {
        return ['action' => 'go_forward'];
    }

    private function handleMute(string $transcript): array
    {
        return ['action' => 'mute'];
    }

    private function handleUnmute(string $transcript): array
    {
        return ['action' => 'unmute'];
    }

    private function handleFullscreen(string $transcript): array
    {
        return ['action' => 'fullscreen'];
    }

    private function extractUrl(string $transcript): string
    {
        // Extract URL from transcript
        $words = explode(' ', $transcript);
        foreach ($words as $word) {
            if (filter_var($word, FILTER_VALIDATE_URL) || 
                preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $word)) {
                return $word;
            }
        }
        return '';
    }

    private function extractSearchQuery(string $transcript): string
    {
        // Extract search query from transcript
        $patterns = ['search for', 'find', 'look up'];
        foreach ($patterns as $pattern) {
            if (strpos($transcript, $pattern) !== false) {
                return trim(str_replace($pattern, '', $transcript));
            }
        }
        return $transcript;
    }
}

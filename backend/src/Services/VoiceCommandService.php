<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class VoiceCommandService
{
    private Logger $logger;
    private bool $isEnabled = false;
    private array $commands = [];
    private array $commandHistory = [];
    private array $speechPatterns = [];
    private float $confidenceThreshold = 0.7;
    private int $maxHistorySize = 100;
    private array $voiceSettings = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeVoiceSettings();
        $this->initializeSpeechPatterns();
    }

    public function enable(): bool
    {
        $this->isEnabled = true;
        $this->logger->info('Voice commands enabled');
        return true;
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->logger->info('Voice commands disabled');
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function processAudioData(string $audioData, array $metadata = []): array
    {
        if (!$this->isEnabled) {
            return ['success' => false, 'message' => 'Voice commands disabled'];
        }

        try {
            // In a real implementation, this would use speech recognition APIs
            // For now, we'll simulate the process
            $transcript = $this->transcribeAudio($audioData, $metadata);
            
            if (empty($transcript)) {
                return ['success' => false, 'message' => 'No speech detected'];
            }

            $command = $this->parseCommand($transcript);
            
            if ($command) {
                $result = $this->executeCommand($command);
                $this->addToHistory($transcript, $command, $result);
                
                return [
                    'success' => true,
                    'transcript' => $transcript,
                    'command' => $command,
                    'result' => $result
                ];
            }

            return [
                'success' => false,
                'transcript' => $transcript,
                'message' => 'No valid command recognized'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error processing voice command', [
                'error' => $e->getMessage(),
                'metadata' => $metadata
            ]);
            
            return ['success' => false, 'message' => 'Error processing voice command'];
        }
    }

    public function registerCommand(string $name, array $patterns, callable $callback, array $options = []): bool
    {
        $this->commands[$name] = [
            'patterns' => $patterns,
            'callback' => $callback,
            'options' => array_merge([
                'confidence_threshold' => $this->confidenceThreshold,
                'case_sensitive' => false,
                'exact_match' => false
            ], $options)
        ];

        $this->logger->info('Voice command registered', ['name' => $name, 'patterns' => count($patterns)]);
        return true;
    }

    public function unregisterCommand(string $name): bool
    {
        if (isset($this->commands[$name])) {
            unset($this->commands[$name]);
            $this->logger->info('Voice command unregistered', ['name' => $name]);
            return true;
        }
        return false;
    }

    public function setConfidenceThreshold(float $threshold): void
    {
        $this->confidenceThreshold = max(0.0, min(1.0, $threshold));
        $this->logger->info('Confidence threshold updated', ['threshold' => $this->confidenceThreshold]);
    }

    public function getCommandHistory(): array
    {
        return $this->commandHistory;
    }

    public function clearCommandHistory(): void
    {
        $this->commandHistory = [];
        $this->logger->info('Command history cleared');
    }

    public function getAvailableCommands(): array
    {
        return array_keys($this->commands);
    }

    public function getVoiceSettings(): array
    {
        return $this->voiceSettings;
    }

    public function updateVoiceSettings(array $settings): void
    {
        $this->voiceSettings = array_merge($this->voiceSettings, $settings);
        $this->logger->info('Voice settings updated', ['settings' => $settings]);
    }

    private function initializeVoiceSettings(): void
    {
        $this->voiceSettings = [
            'language' => 'en-US',
            'sample_rate' => 16000,
            'channels' => 1,
            'encoding' => 'pcm',
            'noise_reduction' => true,
            'auto_punctuation' => true,
            'profanity_filter' => false,
            'speaker_diarization' => false
        ];
    }

    private function initializeSpeechPatterns(): void
    {
        $this->speechPatterns = [
            'browser_commands' => [
                'open_tab' => ['open tab', 'new tab', 'create tab'],
                'close_tab' => ['close tab', 'close current tab'],
                'switch_tab' => ['switch tab', 'change tab', 'go to tab'],
                'refresh_page' => ['refresh', 'reload', 'refresh page'],
                'go_back' => ['go back', 'back', 'previous page'],
                'go_forward' => ['go forward', 'forward', 'next page'],
                'bookmark_page' => ['bookmark', 'save bookmark', 'bookmark this page'],
                'open_bookmarks' => ['open bookmarks', 'show bookmarks'],
                'open_history' => ['open history', 'show history'],
                'open_downloads' => ['open downloads', 'show downloads'],
                'open_settings' => ['open settings', 'show settings', 'preferences'],
                'zoom_in' => ['zoom in', 'increase zoom'],
                'zoom_out' => ['zoom out', 'decrease zoom'],
                'reset_zoom' => ['reset zoom', 'normal zoom'],
                'find_text' => ['find', 'search page', 'find text'],
                'print_page' => ['print', 'print page'],
                'save_page' => ['save page', 'save as'],
                'fullscreen' => ['fullscreen', 'enter fullscreen'],
                'exit_fullscreen' => ['exit fullscreen', 'leave fullscreen']
            ],
            'navigation_commands' => [
                'go_to_url' => ['go to', 'navigate to', 'visit', 'open'],
                'search' => ['search for', 'look for', 'find'],
                'scroll_up' => ['scroll up', 'page up'],
                'scroll_down' => ['scroll down', 'page down'],
                'scroll_to_top' => ['scroll to top', 'go to top'],
                'scroll_to_bottom' => ['scroll to bottom', 'go to bottom']
            ],
            'media_commands' => [
                'play_video' => ['play', 'start video'],
                'pause_video' => ['pause', 'stop video'],
                'mute_audio' => ['mute', 'turn off sound'],
                'unmute_audio' => ['unmute', 'turn on sound'],
                'volume_up' => ['volume up', 'louder'],
                'volume_down' => ['volume down', 'quieter']
            ],
            'system_commands' => [
                'help' => ['help', 'what can you do', 'commands'],
                'stop_listening' => ['stop listening', 'disable voice', 'turn off voice'],
                'start_listening' => ['start listening', 'enable voice', 'turn on voice'],
                'repeat_command' => ['repeat', 'say again', 'what did you say']
            ]
        ];
    }

    private function transcribeAudio(string $audioData, array $metadata): string
    {
        // In a real implementation, this would use:
        // - Google Speech-to-Text API
        // - Azure Speech Services
        // - AWS Transcribe
        // - Mozilla DeepSpeech
        // - Web Speech API (browser-based)
        
        // For simulation, we'll return a mock transcript
        $mockTranscripts = [
            'open new tab',
            'go to google.com',
            'search for weather',
            'bookmark this page',
            'refresh the page',
            'go back',
            'close tab',
            'open settings',
            'zoom in',
            'find text'
        ];

        // Simulate confidence-based selection
        $confidence = $metadata['confidence'] ?? 0.8;
        if ($confidence > $this->confidenceThreshold) {
            return $mockTranscripts[array_rand($mockTranscripts)];
        }

        return '';
    }

    private function parseCommand(string $transcript): ?array
    {
        $transcript = trim(strtolower($transcript));
        
        foreach ($this->commands as $commandName => $commandData) {
            foreach ($commandData['patterns'] as $pattern) {
                $pattern = strtolower($pattern);
                
                $confidence = $this->calculateConfidence($transcript, $pattern, $commandData['options']);
                
                if ($confidence >= $commandData['options']['confidence_threshold']) {
                    $parameters = $this->extractParameters($transcript, $pattern);
                    
            return [
                        'name' => $commandName,
                        'pattern' => $pattern,
                        'confidence' => $confidence,
                        'parameters' => $parameters,
                        'raw_transcript' => $transcript
                    ];
                }
            }
        }

        return null;
    }

    private function calculateConfidence(string $transcript, string $pattern, array $options): float
    {
        if ($options['exact_match']) {
            return $transcript === $pattern ? 1.0 : 0.0;
        }

        // Use Levenshtein distance for fuzzy matching
        $distance = levenshtein($transcript, $pattern);
        $maxLength = max(strlen($transcript), strlen($pattern));
        
        if ($maxLength === 0) {
            return 1.0;
        }

        $similarity = 1 - ($distance / $maxLength);
        
        // Boost confidence for partial matches
        if (strpos($transcript, $pattern) !== false || strpos($pattern, $transcript) !== false) {
            $similarity = min(1.0, $similarity + 0.2);
        }

        return $similarity;
    }

    private function extractParameters(string $transcript, string $pattern): array
    {
        $parameters = [];
        
        // Extract URL from "go to" commands
        if (strpos($pattern, 'go to') !== false || strpos($pattern, 'navigate to') !== false) {
        $url = $this->extractUrl($transcript);
            if ($url) {
                $parameters['url'] = $url;
            }
        }

        // Extract search terms from "search" commands
        if (strpos($pattern, 'search') !== false) {
            $searchTerm = $this->extractSearchTerm($transcript);
            if ($searchTerm) {
                $parameters['query'] = $searchTerm;
            }
        }

        // Extract tab number from "switch tab" commands
        if (strpos($pattern, 'switch tab') !== false || strpos($pattern, 'go to tab') !== false) {
            $tabNumber = $this->extractTabNumber($transcript);
            if ($tabNumber) {
                $parameters['tab_number'] = $tabNumber;
            }
        }

        return $parameters;
    }

    private function extractUrl(string $transcript): ?string
    {
        // Look for common URL patterns
        $urlPatterns = [
            '/(?:go to|navigate to|visit|open)\s+(https?:\/\/[^\s]+)/i',
            '/(?:go to|navigate to|visit|open)\s+([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            '/(?:go to|navigate to|visit|open)\s+([a-zA-Z0-9.-]+\.com)/i'
        ];

        foreach ($urlPatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $url = $matches[1];
                if (!str_starts_with($url, 'http')) {
                    $url = 'https://' . $url;
                }
                return $url;
            }
        }

        return null;
    }

    private function extractSearchTerm(string $transcript): ?string
    {
        $searchPatterns = [
            '/(?:search for|look for|find)\s+(.+)/i',
            '/(?:search|look|find)\s+(.+)/i'
        ];

        foreach ($searchPatterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    private function extractTabNumber(string $transcript): ?int
    {
        if (preg_match('/(?:tab|page)\s*(\d+)/i', $transcript, $matches)) {
            return (int) $matches[1];
        }

        // Handle word numbers
        $wordNumbers = [
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
            'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10
        ];

        foreach ($wordNumbers as $word => $number) {
            if (strpos($transcript, $word) !== false) {
                return $number;
            }
        }

        return null;
    }

    private function executeCommand(array $command): array
    {
        $commandName = $command['name'];
        
        if (!isset($this->commands[$commandName])) {
            return ['success' => false, 'message' => 'Command not found'];
        }

        try {
            $callback = $this->commands[$commandName]['callback'];
            $result = $callback($command);
            
            $this->logger->info('Voice command executed', [
                'command' => $commandName,
                'confidence' => $command['confidence'],
                'parameters' => $command['parameters']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Error executing voice command', [
                'command' => $commandName,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => 'Error executing command'];
        }
    }

    private function addToHistory(string $transcript, array $command, array $result): void
    {
        $this->commandHistory[] = [
            'timestamp' => microtime(true),
            'transcript' => $transcript,
            'command' => $command,
            'result' => $result
        ];

        // Keep history size manageable
        if (count($this->commandHistory) > $this->maxHistorySize) {
            $this->commandHistory = array_slice($this->commandHistory, -$this->maxHistorySize);
        }
    }

    public function getCommandStatistics(): array
    {
        $stats = [];
        $totalCommands = count($this->commandHistory);
        
        foreach ($this->commandHistory as $entry) {
            $commandName = $entry['command']['name'];
            if (!isset($stats[$commandName])) {
                $stats[$commandName] = 0;
            }
            $stats[$commandName]++;
        }

        return [
            'total_commands' => $totalCommands,
            'command_counts' => $stats,
            'average_confidence' => $this->calculateAverageConfidence(),
            'most_used_command' => $this->getMostUsedCommand($stats)
        ];
    }

    private function calculateAverageConfidence(): float
    {
        if (empty($this->commandHistory)) {
            return 0.0;
        }

        $totalConfidence = 0;
        foreach ($this->commandHistory as $entry) {
            $totalConfidence += $entry['command']['confidence'];
        }

        return $totalConfidence / count($this->commandHistory);
    }

    private function getMostUsedCommand(array $stats): ?string
    {
        if (empty($stats)) {
            return null;
        }

        return array_keys($stats, max($stats))[0];
    }

    public function createDefaultCommands(): void
    {
        // Browser navigation commands
        $this->registerCommand('open_tab', $this->speechPatterns['browser_commands']['open_tab'], function($command) {
            return ['success' => true, 'action' => 'open_tab', 'message' => 'Opening new tab'];
        });

        $this->registerCommand('close_tab', $this->speechPatterns['browser_commands']['close_tab'], function($command) {
            return ['success' => true, 'action' => 'close_tab', 'message' => 'Closing current tab'];
        });

        $this->registerCommand('refresh_page', $this->speechPatterns['browser_commands']['refresh_page'], function($command) {
            return ['success' => true, 'action' => 'refresh_page', 'message' => 'Refreshing page'];
        });

        $this->registerCommand('go_back', $this->speechPatterns['browser_commands']['go_back'], function($command) {
            return ['success' => true, 'action' => 'go_back', 'message' => 'Going back'];
        });

        $this->registerCommand('go_forward', $this->speechPatterns['browser_commands']['go_forward'], function($command) {
            return ['success' => true, 'action' => 'go_forward', 'message' => 'Going forward'];
        });

        $this->registerCommand('bookmark_page', $this->speechPatterns['browser_commands']['bookmark_page'], function($command) {
            return ['success' => true, 'action' => 'bookmark_page', 'message' => 'Bookmarking page'];
        });

        $this->registerCommand('go_to_url', $this->speechPatterns['navigation_commands']['go_to_url'], function($command) {
            $url = $command['parameters']['url'] ?? '';
            return ['success' => true, 'action' => 'go_to_url', 'url' => $url, 'message' => "Navigating to {$url}"];
        });

        $this->registerCommand('search', $this->speechPatterns['navigation_commands']['search'], function($command) {
            $query = $command['parameters']['query'] ?? '';
            return ['success' => true, 'action' => 'search', 'query' => $query, 'message' => "Searching for {$query}"];
        });

        $this->registerCommand('help', $this->speechPatterns['system_commands']['help'], function($command) {
            $availableCommands = $this->getAvailableCommands();
            return [
                'success' => true,
                'action' => 'help',
                'message' => 'Available voice commands',
                'commands' => $availableCommands
            ];
        });

        $this->logger->info('Default voice commands registered');
    }
}
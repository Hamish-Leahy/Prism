<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use React\EventLoop\LoopInterface;

class DeveloperToolsService
{
    private Logger $logger;
    private Client $httpClient;
    private LoopInterface $loop;
    private array $config;
    private array $debugSessions = [];
    private array $performanceProfiles = [];
    private array $networkRequests = [];
    private array $consoleLogs = [];
    private array $errorReports = [];
    private bool $isEnabled = false;
    private array $breakpoints = [];
    private array $inspectorElements = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Developer Tools Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Developer Tools Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->startPerformanceMonitoring();
            $this->startNetworkMonitoring();
            $this->startConsoleMonitoring();
            
            $this->logger->info("Developer Tools Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Developer Tools Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function startDebugSession(string $sessionId, array $options = []): array
    {
        if (!$this->isEnabled) {
            return ['error' => 'Developer tools are disabled'];
        }

        try {
            $debugSession = [
                'id' => $sessionId,
                'started_at' => microtime(true),
                'options' => $options,
                'breakpoints' => [],
                'watches' => [],
                'call_stack' => [],
                'variables' => [],
                'status' => 'active'
            ];

            $this->debugSessions[$sessionId] = $debugSession;

            $this->logger->info("Debug session started", [
                'session_id' => $sessionId,
                'options' => $options
            ]);

            return [
                'session_id' => $sessionId,
                'status' => 'active',
                'breakpoints' => [],
                'watches' => []
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to start debug session", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to start debug session'];
        }
    }

    public function setBreakpoint(string $sessionId, string $file, int $line, array $condition = null): bool
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return false;
        }

        try {
            $breakpoint = [
                'id' => uniqid('bp_'),
                'file' => $file,
                'line' => $line,
                'condition' => $condition,
                'hit_count' => 0,
                'created_at' => microtime(true)
            ];

            $this->debugSessions[$sessionId]['breakpoints'][] = $breakpoint;
            $this->breakpoints[$breakpoint['id']] = $breakpoint;

            $this->logger->debug("Breakpoint set", [
                'session_id' => $sessionId,
                'file' => $file,
                'line' => $line
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to set breakpoint", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function removeBreakpoint(string $sessionId, string $breakpointId): bool
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return false;
        }

        try {
            $session = &$this->debugSessions[$sessionId];
            $breakpoints = &$session['breakpoints'];

            foreach ($breakpoints as $index => $breakpoint) {
                if ($breakpoint['id'] === $breakpointId) {
                    unset($breakpoints[$index]);
                    unset($this->breakpoints[$breakpointId]);
                    $breakpoints = array_values($breakpoints); // Re-index array
                    
                    $this->logger->debug("Breakpoint removed", [
                        'session_id' => $sessionId,
                        'breakpoint_id' => $breakpointId
                    ]);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error("Failed to remove breakpoint", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function addWatch(string $sessionId, string $expression): bool
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return false;
        }

        try {
            $watch = [
                'id' => uniqid('watch_'),
                'expression' => $expression,
                'value' => null,
                'type' => null,
                'created_at' => microtime(true)
            ];

            $this->debugSessions[$sessionId]['watches'][] = $watch;

            $this->logger->debug("Watch added", [
                'session_id' => $sessionId,
                'expression' => $expression
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add watch", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function stepOver(string $sessionId): array
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return ['error' => 'Debug session not found'];
        }

        try {
            $session = &$this->debugSessions[$sessionId];
            
            // Simulate stepping over
            $currentLine = $this->getCurrentLine($session);
            $nextLine = $currentLine + 1;
            
            $session['call_stack'][] = [
                'file' => 'current_file.js',
                'line' => $nextLine,
                'function' => 'currentFunction',
                'timestamp' => microtime(true)
            ];

            $this->logger->debug("Stepped over", [
                'session_id' => $sessionId,
                'line' => $nextLine
            ]);

            return [
                'current_line' => $nextLine,
                'call_stack' => $session['call_stack'],
                'variables' => $this->getCurrentVariables($session)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to step over", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to step over'];
        }
    }

    public function stepInto(string $sessionId): array
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return ['error' => 'Debug session not found'];
        }

        try {
            $session = &$this->debugSessions[$sessionId];
            
            // Simulate stepping into
            $currentLine = $this->getCurrentLine($session);
            
            $session['call_stack'][] = [
                'file' => 'current_file.js',
                'line' => $currentLine,
                'function' => 'newFunction',
                'timestamp' => microtime(true)
            ];

            $this->logger->debug("Stepped into", [
                'session_id' => $sessionId,
                'line' => $currentLine
            ]);

            return [
                'current_line' => $currentLine,
                'call_stack' => $session['call_stack'],
                'variables' => $this->getCurrentVariables($session)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to step into", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to step into'];
        }
    }

    public function stepOut(string $sessionId): array
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return ['error' => 'Debug session not found'];
        }

        try {
            $session = &$this->debugSessions[$sessionId];
            
            // Simulate stepping out
            if (!empty($session['call_stack'])) {
                array_pop($session['call_stack']);
            }

            $currentLine = $this->getCurrentLine($session);

            $this->logger->debug("Stepped out", [
                'session_id' => $sessionId,
                'line' => $currentLine
            ]);

            return [
                'current_line' => $currentLine,
                'call_stack' => $session['call_stack'],
                'variables' => $this->getCurrentVariables($session)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to step out", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to step out'];
        }
    }

    public function getPerformanceProfile(string $profileId = null): array
    {
        if ($profileId && isset($this->performanceProfiles[$profileId])) {
            return $this->performanceProfiles[$profileId];
        }

        // Return latest profile or create a new one
        if (empty($this->performanceProfiles)) {
            return $this->createPerformanceProfile();
        }

        $latestProfile = end($this->performanceProfiles);
        return $latestProfile;
    }

    public function startPerformanceProfiling(string $profileName = 'Default'): string
    {
        try {
            $profileId = uniqid('profile_');
            $profile = [
                'id' => $profileId,
                'name' => $profileName,
                'started_at' => microtime(true),
                'samples' => [],
                'memory_usage' => [],
                'cpu_usage' => [],
                'network_requests' => [],
                'status' => 'running'
            ];

            $this->performanceProfiles[$profileId] = $profile;

            $this->logger->info("Performance profiling started", [
                'profile_id' => $profileId,
                'name' => $profileName
            ]);

            return $profileId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to start performance profiling", [
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    public function stopPerformanceProfiling(string $profileId): array
    {
        if (!isset($this->performanceProfiles[$profileId])) {
            return ['error' => 'Profile not found'];
        }

        try {
            $profile = &$this->performanceProfiles[$profileId];
            $profile['status'] = 'stopped';
            $profile['stopped_at'] = microtime(true);
            $profile['duration'] = $profile['stopped_at'] - $profile['started_at'];

            // Generate performance report
            $report = $this->generatePerformanceReport($profile);

            $this->logger->info("Performance profiling stopped", [
                'profile_id' => $profileId,
                'duration' => $profile['duration']
            ]);

            return $report;
        } catch (\Exception $e) {
            $this->logger->error("Failed to stop performance profiling", [
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to stop profiling'];
        }
    }

    public function getNetworkRequests(string $filter = null): array
    {
        if ($filter) {
            return array_filter($this->networkRequests, function($request) use ($filter) {
                return strpos($request['url'], $filter) !== false;
            });
        }

        return $this->networkRequests;
    }

    public function getConsoleLogs(string $level = null): array
    {
        if ($level) {
            return array_filter($this->consoleLogs, function($log) use ($level) {
                return $log['level'] === $level;
            });
        }

        return $this->consoleLogs;
    }

    public function getErrorReports(): array
    {
        return $this->errorReports;
    }

    public function inspectElement(string $selector): array
    {
        try {
            $element = [
                'selector' => $selector,
                'tag_name' => 'div',
                'attributes' => [
                    'id' => 'example-id',
                    'class' => 'example-class',
                    'data-test' => 'example-data'
                ],
                'styles' => [
                    'display' => 'block',
                    'width' => '100px',
                    'height' => '50px',
                    'background-color' => '#ffffff'
                ],
                'computed_styles' => [
                    'display' => 'block',
                    'width' => '100px',
                    'height' => '50px',
                    'background-color' => 'rgb(255, 255, 255)'
                ],
                'bounding_rect' => [
                    'x' => 100,
                    'y' => 200,
                    'width' => 100,
                    'height' => 50
                ],
                'children' => [],
                'parent' => null,
                'inspected_at' => microtime(true)
            ];

            $this->inspectorElements[$selector] = $element;

            $this->logger->debug("Element inspected", [
                'selector' => $selector
            ]);

            return $element;
        } catch (\Exception $e) {
            $this->logger->error("Failed to inspect element", [
                'selector' => $selector,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to inspect element'];
        }
    }

    public function modifyElement(string $selector, array $changes): bool
    {
        try {
            if (!isset($this->inspectorElements[$selector])) {
                return false;
            }

            $element = &$this->inspectorElements[$selector];

            foreach ($changes as $property => $value) {
                if (isset($element['styles'][$property])) {
                    $element['styles'][$property] = $value;
                }
            }

            $this->logger->debug("Element modified", [
                'selector' => $selector,
                'changes' => $changes
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to modify element", [
                'selector' => $selector,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);

        return [
            'current' => $memoryUsage,
            'peak' => $peakMemoryUsage,
            'current_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($peakMemoryUsage / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit'),
            'timestamp' => microtime(true)
        ];
    }

    public function getCPUUsage(): array
    {
        $load = sys_getloadavg();
        
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2],
            'cpu_count' => $this->getCPUCount(),
            'timestamp' => microtime(true)
        ];
    }

    public function generateDebugReport(string $sessionId): array
    {
        if (!isset($this->debugSessions[$sessionId])) {
            return ['error' => 'Debug session not found'];
        }

        $session = $this->debugSessions[$sessionId];
        
        return [
            'session_id' => $sessionId,
            'duration' => microtime(true) - $session['started_at'],
            'breakpoints_hit' => array_sum(array_column($session['breakpoints'], 'hit_count')),
            'total_breakpoints' => count($session['breakpoints']),
            'watches' => count($session['watches']),
            'call_stack_depth' => count($session['call_stack']),
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCPUUsage(),
            'network_requests' => count($this->networkRequests),
            'console_logs' => count($this->consoleLogs),
            'errors' => count($this->errorReports)
        ];
    }

    private function getCurrentLine(array $session): int
    {
        if (empty($session['call_stack'])) {
            return 1;
        }

        $currentFrame = end($session['call_stack']);
        return $currentFrame['line'] ?? 1;
    }

    private function getCurrentVariables(array $session): array
    {
        return [
            'local' => [
                'variable1' => 'value1',
                'variable2' => 'value2'
            ],
            'global' => [
                'window' => 'object',
                'document' => 'object'
            ],
            'this' => 'object'
        ];
    }

    private function createPerformanceProfile(): array
    {
        $profileId = uniqid('profile_');
        $profile = [
            'id' => $profileId,
            'name' => 'Auto-generated',
            'started_at' => microtime(true),
            'samples' => [],
            'memory_usage' => [],
            'cpu_usage' => [],
            'network_requests' => [],
            'status' => 'completed'
        ];

        $this->performanceProfiles[$profileId] = $profile;
        return $profile;
    }

    private function generatePerformanceReport(array $profile): array
    {
        $samples = $profile['samples'];
        $memoryUsage = $profile['memory_usage'];
        $cpuUsage = $profile['cpu_usage'];

        return [
            'profile_id' => $profile['id'],
            'name' => $profile['name'],
            'duration' => $profile['duration'],
            'samples_count' => count($samples),
            'average_memory' => !empty($memoryUsage) ? array_sum($memoryUsage) / count($memoryUsage) : 0,
            'peak_memory' => !empty($memoryUsage) ? max($memoryUsage) : 0,
            'average_cpu' => !empty($cpuUsage) ? array_sum($cpuUsage) / count($cpuUsage) : 0,
            'peak_cpu' => !empty($cpuUsage) ? max($cpuUsage) : 0,
            'network_requests' => count($profile['network_requests']),
            'performance_score' => $this->calculatePerformanceScore($profile)
        ];
    }

    private function calculatePerformanceScore(array $profile): int
    {
        $score = 100;
        
        // Deduct points for high memory usage
        if (!empty($profile['memory_usage'])) {
            $avgMemory = array_sum($profile['memory_usage']) / count($profile['memory_usage']);
            if ($avgMemory > 100 * 1024 * 1024) { // 100MB
                $score -= 20;
            }
        }
        
        // Deduct points for high CPU usage
        if (!empty($profile['cpu_usage'])) {
            $avgCpu = array_sum($profile['cpu_usage']) / count($profile['cpu_usage']);
            if ($avgCpu > 80) {
                $score -= 30;
            }
        }
        
        // Deduct points for many network requests
        if (count($profile['network_requests']) > 50) {
            $score -= 10;
        }
        
        return max(0, $score);
    }

    private function getCPUCount(): int
    {
        if (function_exists('proc_open')) {
            $process = proc_open('nproc', [], $pipes);
            if ($process) {
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                proc_close($process);
                return (int)trim($output);
            }
        }
        
        return 1; // Default fallback
    }

    private function startPerformanceMonitoring(): void
    {
        if (!$this->loop) {
            return;
        }

        // Monitor performance every 5 seconds
        $this->loop->addPeriodicTimer(5.0, function() {
            $this->collectPerformanceData();
        });
    }

    private function startNetworkMonitoring(): void
    {
        if (!$this->loop) {
            return;
        }

        // Monitor network requests every 1 second
        $this->loop->addPeriodicTimer(1.0, function() {
            $this->collectNetworkData();
        });
    }

    private function startConsoleMonitoring(): void
    {
        if (!$this->loop) {
            return;
        }

        // Monitor console logs every 2 seconds
        $this->loop->addPeriodicTimer(2.0, function() {
            $this->collectConsoleData();
        });
    }

    private function collectPerformanceData(): void
    {
        $memoryUsage = memory_get_usage(true);
        $cpuUsage = $this->getCPUUsage()['load_1min'] * 100;

        // Add to current profile if running
        foreach ($this->performanceProfiles as &$profile) {
            if ($profile['status'] === 'running') {
                $profile['memory_usage'][] = $memoryUsage;
                $profile['cpu_usage'][] = $cpuUsage;
            }
        }
    }

    private function collectNetworkData(): void
    {
        // This would collect actual network request data
        // For now, add mock data
        $this->networkRequests[] = [
            'url' => 'https://api.example.com/data',
            'method' => 'GET',
            'status' => 200,
            'duration' => rand(100, 500),
            'timestamp' => microtime(true)
        ];
    }

    private function collectConsoleData(): void
    {
        // This would collect actual console log data
        // For now, add mock data
        $levels = ['log', 'warn', 'error', 'info'];
        $this->consoleLogs[] = [
            'level' => $levels[array_rand($levels)],
            'message' => 'Sample console message',
            'timestamp' => microtime(true),
            'source' => 'app.js:123'
        ];
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->debugSessions = [];
        $this->performanceProfiles = [];
        $this->networkRequests = [];
        $this->consoleLogs = [];
        $this->errorReports = [];
        $this->breakpoints = [];
        $this->inspectorElements = [];
        $this->isEnabled = false;
        $this->logger->info("Developer Tools Service cleaned up");
    }
}

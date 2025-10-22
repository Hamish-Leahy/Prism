<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;

class PerformanceMonitorService
{
    private Logger $logger;
    private array $metrics = [];
    private array $thresholds = [
        'memory_usage' => 80, // percentage
        'cpu_usage' => 70,    // percentage
        'response_time' => 1000, // milliseconds
        'tab_load_time' => 3000, // milliseconds
        'memory_leak_threshold' => 100 // MB increase per hour
    ];
    private array $alerts = [];
    private bool $isMonitoring = false;
    private array $realTimeSubscribers = [];
    private array $performanceHistory = [];
    private array $engineMetrics = [];
    private ?\React\EventLoop\LoopInterface $loop = null;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function startMonitoring(): bool
    {
        try {
            $this->isMonitoring = true;
            $this->logger->info('Performance monitoring started');
            
            // Start background monitoring
            $this->scheduleMonitoring();
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to start performance monitoring: ' . $e->getMessage());
            return false;
        }
    }

    public function stopMonitoring(): bool
    {
        $this->isMonitoring = false;
        $this->logger->info('Performance monitoring stopped');
        return true;
    }

    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        $timestamp = microtime(true);
        
        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => $timestamp
        ];

        // Check thresholds
        $this->checkThresholds($name, $value, $tags);
        
        // Keep only last 1000 metrics
        if (count($this->metrics) > 1000) {
            $this->metrics = array_slice($this->metrics, -1000);
        }
    }

    public function getPerformanceReport(): array
    {
        $report = [
            'timestamp' => date('c'),
            'is_monitoring' => $this->isMonitoring,
            'current_metrics' => $this->getCurrentMetrics(),
            'trends' => $this->getTrends(),
            'alerts' => $this->alerts,
            'recommendations' => $this->getRecommendations()
        ];

        return $report;
    }

    public function getMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        return [
            'current' => $memoryUsage,
            'peak' => $memoryPeak,
            'limit' => $memoryLimit,
            'percentage' => ($memoryUsage / $memoryLimit) * 100,
            'formatted' => [
                'current' => $this->formatBytes($memoryUsage),
                'peak' => $this->formatBytes($memoryPeak),
                'limit' => $this->formatBytes($memoryLimit)
            ]
        ];
    }

    public function getCPUUsage(): float
    {
        // Simple CPU usage calculation
        $load = sys_getloadavg();
        return $load[0] * 100; // Convert to percentage
    }

    public function getTabPerformance(string $tabId): array
    {
        $tabMetrics = array_filter($this->metrics, function($metric) use ($tabId) {
            return isset($metric['tags']['tab_id']) && $metric['tags']['tab_id'] === $tabId;
        });

        return [
            'tab_id' => $tabId,
            'load_time' => $this->getMetricValue($tabMetrics, 'tab_load_time'),
            'memory_usage' => $this->getMetricValue($tabMetrics, 'tab_memory_usage'),
            'render_time' => $this->getMetricValue($tabMetrics, 'tab_render_time'),
            'network_requests' => $this->getMetricValue($tabMetrics, 'tab_network_requests'),
            'javascript_errors' => $this->getMetricValue($tabMetrics, 'tab_js_errors')
        ];
    }

    public function detectMemoryLeaks(): array
    {
        $memoryHistory = array_filter($this->metrics, function($metric) {
            return $metric['name'] === 'memory_usage';
        });

        if (count($memoryHistory) < 10) {
            return ['leak_detected' => false, 'message' => 'Insufficient data'];
        }

        $recent = array_slice($memoryHistory, -10);
        $older = array_slice($memoryHistory, -20, 10);

        $recentAvg = array_sum(array_column($recent, 'value')) / count($recent);
        $olderAvg = array_sum(array_column($older, 'value')) / count($older);

        $increase = $recentAvg - $olderAvg;
        $leakDetected = $increase > $this->thresholds['memory_leak_threshold'];

        return [
            'leak_detected' => $leakDetected,
            'increase_mb' => $increase,
            'recent_average' => $recentAvg,
            'older_average' => $olderAvg,
            'threshold' => $this->thresholds['memory_leak_threshold']
        ];
    }

    public function optimizeMemory(): array
    {
        $optimizations = [];
        
        // Clear old metrics
        if (count($this->metrics) > 500) {
            $this->metrics = array_slice($this->metrics, -500);
            $optimizations[] = 'Cleared old metrics';
        }

        // Clear alerts older than 1 hour
        $oneHourAgo = time() - 3600;
        $this->alerts = array_filter($this->alerts, function($alert) use ($oneHourAgo) {
            return $alert['timestamp'] > $oneHourAgo;
        });
        $optimizations[] = 'Cleared old alerts';

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            $optimizations[] = "Garbage collected {$collected} cycles";
        }

        return $optimizations;
    }

    public function setThreshold(string $metric, float $value): void
    {
        if (isset($this->thresholds[$metric])) {
            $this->thresholds[$metric] = $value;
            $this->logger->info('Performance threshold updated', [
                'metric' => $metric,
                'value' => $value
            ]);
        }
    }

    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    private function scheduleMonitoring(): void
    {
        // In a real implementation, this would use a proper task scheduler
        // For now, we'll just log that monitoring is scheduled
        $this->logger->info('Performance monitoring scheduled');
    }

    private function checkThresholds(string $name, float $value, array $tags): void
    {
        $threshold = $this->thresholds[$name] ?? null;
        
        if ($threshold && $value > $threshold) {
            $alert = [
                'id' => uniqid(),
                'type' => 'threshold_exceeded',
                'metric' => $name,
                'value' => $value,
                'threshold' => $threshold,
                'tags' => $tags,
                'timestamp' => time(),
                'severity' => $this->getSeverity($name, $value, $threshold)
            ];

            $this->alerts[] = $alert;
            $this->logger->warning('Performance threshold exceeded', $alert);
        }
    }

    private function getSeverity(string $metric, float $value, float $threshold): string
    {
        $ratio = $value / $threshold;
        
        if ($ratio > 2.0) {
            return 'critical';
        } elseif ($ratio > 1.5) {
            return 'high';
        } elseif ($ratio > 1.2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function getCurrentMetrics(): array
    {
        $current = [];
        $metricNames = array_unique(array_column($this->metrics, 'name'));
        
        foreach ($metricNames as $name) {
            $recent = array_filter($this->metrics, function($metric) use ($name) {
                return $metric['name'] === $name && 
                       (microtime(true) - $metric['timestamp']) < 60; // Last minute
            });
            
            if (!empty($recent)) {
                $values = array_column($recent, 'value');
                $current[$name] = [
                    'current' => end($values),
                    'average' => array_sum($values) / count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'count' => count($values)
                ];
            }
        }
        
        return $current;
    }

    private function getTrends(): array
    {
        $trends = [];
        $metricNames = array_unique(array_column($this->metrics, 'name'));
        
        foreach ($metricNames as $name) {
            $metricData = array_filter($this->metrics, function($metric) use ($name) {
                return $metric['name'] === $name;
            });
            
            if (count($metricData) >= 10) {
                $recent = array_slice($metricData, -5);
                $older = array_slice($metricData, -10, 5);
                
                $recentAvg = array_sum(array_column($recent, 'value')) / count($recent);
                $olderAvg = array_sum(array_column($older, 'value')) / count($older);
                
                $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;
                
                $trends[$name] = [
                    'change_percentage' => round($change, 2),
                    'direction' => $change > 0 ? 'increasing' : 'decreasing',
                    'recent_average' => $recentAvg,
                    'older_average' => $olderAvg
                ];
            }
        }
        
        return $trends;
    }

    private function getRecommendations(): array
    {
        $recommendations = [];
        $current = $this->getCurrentMetrics();
        
        // Memory recommendations
        if (isset($current['memory_usage'])) {
            $memory = $current['memory_usage']['current'];
            if ($memory > $this->thresholds['memory_usage']) {
                $recommendations[] = [
                    'type' => 'memory',
                    'priority' => 'high',
                    'message' => 'High memory usage detected. Consider closing unused tabs or restarting the browser.',
                    'action' => 'optimize_memory'
                ];
            }
        }
        
        // CPU recommendations
        if (isset($current['cpu_usage'])) {
            $cpu = $current['cpu_usage']['current'];
            if ($cpu > $this->thresholds['cpu_usage']) {
                $recommendations[] = [
                    'type' => 'cpu',
                    'priority' => 'medium',
                    'message' => 'High CPU usage detected. Check for resource-intensive tabs or extensions.',
                    'action' => 'check_tabs'
                ];
            }
        }
        
        // Response time recommendations
        if (isset($current['response_time'])) {
            $responseTime = $current['response_time']['current'];
            if ($responseTime > $this->thresholds['response_time']) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => 'Slow response times detected. Check network connection or server performance.',
                    'action' => 'check_network'
                ];
            }
        }
        
        return $recommendations;
    }

    private function getMetricValue(array $metrics, string $name): float
    {
        $metric = array_filter($metrics, function($m) use ($name) {
            return $m['name'] === $name;
        });
        
        return !empty($metric) ? end($metric)['value'] : 0;
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        return $this->parseMemoryLimit($limit);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    // Real-time monitoring methods
    public function subscribeToRealTimeUpdates(callable $callback): string
    {
        $subscriberId = uniqid('sub_');
        $this->realTimeSubscribers[$subscriberId] = $callback;
        
        $this->logger->info('New real-time subscriber added', ['subscriber_id' => $subscriberId]);
        
        return $subscriberId;
    }

    public function unsubscribeFromRealTimeUpdates(string $subscriberId): bool
    {
        if (isset($this->realTimeSubscribers[$subscriberId])) {
            unset($this->realTimeSubscribers[$subscriberId]);
            $this->logger->info('Real-time subscriber removed', ['subscriber_id' => $subscriberId]);
            return true;
        }
        
        return false;
    }

    public function startRealTimeMonitoring(): bool
    {
        try {
            if ($this->loop === null) {
                $this->loop = Loop::get();
            }

            // Schedule periodic monitoring
            $this->loop->addPeriodicTimer(1.0, function() {
                $this->collectRealTimeMetrics();
            });

            $this->loop->addPeriodicTimer(5.0, function() {
                $this->broadcastMetrics();
            });

            $this->logger->info('Real-time monitoring started');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to start real-time monitoring: ' . $e->getMessage());
            return false;
        }
    }

    public function stopRealTimeMonitoring(): bool
    {
        if ($this->loop) {
            $this->loop->stop();
            $this->loop = null;
        }
        
        $this->realTimeSubscribers = [];
        $this->logger->info('Real-time monitoring stopped');
        return true;
    }

    private function collectRealTimeMetrics(): void
    {
        $timestamp = microtime(true);
        
        // Collect system metrics
        $memoryUsage = $this->getMemoryUsage();
        $cpuUsage = $this->getCPUUsage();
        
        // Record metrics
        $this->recordMetric('memory_usage', $memoryUsage['percentage']);
        $this->recordMetric('cpu_usage', $cpuUsage);
        $this->recordMetric('active_tabs', $this->getActiveTabCount());
        $this->recordMetric('network_requests', $this->getNetworkRequestCount());
        
        // Store in performance history
        $this->performanceHistory[] = [
            'timestamp' => $timestamp,
            'memory' => $memoryUsage,
            'cpu' => $cpuUsage,
            'tabs' => $this->getActiveTabCount(),
            'requests' => $this->getNetworkRequestCount()
        ];
        
        // Keep only last 1000 entries
        if (count($this->performanceHistory) > 1000) {
            $this->performanceHistory = array_slice($this->performanceHistory, -1000);
        }
    }

    private function broadcastMetrics(): void
    {
        if (empty($this->realTimeSubscribers)) {
            return;
        }

        $currentMetrics = $this->getCurrentMetrics();
        $recentAlerts = array_slice($this->alerts, -5);
        
        $data = [
            'type' => 'performance_update',
            'timestamp' => microtime(true),
            'metrics' => $currentMetrics,
            'alerts' => $recentAlerts,
            'trends' => $this->getTrends()
        ];

        foreach ($this->realTimeSubscribers as $subscriberId => $callback) {
            try {
                $callback($data);
            } catch (\Exception $e) {
                $this->logger->error('Error broadcasting to subscriber', [
                    'subscriber_id' => $subscriberId,
                    'error' => $e->getMessage()
                ]);
                unset($this->realTimeSubscribers[$subscriberId]);
            }
        }
    }

    public function getEnginePerformance(string $engineName): array
    {
        $engineMetrics = array_filter($this->metrics, function($metric) use ($engineName) {
            return isset($metric['tags']['engine']) && $metric['tags']['engine'] === $engineName;
        });

        if (empty($engineMetrics)) {
            return [
                'engine' => $engineName,
                'status' => 'no_data',
                'metrics' => []
            ];
        }

        return [
            'engine' => $engineName,
            'status' => 'active',
            'metrics' => [
                'average_load_time' => $this->getMetricAverage($engineMetrics, 'engine_load_time'),
                'memory_usage' => $this->getMetricAverage($engineMetrics, 'engine_memory'),
                'cpu_usage' => $this->getMetricAverage($engineMetrics, 'engine_cpu'),
                'error_rate' => $this->getMetricAverage($engineMetrics, 'engine_errors'),
                'request_count' => count($engineMetrics)
            ],
            'last_updated' => end($engineMetrics)['timestamp']
        ];
    }

    public function getAllEnginesPerformance(): array
    {
        $engines = ['prism', 'chromium', 'firefox'];
        $performance = [];
        
        foreach ($engines as $engine) {
            $performance[$engine] = $this->getEnginePerformance($engine);
        }
        
        return $performance;
    }

    public function getPerformanceHistory(int $limit = 100): array
    {
        return array_slice($this->performanceHistory, -$limit);
    }

    public function generatePerformanceReport(string $period = '1h'): array
    {
        $now = microtime(true);
        $periodSeconds = $this->parsePeriod($period);
        $startTime = $now - $periodSeconds;
        
        $relevantMetrics = array_filter($this->metrics, function($metric) use ($startTime) {
            return $metric['timestamp'] >= $startTime;
        });
        
        $report = [
            'period' => $period,
            'start_time' => $startTime,
            'end_time' => $now,
            'summary' => $this->generateSummary($relevantMetrics),
            'engine_performance' => $this->getAllEnginesPerformance(),
            'memory_analysis' => $this->analyzeMemoryUsage($relevantMetrics),
            'cpu_analysis' => $this->analyzeCPUUsage($relevantMetrics),
            'recommendations' => $this->getAdvancedRecommendations($relevantMetrics)
        ];
        
        return $report;
    }

    private function getActiveTabCount(): int
    {
        // This would integrate with the TabController to get actual tab count
        // For now, return a simulated count
        return rand(1, 20);
    }

    private function getNetworkRequestCount(): int
    {
        // This would integrate with HttpClientService to get actual request count
        // For now, return a simulated count
        return rand(0, 100);
    }

    private function getMetricAverage(array $metrics, string $name): float
    {
        $filtered = array_filter($metrics, function($metric) use ($name) {
            return $metric['name'] === $name;
        });
        
        if (empty($filtered)) {
            return 0;
        }
        
        $values = array_column($filtered, 'value');
        return array_sum($values) / count($values);
    }

    private function parsePeriod(string $period): int
    {
        $periods = [
            '1m' => 60,
            '5m' => 300,
            '15m' => 900,
            '1h' => 3600,
            '6h' => 21600,
            '1d' => 86400,
            '1w' => 604800
        ];
        
        return $periods[$period] ?? 3600;
    }

    private function generateSummary(array $metrics): array
    {
        $metricNames = array_unique(array_column($metrics, 'name'));
        $summary = [];
        
        foreach ($metricNames as $name) {
            $values = array_column(array_filter($metrics, function($m) use ($name) {
                return $m['name'] === $name;
            }), 'value');
            
            if (!empty($values)) {
                $summary[$name] = [
                    'count' => count($values),
                    'average' => array_sum($values) / count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'latest' => end($values)
                ];
            }
        }
        
        return $summary;
    }

    private function analyzeMemoryUsage(array $metrics): array
    {
        $memoryMetrics = array_filter($metrics, function($metric) {
            return $metric['name'] === 'memory_usage';
        });
        
        if (empty($memoryMetrics)) {
            return ['status' => 'no_data'];
        }
        
        $values = array_column($memoryMetrics, 'value');
        $average = array_sum($values) / count($values);
        $peak = max($values);
        
        return [
            'average_usage' => $average,
            'peak_usage' => $peak,
            'trend' => $this->calculateTrend($values),
            'status' => $peak > $this->thresholds['memory_usage'] ? 'warning' : 'normal'
        ];
    }

    private function analyzeCPUUsage(array $metrics): array
    {
        $cpuMetrics = array_filter($metrics, function($metric) {
            return $metric['name'] === 'cpu_usage';
        });
        
        if (empty($cpuMetrics)) {
            return ['status' => 'no_data'];
        }
        
        $values = array_column($cpuMetrics, 'value');
        $average = array_sum($values) / count($values);
        $peak = max($values);
        
        return [
            'average_usage' => $average,
            'peak_usage' => $peak,
            'trend' => $this->calculateTrend($values),
            'status' => $peak > $this->thresholds['cpu_usage'] ? 'warning' : 'normal'
        ];
    }

    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'stable';
        }
        
        $recent = array_slice($values, -5);
        $older = array_slice($values, -10, 5);
        
        if (empty($older)) {
            return 'stable';
        }
        
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        
        $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;
        
        if ($change > 10) {
            return 'increasing';
        } elseif ($change < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    private function getAdvancedRecommendations(array $metrics): array
    {
        $recommendations = [];
        
        // Memory recommendations
        $memoryAnalysis = $this->analyzeMemoryUsage($metrics);
        if ($memoryAnalysis['status'] === 'warning') {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'title' => 'High Memory Usage Detected',
                'description' => 'Memory usage is consistently high. Consider closing unused tabs or restarting the browser.',
                'actions' => [
                    'Close unused tabs',
                    'Restart browser',
                    'Check for memory leaks'
                ]
            ];
        }
        
        // CPU recommendations
        $cpuAnalysis = $this->analyzeCPUUsage($metrics);
        if ($cpuAnalysis['status'] === 'warning') {
            $recommendations[] = [
                'type' => 'cpu',
                'priority' => 'medium',
                'title' => 'High CPU Usage Detected',
                'description' => 'CPU usage is high. Check for resource-intensive tabs or extensions.',
                'actions' => [
                    'Check active tabs',
                    'Disable heavy extensions',
                    'Update browser'
                ]
            ];
        }
        
        // Performance recommendations
        $loadTimeMetrics = array_filter($metrics, function($metric) {
            return $metric['name'] === 'tab_load_time';
        });
        
        if (!empty($loadTimeMetrics)) {
            $avgLoadTime = array_sum(array_column($loadTimeMetrics, 'value')) / count($loadTimeMetrics);
            if ($avgLoadTime > $this->thresholds['tab_load_time']) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'title' => 'Slow Page Loading',
                    'description' => 'Page loading times are slower than expected.',
                    'actions' => [
                        'Check network connection',
                        'Clear browser cache',
                        'Disable unnecessary extensions'
                    ]
                ];
            }
        }
        
        return $recommendations;
    }
}

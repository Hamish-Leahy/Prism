<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class AnalyticsPlugin extends BasePlugin
{
    private array $analytics = [];
    private array $events = [];
    private bool $isEnabled = false;
    private array $config = [];
    private int $maxEvents = 10000;
    private array $metrics = [];

    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct($config, $logger);
        $this->config = $config;
    }

    public function initialize(): bool
    {
        try {
            $this->loadConfiguration();
            $this->logger->info('Analytics plugin initialized');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Analytics plugin: ' . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->isEnabled = true;
        $this->logger->info('Analytics plugin enabled');
        return true;
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->logger->info('Analytics plugin disabled');
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Analytics',
            'version' => '1.0.0',
            'description' => 'Tracks browser usage analytics and metrics',
            'author' => 'Prism Team',
            'enabled' => $this->isEnabled,
            'events_tracked' => count($this->events),
            'metrics_count' => count($this->metrics)
        ];
    }

    public function onEvent(string $eventName, array $data = []): mixed
    {
        if (!$this->isEnabled) {
            return null;
        }

        switch ($eventName) {
            case 'page_load':
                return $this->trackPageLoad($data);
            case 'tab_created':
                return $this->trackTabCreated($data);
            case 'tab_closed':
                return $this->trackTabClosed($data);
            case 'bookmark_created':
                return $this->trackBookmarkCreated($data);
            case 'download_started':
                return $this->trackDownloadStarted($data);
            case 'search_performed':
                return $this->trackSearch($data);
            case 'gesture_performed':
                return $this->trackGesture($data);
            case 'voice_command':
                return $this->trackVoiceCommand($data);
            default:
                return $this->trackGenericEvent($eventName, $data);
        }
    }

    public function trackEvent(string $eventName, array $data = []): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        $event = [
            'name' => $eventName,
            'data' => $data,
            'timestamp' => microtime(true),
            'date' => date('Y-m-d H:i:s'),
            'session_id' => $this->getSessionId(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $this->events[] = $event;

        // Keep events array manageable
        if (count($this->events) > $this->maxEvents) {
            $this->events = array_slice($this->events, -$this->maxEvents);
        }

        $this->updateMetrics($eventName, $data);

        $this->logger->debug('Event tracked', [
            'event' => $eventName,
            'data' => $data
        ]);

        return true;
    }

    public function getAnalytics(string $period = '1d'): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        $startTime = $this->getPeriodStartTime($period);
        $filteredEvents = $this->filterEventsByTime($this->events, $startTime);

        return [
            'period' => $period,
            'start_time' => $startTime,
            'end_time' => microtime(true),
            'total_events' => count($filteredEvents),
            'event_counts' => $this->getEventCounts($filteredEvents),
            'page_views' => $this->getPageViews($filteredEvents),
            'tab_activity' => $this->getTabActivity($filteredEvents),
            'search_activity' => $this->getSearchActivity($filteredEvents),
            'download_activity' => $this->getDownloadActivity($filteredEvents),
            'gesture_activity' => $this->getGestureActivity($filteredEvents),
            'voice_activity' => $this->getVoiceActivity($filteredEvents),
            'top_domains' => $this->getTopDomains($filteredEvents),
            'session_stats' => $this->getSessionStats($filteredEvents),
            'performance_metrics' => $this->getPerformanceMetrics($filteredEvents)
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getEvents(string $eventName = null, int $limit = 100): array
    {
        $events = $this->events;

        if ($eventName) {
            $events = array_filter($events, function($event) use ($eventName) {
                return $event['name'] === $eventName;
            });
        }

        return array_slice($events, -$limit);
    }

    public function clearAnalytics(): bool
    {
        $this->events = [];
        $this->metrics = [];
        $this->logger->info('Analytics data cleared');
        return true;
    }

    public function exportAnalytics(string $format = 'json'): string
    {
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'total_events' => count($this->events),
            'events' => $this->events,
            'metrics' => $this->metrics
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($data);
            default:
                return json_encode($data);
        }
    }

    private function trackPageLoad(array $data): array
    {
        $this->trackEvent('page_load', $data);
        return ['tracked' => true];
    }

    private function trackTabCreated(array $data): array
    {
        $this->trackEvent('tab_created', $data);
        return ['tracked' => true];
    }

    private function trackTabClosed(array $data): array
    {
        $this->trackEvent('tab_closed', $data);
        return ['tracked' => true];
    }

    private function trackBookmarkCreated(array $data): array
    {
        $this->trackEvent('bookmark_created', $data);
        return ['tracked' => true];
    }

    private function trackDownloadStarted(array $data): array
    {
        $this->trackEvent('download_started', $data);
        return ['tracked' => true];
    }

    private function trackSearch(array $data): array
    {
        $this->trackEvent('search_performed', $data);
        return ['tracked' => true];
    }

    private function trackGesture(array $data): array
    {
        $this->trackEvent('gesture_performed', $data);
        return ['tracked' => true];
    }

    private function trackVoiceCommand(array $data): array
    {
        $this->trackEvent('voice_command', $data);
        return ['tracked' => true];
    }

    private function trackGenericEvent(string $eventName, array $data): array
    {
        $this->trackEvent($eventName, $data);
        return ['tracked' => true];
    }

    private function updateMetrics(string $eventName, array $data): void
    {
        if (!isset($this->metrics[$eventName])) {
            $this->metrics[$eventName] = [
                'count' => 0,
                'first_seen' => microtime(true),
                'last_seen' => microtime(true),
                'data_samples' => []
            ];
        }

        $this->metrics[$eventName]['count']++;
        $this->metrics[$eventName]['last_seen'] = microtime(true);

        // Keep sample data (last 100 entries)
        $this->metrics[$eventName]['data_samples'][] = $data;
        if (count($this->metrics[$eventName]['data_samples']) > 100) {
            $this->metrics[$eventName]['data_samples'] = array_slice(
                $this->metrics[$eventName]['data_samples'], -100
            );
        }
    }

    private function getPeriodStartTime(string $period): float
    {
        $now = microtime(true);
        $periods = [
            '1h' => 3600,
            '1d' => 86400,
            '1w' => 604800,
            '1m' => 2592000,
            '1y' => 31536000
        ];

        $seconds = $periods[$period] ?? 86400;
        return $now - $seconds;
    }

    private function filterEventsByTime(array $events, float $startTime): array
    {
        return array_filter($events, function($event) use ($startTime) {
            return $event['timestamp'] >= $startTime;
        });
    }

    private function getEventCounts(array $events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $name = $event['name'];
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        return $counts;
    }

    private function getPageViews(array $events): array
    {
        $pageViews = array_filter($events, function($event) {
            return $event['name'] === 'page_load';
        });

        $domains = [];
        $urls = [];

        foreach ($pageViews as $event) {
            $url = $event['data']['url'] ?? '';
            if ($url) {
                $urls[] = $url;
                $domain = parse_url($url, PHP_URL_HOST);
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        return [
            'total' => count($pageViews),
            'unique_domains' => count(array_unique($domains)),
            'unique_urls' => count(array_unique($urls)),
            'top_domains' => array_count_values($domains),
            'top_urls' => array_count_values($urls)
        ];
    }

    private function getTabActivity(array $events): array
    {
        $tabEvents = array_filter($events, function($event) {
            return in_array($event['name'], ['tab_created', 'tab_closed']);
        });

        $created = 0;
        $closed = 0;

        foreach ($tabEvents as $event) {
            if ($event['name'] === 'tab_created') {
                $created++;
            } else {
                $closed++;
            }
        }

        return [
            'tabs_created' => $created,
            'tabs_closed' => $closed,
            'net_tabs' => $created - $closed
        ];
    }

    private function getSearchActivity(array $events): array
    {
        $searchEvents = array_filter($events, function($event) {
            return $event['name'] === 'search_performed';
        });

        $queries = [];
        $engines = [];

        foreach ($searchEvents as $event) {
            $query = $event['data']['query'] ?? '';
            $engine = $event['data']['engine'] ?? 'unknown';
            
            if ($query) {
                $queries[] = $query;
            }
            $engines[] = $engine;
        }

        return [
            'total_searches' => count($searchEvents),
            'unique_queries' => count(array_unique($queries)),
            'search_engines' => array_count_values($engines),
            'top_queries' => array_count_values($queries)
        ];
    }

    private function getDownloadActivity(array $events): array
    {
        $downloadEvents = array_filter($events, function($event) {
            return $event['name'] === 'download_started';
        });

        $fileTypes = [];
        $sizes = [];

        foreach ($downloadEvents as $event) {
            $url = $event['data']['url'] ?? '';
            $size = $event['data']['size'] ?? 0;
            
            if ($url) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if ($extension) {
                    $fileTypes[] = $extension;
                }
            }
            
            if ($size > 0) {
                $sizes[] = $size;
            }
        }

        return [
            'total_downloads' => count($downloadEvents),
            'file_types' => array_count_values($fileTypes),
            'total_size' => array_sum($sizes),
            'average_size' => count($sizes) > 0 ? array_sum($sizes) / count($sizes) : 0
        ];
    }

    private function getGestureActivity(array $events): array
    {
        $gestureEvents = array_filter($events, function($event) {
            return $event['name'] === 'gesture_performed';
        });

        $gestureTypes = [];

        foreach ($gestureEvents as $event) {
            $type = $event['data']['type'] ?? 'unknown';
            $gestureTypes[] = $type;
        }

        return [
            'total_gestures' => count($gestureEvents),
            'gesture_types' => array_count_values($gestureTypes)
        ];
    }

    private function getVoiceActivity(array $events): array
    {
        $voiceEvents = array_filter($events, function($event) {
            return $event['name'] === 'voice_command';
        });

        $commands = [];

        foreach ($voiceEvents as $event) {
            $command = $event['data']['command'] ?? 'unknown';
            $commands[] = $command;
        }

        return [
            'total_commands' => count($voiceEvents),
            'command_types' => array_count_values($commands)
        ];
    }

    private function getTopDomains(array $events): array
    {
        $domains = [];

        foreach ($events as $event) {
            if (isset($event['data']['url'])) {
                $domain = parse_url($event['data']['url'], PHP_URL_HOST);
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        return array_count_values($domains);
    }

    private function getSessionStats(array $events): array
    {
        $sessions = [];
        foreach ($events as $event) {
            $sessionId = $event['session_id'];
            if (!isset($sessions[$sessionId])) {
                $sessions[$sessionId] = [
                    'start_time' => $event['timestamp'],
                    'end_time' => $event['timestamp'],
                    'event_count' => 0
                ];
            }
            $sessions[$sessionId]['end_time'] = $event['timestamp'];
            $sessions[$sessionId]['event_count']++;
        }

        $sessionDurations = array_map(function($session) {
            return $session['end_time'] - $session['start_time'];
        }, $sessions);

        return [
            'total_sessions' => count($sessions),
            'average_duration' => count($sessionDurations) > 0 ? array_sum($sessionDurations) / count($sessionDurations) : 0,
            'average_events_per_session' => count($events) / max(1, count($sessions))
        ];
    }

    private function getPerformanceMetrics(array $events): array
    {
        $performanceEvents = array_filter($events, function($event) {
            return isset($event['data']['load_time']) || isset($event['data']['response_time']);
        });

        $loadTimes = [];
        $responseTimes = [];

        foreach ($performanceEvents as $event) {
            if (isset($event['data']['load_time'])) {
                $loadTimes[] = $event['data']['load_time'];
            }
            if (isset($event['data']['response_time'])) {
                $responseTimes[] = $event['data']['response_time'];
            }
        }

        return [
            'load_times' => [
                'count' => count($loadTimes),
                'average' => count($loadTimes) > 0 ? array_sum($loadTimes) / count($loadTimes) : 0,
                'min' => count($loadTimes) > 0 ? min($loadTimes) : 0,
                'max' => count($loadTimes) > 0 ? max($loadTimes) : 0
            ],
            'response_times' => [
                'count' => count($responseTimes),
                'average' => count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : 0,
                'min' => count($responseTimes) > 0 ? min($responseTimes) : 0,
                'max' => count($responseTimes) > 0 ? max($responseTimes) : 0
            ]
        ];
    }

    private function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    private function exportToCSV(array $data): string
    {
        $csv = "timestamp,event_name,data\n";
        
        foreach ($data['events'] as $event) {
            $csv .= sprintf(
                "%s,%s,%s\n",
                $event['date'],
                $event['name'],
                json_encode($event['data'])
            );
        }
        
        return $csv;
    }

    private function loadConfiguration(): void
    {
        $config = $this->getConfig();
        
        if (isset($config['max_events'])) {
            $this->maxEvents = (int) $config['max_events'];
        }

        if (isset($config['enabled'])) {
            $this->isEnabled = (bool) $config['enabled'];
        }
    }

    public function getStatistics(): array
    {
        return [
            'enabled' => $this->isEnabled,
            'total_events' => count($this->events),
            'unique_event_types' => count($this->metrics),
            'max_events' => $this->maxEvents,
            'event_types' => array_keys($this->metrics)
        ];
    }
}

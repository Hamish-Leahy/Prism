<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class GestureControlService
{
    private Logger $logger;
    private array $gesturePatterns = [];
    private array $activeGestures = [];
    private array $gestureHistory = [];
    private array $gestureCallbacks = [];
    private bool $isEnabled = false;
    private array $touchPoints = [];
    private array $mousePoints = [];
    private float $gestureThreshold = 10.0; // Minimum distance for gesture recognition
    private int $maxHistorySize = 50;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeGesturePatterns();
    }

    public function enable(): bool
    {
        $this->isEnabled = true;
        $this->logger->info('Gesture control enabled');
        return true;
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->activeGestures = [];
        $this->touchPoints = [];
        $this->mousePoints = [];
        $this->logger->info('Gesture control disabled');
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function processTouchEvent(array $event): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $type = $event['type'] ?? '';
        $touches = $event['touches'] ?? [];

        switch ($type) {
            case 'touchstart':
                $this->handleTouchStart($touches);
                break;
            case 'touchmove':
                $this->handleTouchMove($touches);
                break;
            case 'touchend':
                $this->handleTouchEnd($touches);
                break;
            case 'touchcancel':
                $this->handleTouchCancel($touches);
                break;
        }
    }

    public function processMouseEvent(array $event): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $type = $event['type'] ?? '';
        $button = $event['button'] ?? 0;
        $x = $event['x'] ?? 0;
        $y = $event['y'] ?? 0;

        switch ($type) {
            case 'mousedown':
                $this->handleMouseDown($x, $y, $button);
                break;
            case 'mousemove':
                $this->handleMouseMove($x, $y, $button);
                break;
            case 'mouseup':
                $this->handleMouseUp($x, $y, $button);
                break;
        }
    }

    public function registerGesture(string $name, callable $callback, array $options = []): bool
    {
        $this->gestureCallbacks[$name] = [
            'callback' => $callback,
            'options' => $options
        ];

        $this->logger->info('Gesture registered', ['name' => $name]);
        return true;
    }

    public function unregisterGesture(string $name): bool
    {
        if (isset($this->gestureCallbacks[$name])) {
            unset($this->gestureCallbacks[$name]);
            $this->logger->info('Gesture unregistered', ['name' => $name]);
            return true;
        }
        return false;
    }

    public function setGestureThreshold(float $threshold): void
    {
        $this->gestureThreshold = max(1.0, $threshold);
        $this->logger->info('Gesture threshold updated', ['threshold' => $this->gestureThreshold]);
    }

    public function getActiveGestures(): array
    {
        return $this->activeGestures;
    }

    public function getGestureHistory(): array
    {
        return $this->gestureHistory;
    }

    private function initializeGesturePatterns(): void
    {
        $this->gesturePatterns = [
            'swipe_left' => [
                'min_distance' => 50,
                'max_angle' => 30,
                'max_duration' => 500,
                'min_velocity' => 0.1
            ],
            'swipe_right' => [
                'min_distance' => 50,
                'max_angle' => 30,
                'max_duration' => 500,
                'min_velocity' => 0.1
            ],
            'swipe_up' => [
                'min_distance' => 50,
                'max_angle' => 30,
                'max_duration' => 500,
                'min_velocity' => 0.1
            ],
            'swipe_down' => [
                'min_distance' => 50,
                'max_angle' => 30,
                'max_duration' => 500,
                'min_velocity' => 0.1
            ],
            'pinch' => [
                'min_scale_change' => 0.1,
                'max_duration' => 1000
            ],
            'rotate' => [
                'min_angle_change' => 15,
                'max_duration' => 1000
            ],
            'tap' => [
                'max_duration' => 300,
                'max_movement' => 10
            ],
            'double_tap' => [
                'max_duration' => 300,
                'max_movement' => 10,
                'max_interval' => 500
            ],
            'long_press' => [
                'min_duration' => 500,
                'max_movement' => 10
            ],
            'drag' => [
                'min_distance' => 20,
                'min_duration' => 100
            ]
        ];
    }

    private function handleTouchStart(array $touches): void
    {
        foreach ($touches as $touch) {
            $id = $touch['identifier'] ?? uniqid();
            $this->touchPoints[$id] = [
                'id' => $id,
                'start_x' => $touch['clientX'] ?? 0,
                'start_y' => $touch['clientY'] ?? 0,
                'current_x' => $touch['clientX'] ?? 0,
                'current_y' => $touch['clientY'] ?? 0,
                'start_time' => microtime(true) * 1000,
                'last_time' => microtime(true) * 1000,
                'path' => [[$touch['clientX'] ?? 0, $touch['clientY'] ?? 0]],
                'velocity' => 0,
                'is_active' => true
            ];
        }

        $this->logger->debug('Touch start', ['touch_count' => count($touches)]);
    }

    private function handleTouchMove(array $touches): void
    {
        foreach ($touches as $touch) {
            $id = $touch['identifier'] ?? null;
            if (!$id || !isset($this->touchPoints[$id])) {
                continue;
            }

            $point = &$this->touchPoints[$id];
            $currentTime = microtime(true) * 1000;
            
            $point['current_x'] = $touch['clientX'] ?? 0;
            $point['current_y'] = $touch['clientY'] ?? 0;
            $point['path'][] = [$point['current_x'], $point['current_y']];
            
            // Calculate velocity
            $distance = $this->calculateDistance(
                $point['current_x'], $point['current_y'],
                $point['path'][count($point['path']) - 2][0] ?? $point['current_x'],
                $point['path'][count($point['path']) - 2][1] ?? $point['current_y']
            );
            $timeDelta = $currentTime - $point['last_time'];
            $point['velocity'] = $timeDelta > 0 ? $distance / $timeDelta : 0;
            $point['last_time'] = $currentTime;

            // Keep path size manageable
            if (count($point['path']) > $this->maxHistorySize) {
                $point['path'] = array_slice($point['path'], -$this->maxHistorySize);
            }

            // Check for ongoing gestures
            $this->checkOngoingGestures($id, $point);
        }
    }

    private function handleTouchEnd(array $touches): void
    {
        foreach ($touches as $touch) {
            $id = $touch['identifier'] ?? null;
            if (!$id || !isset($this->touchPoints[$id])) {
                continue;
            }

            $point = $this->touchPoints[$id];
            $point['is_active'] = false;
            $point['end_time'] = microtime(true) * 1000;

            // Recognize completed gestures
            $this->recognizeGestures($id, $point);

            // Clean up
            unset($this->touchPoints[$id]);
        }
    }

    private function handleTouchCancel(array $touches): void
    {
        foreach ($touches as $touch) {
            $id = $touch['identifier'] ?? null;
            if ($id && isset($this->touchPoints[$id])) {
                unset($this->touchPoints[$id]);
            }
        }
    }

    private function handleMouseDown(float $x, float $y, int $button): void
    {
        $id = "mouse_{$button}";
        $this->mousePoints[$id] = [
            'id' => $id,
            'start_x' => $x,
            'start_y' => $y,
            'current_x' => $x,
            'current_y' => $y,
            'start_time' => microtime(true) * 1000,
            'last_time' => microtime(true) * 1000,
            'path' => [[$x, $y]],
            'velocity' => 0,
            'is_active' => true,
            'button' => $button
        ];

        $this->logger->debug('Mouse down', ['x' => $x, 'y' => $y, 'button' => $button]);
    }

    private function handleMouseMove(float $x, float $y, int $button): void
    {
        $id = "mouse_{$button}";
        if (!isset($this->mousePoints[$id])) {
            return;
        }

        $point = &$this->mousePoints[$id];
        $currentTime = microtime(true) * 1000;
        
        $point['current_x'] = $x;
        $point['current_y'] = $y;
        $point['path'][] = [$x, $y];
        
        // Calculate velocity
        $distance = $this->calculateDistance(
            $x, $y,
            $point['path'][count($point['path']) - 2][0] ?? $x,
            $point['path'][count($point['path']) - 2][1] ?? $y
        );
        $timeDelta = $currentTime - $point['last_time'];
        $point['velocity'] = $timeDelta > 0 ? $distance / $timeDelta : 0;
        $point['last_time'] = $currentTime;

        // Keep path size manageable
        if (count($point['path']) > $this->maxHistorySize) {
            $point['path'] = array_slice($point['path'], -$this->maxHistorySize);
        }

        // Check for ongoing gestures
        $this->checkOngoingGestures($id, $point);
    }

    private function handleMouseUp(float $x, float $y, int $button): void
    {
        $id = "mouse_{$button}";
        if (!isset($this->mousePoints[$id])) {
            return;
        }

        $point = $this->mousePoints[$id];
        $point['is_active'] = false;
        $point['end_time'] = microtime(true) * 1000;

        // Recognize completed gestures
        $this->recognizeGestures($id, $point);

        // Clean up
        unset($this->mousePoints[$id]);
    }

    private function checkOngoingGestures(string $id, array $point): void
    {
        if (!$point['is_active']) {
            return;
        }

        // Check for drag gesture
        $distance = $this->calculateDistance(
            $point['current_x'], $point['current_y'],
            $point['start_x'], $point['start_y']
        );

        if ($distance > $this->gesturePatterns['drag']['min_distance']) {
            $duration = (microtime(true) * 1000) - $point['start_time'];
            if ($duration > $this->gesturePatterns['drag']['min_duration']) {
                $this->triggerGesture('drag', [
                    'id' => $id,
                    'start_x' => $point['start_x'],
                    'start_y' => $point['start_y'],
                    'current_x' => $point['current_x'],
                    'current_y' => $point['current_y'],
                    'distance' => $distance,
                    'duration' => $duration,
                    'velocity' => $point['velocity']
                ]);
            }
        }
    }

    private function recognizeGestures(string $id, array $point): void
    {
        $duration = $point['end_time'] - $point['start_time'];
        $distance = $this->calculateDistance(
            $point['current_x'], $point['current_y'],
            $point['start_x'], $point['start_y']
        );

        // Tap gesture
        if ($duration <= $this->gesturePatterns['tap']['max_duration'] && 
            $distance <= $this->gesturePatterns['tap']['max_movement']) {
            $this->triggerGesture('tap', [
                'id' => $id,
                'x' => $point['current_x'],
                'y' => $point['current_y'],
                'duration' => $duration
            ]);
        }

        // Long press gesture
        if ($duration >= $this->gesturePatterns['long_press']['min_duration'] && 
            $distance <= $this->gesturePatterns['long_press']['max_movement']) {
            $this->triggerGesture('long_press', [
                'id' => $id,
                'x' => $point['current_x'],
                'y' => $point['current_y'],
                'duration' => $duration
            ]);
        }

        // Swipe gestures
        if ($distance >= $this->gesturePatterns['swipe_left']['min_distance'] && 
            $duration <= $this->gesturePatterns['swipe_left']['max_duration']) {
            
            $angle = $this->calculateAngle(
                $point['start_x'], $point['start_y'],
                $point['current_x'], $point['current_y']
            );

            if ($angle >= 150 && $angle <= 210) { // Left swipe
                $this->triggerGesture('swipe_left', [
                    'id' => $id,
                    'start_x' => $point['start_x'],
                    'start_y' => $point['start_y'],
                    'end_x' => $point['current_x'],
                    'end_y' => $point['current_y'],
                    'distance' => $distance,
                    'duration' => $duration,
                    'velocity' => $point['velocity']
                ]);
            } elseif ($angle >= -30 && $angle <= 30) { // Right swipe
                $this->triggerGesture('swipe_right', [
                    'id' => $id,
                    'start_x' => $point['start_x'],
                    'start_y' => $point['start_y'],
                    'end_x' => $point['current_x'],
                    'end_y' => $point['current_y'],
                    'distance' => $distance,
                    'duration' => $duration,
                    'velocity' => $point['velocity']
                ]);
            } elseif ($angle >= 60 && $angle <= 120) { // Up swipe
                $this->triggerGesture('swipe_up', [
                    'id' => $id,
                    'start_x' => $point['start_x'],
                    'start_y' => $point['start_y'],
                    'end_x' => $point['current_x'],
                    'end_y' => $point['current_y'],
                    'distance' => $distance,
                    'duration' => $duration,
                    'velocity' => $point['velocity']
                ]);
            } elseif ($angle >= 240 && $angle <= 300) { // Down swipe
                $this->triggerGesture('swipe_down', [
                    'id' => $id,
                    'start_x' => $point['start_x'],
                    'start_y' => $point['start_y'],
                    'end_x' => $point['current_x'],
                    'end_y' => $point['current_y'],
                    'distance' => $distance,
                    'duration' => $duration,
                    'velocity' => $point['velocity']
                ]);
            }
        }

        // Multi-touch gestures
        if (count($this->touchPoints) >= 2) {
            $this->recognizeMultiTouchGestures($id, $point);
        }
    }

    private function recognizeMultiTouchGestures(string $id, array $point): void
    {
        $activeTouches = array_filter($this->touchPoints, function($touch) {
            return $touch['is_active'];
        });

        if (count($activeTouches) < 2) {
            return;
        }

        $touches = array_values($activeTouches);
        $touch1 = $touches[0];
        $touch2 = $touches[1];

        // Calculate pinch/zoom
        $currentDistance = $this->calculateDistance(
            $touch1['current_x'], $touch1['current_y'],
            $touch2['current_x'], $touch2['current_y']
        );
        $startDistance = $this->calculateDistance(
            $touch1['start_x'], $touch1['start_y'],
            $touch2['start_x'], $touch2['start_y']
        );

        if ($startDistance > 0) {
            $scaleChange = $currentDistance / $startDistance;
            
            if (abs($scaleChange - 1.0) >= $this->gesturePatterns['pinch']['min_scale_change']) {
                $this->triggerGesture('pinch', [
                    'id' => $id,
                    'scale' => $scaleChange,
                    'center_x' => ($touch1['current_x'] + $touch2['current_x']) / 2,
                    'center_y' => ($touch1['current_y'] + $touch2['current_y']) / 2,
                    'distance' => $currentDistance
                ]);
            }
        }

        // Calculate rotation
        $currentAngle = $this->calculateAngle(
            $touch1['current_x'], $touch1['current_y'],
            $touch2['current_x'], $touch2['current_y']
        );
        $startAngle = $this->calculateAngle(
            $touch1['start_x'], $touch1['start_y'],
            $touch2['start_x'], $touch2['start_y']
        );

        $angleChange = $currentAngle - $startAngle;
        if (abs($angleChange) >= $this->gesturePatterns['rotate']['min_angle_change']) {
            $this->triggerGesture('rotate', [
                'id' => $id,
                'angle' => $angleChange,
                'center_x' => ($touch1['current_x'] + $touch2['current_x']) / 2,
                'center_y' => ($touch1['current_y'] + $touch2['current_y']) / 2
            ]);
        }
    }

    private function triggerGesture(string $gestureType, array $data): void
    {
        $gestureData = [
            'type' => $gestureType,
            'data' => $data,
            'timestamp' => microtime(true)
        ];

        // Add to gesture history
        $this->gestureHistory[] = $gestureData;
        if (count($this->gestureHistory) > $this->maxHistorySize) {
            $this->gestureHistory = array_slice($this->gestureHistory, -$this->maxHistorySize);
        }

        // Trigger registered callbacks
        if (isset($this->gestureCallbacks[$gestureType])) {
            try {
                $callback = $this->gestureCallbacks[$gestureType]['callback'];
                $callback($gestureData);
            } catch (\Exception $e) {
                $this->logger->error('Error in gesture callback', [
                    'gesture' => $gestureType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Add to active gestures
        $this->activeGestures[$gestureType] = $gestureData;

        $this->logger->debug('Gesture recognized', [
            'type' => $gestureType,
            'data' => $data
        ]);
    }

    private function calculateDistance(float $x1, float $y1, float $x2, float $y2): float
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }

    private function calculateAngle(float $x1, float $y1, float $x2, float $y2): float
    {
        $angle = atan2($y2 - $y1, $x2 - $x1) * 180 / M_PI;
        return $angle < 0 ? $angle + 360 : $angle;
    }

    public function clearGestureHistory(): void
    {
        $this->gestureHistory = [];
        $this->logger->info('Gesture history cleared');
    }

    public function getGestureStatistics(): array
    {
        $stats = [];
        foreach ($this->gestureHistory as $gesture) {
            $type = $gesture['type'];
            if (!isset($stats[$type])) {
                $stats[$type] = 0;
            }
            $stats[$type]++;
        }

        return [
            'total_gestures' => count($this->gestureHistory),
            'gesture_counts' => $stats,
            'active_gestures' => count($this->activeGestures),
            'registered_callbacks' => count($this->gestureCallbacks)
        ];
    }
}
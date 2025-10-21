<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class GestureControlService
{
    private Logger $logger;
    private array $gestures = [];
    private bool $isEnabled = false;
    private array $sensitivity = [
        'swipe' => 50,
        'pinch' => 0.1,
        'rotation' => 15
    ];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeGestures();
    }

    public function enable(): bool
    {
        try {
            $this->isEnabled = true;
            $this->logger->info('Gesture control enabled');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to enable gesture control: ' . $e->getMessage());
            return false;
        }
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->logger->info('Gesture control disabled');
        return true;
    }

    public function processGesture(array $gestureData): array
    {
        if (!$this->isEnabled) {
            return ['success' => false, 'message' => 'Gesture control is disabled'];
        }

        $gestureType = $gestureData['type'] ?? '';
        $coordinates = $gestureData['coordinates'] ?? [];
        $velocity = $gestureData['velocity'] ?? 0;
        $direction = $gestureData['direction'] ?? '';

        $this->logger->info('Processing gesture', [
            'type' => $gestureType,
            'coordinates' => $coordinates,
            'velocity' => $velocity
        ]);

        foreach ($this->gestures as $gesture) {
            if ($this->matchesGesture($gestureType, $direction, $velocity, $gesture)) {
                return $this->executeGesture($gesture, $gestureData);
            }
        }

        return ['success' => false, 'message' => 'Gesture not recognized'];
    }

    public function addCustomGesture(string $name, array $pattern, callable $handler): void
    {
        $this->gestures[] = [
            'name' => $name,
            'pattern' => $pattern,
            'handler' => $handler,
            'type' => 'custom'
        ];
    }

    public function setSensitivity(string $gestureType, float $value): void
    {
        if (isset($this->sensitivity[$gestureType])) {
            $this->sensitivity[$gestureType] = $value;
            $this->logger->info('Gesture sensitivity updated', [
                'type' => $gestureType,
                'value' => $value
            ]);
        }
    }

    public function getAvailableGestures(): array
    {
        return array_map(function($gesture) {
            return [
                'name' => $gesture['name'],
                'description' => $gesture['description'] ?? '',
                'pattern' => $gesture['pattern']
            ];
        }, $this->gestures);
    }

    private function initializeGestures(): void
    {
        $this->gestures = [
            [
                'name' => 'swipe_left',
                'pattern' => ['type' => 'swipe', 'direction' => 'left'],
                'description' => 'Swipe left to go back',
                'handler' => [$this, 'handleSwipeLeft']
            ],
            [
                'name' => 'swipe_right',
                'pattern' => ['type' => 'swipe', 'direction' => 'right'],
                'description' => 'Swipe right to go forward',
                'handler' => [$this, 'handleSwipeRight']
            ],
            [
                'name' => 'swipe_up',
                'pattern' => ['type' => 'swipe', 'direction' => 'up'],
                'description' => 'Swipe up to scroll up',
                'handler' => [$this, 'handleSwipeUp']
            ],
            [
                'name' => 'swipe_down',
                'pattern' => ['type' => 'swipe', 'direction' => 'down'],
                'description' => 'Swipe down to scroll down',
                'handler' => [$this, 'handleSwipeDown']
            ],
            [
                'name' => 'pinch_in',
                'pattern' => ['type' => 'pinch', 'direction' => 'in'],
                'description' => 'Pinch in to zoom out',
                'handler' => [$this, 'handlePinchIn']
            ],
            [
                'name' => 'pinch_out',
                'pattern' => ['type' => 'pinch', 'direction' => 'out'],
                'description' => 'Pinch out to zoom in',
                'handler' => [$this, 'handlePinchOut']
            ],
            [
                'name' => 'two_finger_tap',
                'pattern' => ['type' => 'tap', 'fingers' => 2],
                'description' => 'Two finger tap to open context menu',
                'handler' => [$this, 'handleTwoFingerTap']
            ],
            [
                'name' => 'three_finger_swipe_left',
                'pattern' => ['type' => 'swipe', 'direction' => 'left', 'fingers' => 3],
                'description' => 'Three finger swipe left to switch tabs',
                'handler' => [$this, 'handleThreeFingerSwipeLeft']
            ],
            [
                'name' => 'three_finger_swipe_right',
                'pattern' => ['type' => 'swipe', 'direction' => 'right', 'fingers' => 3],
                'description' => 'Three finger swipe right to switch tabs',
                'handler' => [$this, 'handleThreeFingerSwipeRight']
            ],
            [
                'name' => 'long_press',
                'pattern' => ['type' => 'long_press', 'duration' => 1000],
                'description' => 'Long press to select text or open context menu',
                'handler' => [$this, 'handleLongPress']
            ],
            [
                'name' => 'double_tap',
                'pattern' => ['type' => 'double_tap'],
                'description' => 'Double tap to zoom in/out',
                'handler' => [$this, 'handleDoubleTap']
            ],
            [
                'name' => 'rotation_clockwise',
                'pattern' => ['type' => 'rotation', 'direction' => 'clockwise'],
                'description' => 'Rotate clockwise to refresh page',
                'handler' => [$this, 'handleRotationClockwise']
            ],
            [
                'name' => 'rotation_counterclockwise',
                'pattern' => ['type' => 'rotation', 'direction' => 'counterclockwise'],
                'description' => 'Rotate counterclockwise to go back',
                'handler' => [$this, 'handleRotationCounterclockwise']
            ],
            [
                'name' => 'edge_swipe_left',
                'pattern' => ['type' => 'edge_swipe', 'direction' => 'left'],
                'description' => 'Edge swipe left to open sidebar',
                'handler' => [$this, 'handleEdgeSwipeLeft']
            ],
            [
                'name' => 'edge_swipe_right',
                'pattern' => ['type' => 'edge_swipe', 'direction' => 'right'],
                'description' => 'Edge swipe right to close sidebar',
                'handler' => [$this, 'handleEdgeSwipeRight']
            ]
        ];
    }

    private function matchesGesture(string $type, string $direction, float $velocity, array $gesture): bool
    {
        $pattern = $gesture['pattern'];
        
        if ($pattern['type'] !== $type) {
            return false;
        }

        if (isset($pattern['direction']) && $pattern['direction'] !== $direction) {
            return false;
        }

        if (isset($pattern['fingers']) && $pattern['fingers'] !== ($gesture['fingers'] ?? 1)) {
            return false;
        }

        // Check velocity threshold
        $velocityThreshold = $this->sensitivity[$type] ?? 0;
        if ($velocity < $velocityThreshold) {
            return false;
        }

        return true;
    }

    private function executeGesture(array $gesture, array $gestureData): array
    {
        try {
            $result = call_user_func($gesture['handler'], $gestureData);
            return [
                'success' => true,
                'gesture' => $gesture['name'],
                'result' => $result
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute gesture', [
                'gesture' => $gesture['name'],
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'gesture' => $gesture['name'],
                'error' => 'Failed to execute gesture'
            ];
        }
    }

    // Gesture handlers
    private function handleSwipeLeft(array $data): array
    {
        return ['action' => 'go_back', 'velocity' => $data['velocity']];
    }

    private function handleSwipeRight(array $data): array
    {
        return ['action' => 'go_forward', 'velocity' => $data['velocity']];
    }

    private function handleSwipeUp(array $data): array
    {
        return ['action' => 'scroll_up', 'velocity' => $data['velocity']];
    }

    private function handleSwipeDown(array $data): array
    {
        return ['action' => 'scroll_down', 'velocity' => $data['velocity']];
    }

    private function handlePinchIn(array $data): array
    {
        return ['action' => 'zoom_out', 'scale' => $data['scale'] ?? 0.9];
    }

    private function handlePinchOut(array $data): array
    {
        return ['action' => 'zoom_in', 'scale' => $data['scale'] ?? 1.1];
    }

    private function handleTwoFingerTap(array $data): array
    {
        return ['action' => 'context_menu', 'coordinates' => $data['coordinates']];
    }

    private function handleThreeFingerSwipeLeft(array $data): array
    {
        return ['action' => 'next_tab', 'velocity' => $data['velocity']];
    }

    private function handleThreeFingerSwipeRight(array $data): array
    {
        return ['action' => 'previous_tab', 'velocity' => $data['velocity']];
    }

    private function handleLongPress(array $data): array
    {
        return ['action' => 'text_selection', 'coordinates' => $data['coordinates']];
    }

    private function handleDoubleTap(array $data): array
    {
        return ['action' => 'toggle_zoom', 'coordinates' => $data['coordinates']];
    }

    private function handleRotationClockwise(array $data): array
    {
        return ['action' => 'refresh', 'angle' => $data['angle'] ?? 0];
    }

    private function handleRotationCounterclockwise(array $data): array
    {
        return ['action' => 'go_back', 'angle' => $data['angle'] ?? 0];
    }

    private function handleEdgeSwipeLeft(array $data): array
    {
        return ['action' => 'open_sidebar', 'velocity' => $data['velocity']];
    }

    private function handleEdgeSwipeRight(array $data): array
    {
        return ['action' => 'close_sidebar', 'velocity' => $data['velocity']];
    }
}

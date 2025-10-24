<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\LoopInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PushNotificationService
{
    private array $config;
    private Logger $logger;
    private LoopInterface $loop;
    private Client $httpClient;
    private array $subscriptions = [];
    private array $notifications = [];
    private array $notificationTemplates = [];
    private array $scheduledNotifications = [];
    private bool $initialized = false;
    private array $vapidKeys = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->loadNotificationTemplates();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Push Notification Service");
            
            // Check if Push Notifications are enabled
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Push Notification Service disabled by configuration");
                return true;
            }

            // Validate VAPID keys if provided
            if (empty($this->config['vapid_public_key']) || empty($this->config['vapid_private_key'])) {
                $this->logger->warning("VAPID keys not configured, push notifications may not work properly");
            }

            $this->initialized = true;
            $this->logger->info("Push Notification Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Push Notification Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function subscribe(string $subscriptionId, array $subscriptionData): bool
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Push Notification Service not initialized');
        }

        try {
            $subscription = [
                'id' => $subscriptionId,
                'endpoint' => $subscriptionData['endpoint'] ?? '',
                'keys' => $subscriptionData['keys'] ?? [],
                'user_id' => $subscriptionData['user_id'] ?? null,
                'user_agent' => $subscriptionData['user_agent'] ?? '',
                'created_at' => time(),
                'updated_at' => time(),
                'active' => true
            ];

            $this->subscriptions[$subscriptionId] = $subscription;
            
            $this->logger->info("Push notification subscription created", [
                'subscription_id' => $subscriptionId,
                'endpoint' => $subscription['endpoint']
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create push notification subscription", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function unsubscribe(string $subscriptionId): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        try {
            $this->subscriptions[$subscriptionId]['active'] = false;
            unset($this->subscriptions[$subscriptionId]);
            
            $this->logger->info("Push notification subscription removed", [
                'subscription_id' => $subscriptionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to remove push notification subscription", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendNotification(string $subscriptionId, array $payload, array $options = []): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        try {
            $notificationId = 'notification_' . uniqid();
            
            $notification = [
                'id' => $notificationId,
                'subscription_id' => $subscriptionId,
                'payload' => $payload,
                'options' => $options,
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => $this->config['retry_attempts'] ?? 3,
                'created_at' => time(),
                'sent_at' => null,
                'delivered_at' => null,
                'failed_at' => null
            ];

            $this->notifications[$notificationId] = $notification;
            
            // In a real implementation, this would send the actual push notification
            // For now, we'll simulate the sending process
            $this->simulateNotificationSending($notificationId);
            
            $this->logger->info("Push notification sent", [
                'notification_id' => $notificationId,
                'subscription_id' => $subscriptionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send push notification", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendBulkNotification(array $subscriptionIds, array $payload, array $options = []): array
    {
        $results = [];
        
        foreach ($subscriptionIds as $subscriptionId) {
            $results[$subscriptionId] = $this->sendNotification($subscriptionId, $payload, $options);
        }
        
        return $results;
    }

    public function getSubscription(string $subscriptionId): ?array
    {
        return $this->subscriptions[$subscriptionId] ?? null;
    }

    public function getNotification(string $notificationId): ?array
    {
        return $this->notifications[$notificationId] ?? null;
    }

    public function getSubscriptionsByUser(string $userId): array
    {
        return array_filter($this->subscriptions, function($subscription) use ($userId) {
            return $subscription['user_id'] === $userId && $subscription['active'];
        });
    }

    public function getNotificationsBySubscription(string $subscriptionId): array
    {
        return array_filter($this->notifications, function($notification) use ($subscriptionId) {
            return $notification['subscription_id'] === $subscriptionId;
        });
    }

    public function updateSubscription(string $subscriptionId, array $updates): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        try {
            $this->subscriptions[$subscriptionId] = array_merge(
                $this->subscriptions[$subscriptionId],
                $updates,
                ['updated_at' => time()]
            );
            
            $this->logger->info("Push notification subscription updated", [
                'subscription_id' => $subscriptionId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to update push notification subscription", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function retryFailedNotification(string $notificationId): bool
    {
        if (!isset($this->notifications[$notificationId])) {
            return false;
        }

        $notification = &$this->notifications[$notificationId];
        
        if ($notification['status'] !== 'failed') {
            return false;
        }

        try {
            $notification['status'] = 'pending';
            $notification['retry_count']++;
            $notification['updated_at'] = time();
            
            $this->simulateNotificationSending($notificationId);
            
            $this->logger->info("Failed push notification retried", [
                'notification_id' => $notificationId,
                'retry_count' => $notification['retry_count']
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to retry push notification", [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getStats(): array
    {
        $activeSubscriptions = array_filter($this->subscriptions, function($subscription) {
            return $subscription['active'];
        });

        $pendingNotifications = array_filter($this->notifications, function($notification) {
            return $notification['status'] === 'pending';
        });

        $deliveredNotifications = array_filter($this->notifications, function($notification) {
            return $notification['status'] === 'delivered';
        });

        $failedNotifications = array_filter($this->notifications, function($notification) {
            return $notification['status'] === 'failed';
        });

        return [
            'subscriptions_count' => count($this->subscriptions),
            'active_subscriptions_count' => count($activeSubscriptions),
            'notifications_count' => count($this->notifications),
            'pending_notifications_count' => count($pendingNotifications),
            'delivered_notifications_count' => count($deliveredNotifications),
            'failed_notifications_count' => count($failedNotifications),
            'max_subscriptions' => $this->config['max_subscriptions'] ?? 1000,
            'max_notifications' => $this->config['max_notifications'] ?? 10000,
            'retry_attempts' => $this->config['retry_attempts'] ?? 3,
            'retry_delay' => $this->config['retry_delay'] ?? 60
        ];
    }

    private function simulateNotificationSending(string $notificationId): void
    {
        $notification = &$this->notifications[$notificationId];
        
        // Simulate sending delay
        usleep(100000); // 100ms
        
        // Simulate success/failure based on retry count
        if ($notification['retry_count'] > 2) {
            $notification['status'] = 'delivered';
            $notification['delivered_at'] = time();
        } else {
            // Simulate occasional failures
            if (rand(1, 10) <= 2) { // 20% failure rate
                $notification['status'] = 'failed';
                $notification['failed_at'] = time();
            } else {
                $notification['status'] = 'delivered';
                $notification['delivered_at'] = time();
            }
        }
        
        $notification['sent_at'] = time();
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->subscriptions = [];
        $this->notifications = [];
        $this->scheduledNotifications = [];
        $this->initialized = false;
        $this->logger->info("Push Notification Service cleaned up");
    }

    // Enhanced notification features
    public function scheduleNotification(string $subscriptionId, array $payload, int $timestamp, array $options = []): string
    {
        $notificationId = 'scheduled_' . uniqid();
        
        $scheduledNotification = [
            'id' => $notificationId,
            'subscription_id' => $subscriptionId,
            'payload' => $payload,
            'options' => $options,
            'scheduled_at' => $timestamp,
            'created_at' => time(),
            'status' => 'scheduled'
        ];

        $this->scheduledNotifications[$notificationId] = $scheduledNotification;

        // Schedule the notification if we have an event loop
        if ($this->loop) {
            $delay = max(0, $timestamp - time());
            $this->loop->addTimer($delay, function() use ($notificationId) {
                $this->processScheduledNotification($notificationId);
            });
        }

        $this->logger->info("Notification scheduled", [
            'notification_id' => $notificationId,
            'scheduled_at' => date('Y-m-d H:i:s', $timestamp)
        ]);

        return $notificationId;
    }

    public function cancelScheduledNotification(string $notificationId): bool
    {
        if (!isset($this->scheduledNotifications[$notificationId])) {
            return false;
        }

        unset($this->scheduledNotifications[$notificationId]);
        $this->logger->info("Scheduled notification cancelled", ['notification_id' => $notificationId]);
        return true;
    }

    public function createNotificationTemplate(string $name, array $template): bool
    {
        $this->notificationTemplates[$name] = [
            'title' => $template['title'] ?? '',
            'body' => $template['body'] ?? '',
            'icon' => $template['icon'] ?? '',
            'badge' => $template['badge'] ?? '',
            'image' => $template['image'] ?? '',
            'actions' => $template['actions'] ?? [],
            'data' => $template['data'] ?? [],
            'requireInteraction' => $template['requireInteraction'] ?? false,
            'silent' => $template['silent'] ?? false,
            'tag' => $template['tag'] ?? '',
            'timestamp' => time()
        ];

        $this->logger->info("Notification template created", ['name' => $name]);
        return true;
    }

    public function sendTemplateNotification(string $subscriptionId, string $templateName, array $variables = [], array $options = []): bool
    {
        if (!isset($this->notificationTemplates[$templateName])) {
            $this->logger->error("Notification template not found", ['template' => $templateName]);
            return false;
        }

        $template = $this->notificationTemplates[$templateName];
        $payload = $this->processTemplate($template, $variables);

        return $this->sendNotification($subscriptionId, $payload, $options);
    }

    public function sendBulkTemplateNotification(array $subscriptionIds, string $templateName, array $variables = [], array $options = []): array
    {
        $results = [];
        
        foreach ($subscriptionIds as $subscriptionId) {
            $results[$subscriptionId] = $this->sendTemplateNotification($subscriptionId, $templateName, $variables, $options);
        }
        
        return $results;
    }

    public function sendToAllSubscribers(array $payload, array $options = []): array
    {
        $activeSubscriptions = array_filter($this->subscriptions, function($subscription) {
            return $subscription['active'];
        });

        $results = [];
        foreach ($activeSubscriptions as $subscriptionId => $subscription) {
            $results[$subscriptionId] = $this->sendNotification($subscriptionId, $payload, $options);
        }

        return $results;
    }

    public function sendToUserGroup(string $groupId, array $payload, array $options = []): array
    {
        $groupSubscriptions = array_filter($this->subscriptions, function($subscription) use ($groupId) {
            return $subscription['active'] && ($subscription['group_id'] ?? '') === $groupId;
        });

        $results = [];
        foreach ($groupSubscriptions as $subscriptionId => $subscription) {
            $results[$subscriptionId] = $this->sendNotification($subscriptionId, $payload, $options);
        }

        return $results;
    }

    public function sendGeoTargetedNotification(array $coordinates, int $radius, array $payload, array $options = []): array
    {
        $targetedSubscriptions = array_filter($this->subscriptions, function($subscription) use ($coordinates, $radius) {
            if (!$subscription['active'] || !isset($subscription['location'])) {
                return false;
            }

            $distance = $this->calculateDistance(
                $coordinates['lat'], $coordinates['lng'],
                $subscription['location']['lat'], $subscription['location']['lng']
            );

            return $distance <= $radius;
        });

        $results = [];
        foreach ($targetedSubscriptions as $subscriptionId => $subscription) {
            $results[$subscriptionId] = $this->sendNotification($subscriptionId, $payload, $options);
        }

        return $results;
    }

    public function setSubscriptionLocation(string $subscriptionId, array $location): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        $this->subscriptions[$subscriptionId]['location'] = $location;
        $this->subscriptions[$subscriptionId]['updated_at'] = time();

        $this->logger->info("Subscription location updated", [
            'subscription_id' => $subscriptionId,
            'location' => $location
        ]);

        return true;
    }

    public function setSubscriptionGroup(string $subscriptionId, string $groupId): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        $this->subscriptions[$subscriptionId]['group_id'] = $groupId;
        $this->subscriptions[$subscriptionId]['updated_at'] = time();

        $this->logger->info("Subscription group updated", [
            'subscription_id' => $subscriptionId,
            'group_id' => $groupId
        ]);

        return true;
    }

    public function getNotificationTemplates(): array
    {
        return $this->notificationTemplates;
    }

    public function getScheduledNotifications(): array
    {
        return $this->scheduledNotifications;
    }

    public function getNotificationAnalytics(string $period = '1d'): array
    {
        $startTime = $this->getPeriodStartTime($period);
        $filteredNotifications = array_filter($this->notifications, function($notification) use ($startTime) {
            return $notification['created_at'] >= $startTime;
        });

        $statusCounts = [];
        $hourlyDistribution = [];
        $templateUsage = [];

        foreach ($filteredNotifications as $notification) {
            $status = $notification['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            $hour = date('H', $notification['created_at']);
            $hourlyDistribution[$hour] = ($hourlyDistribution[$hour] ?? 0) + 1;

            if (isset($notification['template'])) {
                $template = $notification['template'];
                $templateUsage[$template] = ($templateUsage[$template] ?? 0) + 1;
            }
        }

        return [
            'period' => $period,
            'total_notifications' => count($filteredNotifications),
            'status_distribution' => $statusCounts,
            'hourly_distribution' => $hourlyDistribution,
            'template_usage' => $templateUsage,
            'delivery_rate' => $this->calculateDeliveryRate($filteredNotifications),
            'average_delivery_time' => $this->calculateAverageDeliveryTime($filteredNotifications)
        ];
    }

    private function processScheduledNotification(string $notificationId): void
    {
        if (!isset($this->scheduledNotifications[$notificationId])) {
            return;
        }

        $scheduledNotification = $this->scheduledNotifications[$notificationId];
        unset($this->scheduledNotifications[$notificationId]);

        $this->sendNotification(
            $scheduledNotification['subscription_id'],
            $scheduledNotification['payload'],
            $scheduledNotification['options']
        );

        $this->logger->info("Scheduled notification processed", ['notification_id' => $notificationId]);
    }

    private function processTemplate(array $template, array $variables): array
    {
        $payload = $template;

        // Replace variables in title and body
        if (isset($payload['title'])) {
            $payload['title'] = $this->replaceVariables($payload['title'], $variables);
        }

        if (isset($payload['body'])) {
            $payload['body'] = $this->replaceVariables($payload['body'], $variables);
        }

        // Merge template data with variables
        if (isset($payload['data'])) {
            $payload['data'] = array_merge($payload['data'], $variables);
        }

        return $payload;
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }

        return $text;
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function getPeriodStartTime(string $period): int
    {
        $now = time();
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

    private function calculateDeliveryRate(array $notifications): float
    {
        if (empty($notifications)) {
            return 0.0;
        }

        $delivered = count(array_filter($notifications, function($n) {
            return $n['status'] === 'delivered';
        }));

        return ($delivered / count($notifications)) * 100;
    }

    private function calculateAverageDeliveryTime(array $notifications): float
    {
        $deliveredNotifications = array_filter($notifications, function($n) {
            return $n['status'] === 'delivered' && $n['sent_at'] && $n['delivered_at'];
        });

        if (empty($deliveredNotifications)) {
            return 0.0;
        }

        $totalTime = 0;
        foreach ($deliveredNotifications as $notification) {
            $totalTime += $notification['delivered_at'] - $notification['sent_at'];
        }

        return $totalTime / count($deliveredNotifications);
    }

    private function loadNotificationTemplates(): void
    {
        $this->notificationTemplates = [
            'download_complete' => [
                'title' => 'Download Complete',
                'body' => '{{filename}} has finished downloading',
                'icon' => '/icons/download.png',
                'data' => ['type' => 'download_complete']
            ],
            'tab_reminder' => [
                'title' => 'Tab Reminder',
                'body' => 'You have {{count}} tabs open',
                'icon' => '/icons/tab.png',
                'data' => ['type' => 'tab_reminder']
            ],
            'security_alert' => [
                'title' => 'Security Alert',
                'body' => '{{message}}',
                'icon' => '/icons/security.png',
                'requireInteraction' => true,
                'data' => ['type' => 'security_alert']
            ],
            'update_available' => [
                'title' => 'Update Available',
                'body' => 'Prism {{version}} is now available',
                'icon' => '/icons/update.png',
                'actions' => [
                    ['action' => 'update', 'title' => 'Update Now'],
                    ['action' => 'later', 'title' => 'Later']
                ],
                'data' => ['type' => 'update_available']
            ]
        ];
    }
}
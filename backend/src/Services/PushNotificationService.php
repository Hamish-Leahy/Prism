<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class PushNotificationService
{
    private array $config;
    private Logger $logger;
    private array $subscriptions = [];
    private array $notifications = [];
    private array $endpoints = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Push Notification service");
            
            // Initialize push notification endpoints
            $this->endpoints = $this->config['endpoints'] ?? [
                'fcm' => 'https://fcm.googleapis.com/fcm/send',
                'apns' => 'https://api.push.apple.com/3/device/',
                'web_push' => 'https://web.push.apple.com/3/device/'
            ];

            $this->initialized = true;
            $this->logger->info("Push Notification service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Push Notification service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function subscribe(string $subscriptionId, array $subscriptionData): bool
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Push Notification service not initialized');
        }

        try {
            $subscription = [
                'id' => $subscriptionId,
                'endpoint' => $subscriptionData['endpoint'] ?? '',
                'keys' => $subscriptionData['keys'] ?? [],
                'user_agent' => $subscriptionData['user_agent'] ?? '',
                'expiration_time' => $subscriptionData['expiration_time'] ?? null,
                'user_visible_only' => $subscriptionData['user_visible_only'] ?? true,
                'application_server_key' => $subscriptionData['application_server_key'] ?? '',
                'created_at' => time(),
                'last_used' => time(),
                'active' => true
            ];

            $this->subscriptions[$subscriptionId] = $subscription;

            $this->logger->info("Created push subscription", [
                'subscription_id' => $subscriptionId,
                'endpoint' => $subscription['endpoint']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create push subscription: " . $e->getMessage());
            return false;
        }
    }

    public function unsubscribe(string $subscriptionId): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }

        unset($this->subscriptions[$subscriptionId]);

        $this->logger->info("Removed push subscription", ['subscription_id' => $subscriptionId]);
        return true;
    }

    public function sendNotification(string $subscriptionId, array $payload, array $options = []): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            throw new \RuntimeException('Subscription not found');
        }

        $subscription = $this->subscriptions[$subscriptionId];
        
        if (!$subscription['active']) {
            throw new \RuntimeException('Subscription is not active');
        }

        try {
            $notificationId = 'notif_' . uniqid();
            
            $notification = [
                'id' => $notificationId,
                'subscription_id' => $subscriptionId,
                'payload' => $payload,
                'options' => $options,
                'status' => 'pending',
                'sent_at' => null,
                'delivered_at' => null,
                'failed_at' => null,
                'retry_count' => 0,
                'max_retries' => $options['max_retries'] ?? 3,
                'created_at' => time()
            ];

            $this->notifications[$notificationId] = $notification;

            // Send the notification
            $success = $this->deliverNotification($subscription, $notification);

            if ($success) {
                $notification['status'] = 'sent';
                $notification['sent_at'] = time();
                $this->logger->info("Push notification sent", [
                    'notification_id' => $notificationId,
                    'subscription_id' => $subscriptionId
                ]);
            } else {
                $notification['status'] = 'failed';
                $notification['failed_at'] = time();
                $this->logger->error("Failed to send push notification", [
                    'notification_id' => $notificationId,
                    'subscription_id' => $subscriptionId
                ]);
            }

            $this->notifications[$notificationId] = $notification;
            $this->subscriptions[$subscriptionId]['last_used'] = time();

            return $success;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send push notification: " . $e->getMessage());
            return false;
        }
    }

    public function sendBulkNotification(array $subscriptionIds, array $payload, array $options = []): array
    {
        $results = [];
        
        foreach ($subscriptionIds as $subscriptionId) {
            try {
                $success = $this->sendNotification($subscriptionId, $payload, $options);
                $results[$subscriptionId] = $success;
            } catch (\Exception $e) {
                $results[$subscriptionId] = false;
                $this->logger->error("Failed to send bulk notification", [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage()
                ]);
            }
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
            return ($subscription['user_id'] ?? '') === $userId;
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

        $this->subscriptions[$subscriptionId] = array_merge($this->subscriptions[$subscriptionId], $updates);
        $this->subscriptions[$subscriptionId]['last_used'] = time();

        return true;
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

        if ($notification['retry_count'] >= $notification['max_retries']) {
            return false;
        }

        $subscription = $this->subscriptions[$notification['subscription_id']] ?? null;
        if (!$subscription) {
            return false;
        }

        $notification['retry_count']++;
        $notification['status'] = 'pending';

        $success = $this->deliverNotification($subscription, $notification);
        
        if ($success) {
            $notification['status'] = 'sent';
            $notification['sent_at'] = time();
        } else {
            $notification['status'] = 'failed';
            $notification['failed_at'] = time();
        }

        return $success;
    }

    public function getStats(): array
    {
        $totalNotifications = count($this->notifications);
        $sentNotifications = count(array_filter($this->notifications, fn($n) => $n['status'] === 'sent'));
        $failedNotifications = count(array_filter($this->notifications, fn($n) => $n['status'] === 'failed'));
        $pendingNotifications = count(array_filter($this->notifications, fn($n) => $n['status'] === 'pending'));

        return [
            'subscriptions_count' => count($this->subscriptions),
            'active_subscriptions' => count(array_filter($this->subscriptions, fn($s) => $s['active'])),
            'total_notifications' => $totalNotifications,
            'sent_notifications' => $sentNotifications,
            'failed_notifications' => $failedNotifications,
            'pending_notifications' => $pendingNotifications,
            'success_rate' => $totalNotifications > 0 ? ($sentNotifications / $totalNotifications) * 100 : 0,
            'initialized' => $this->initialized
        ];
    }

    private function deliverNotification(array $subscription, array $notification): bool
    {
        try {
            $endpoint = $subscription['endpoint'];
            $payload = $notification['payload'];
            $options = $notification['options'];

            // Determine the push service type based on endpoint
            $serviceType = $this->detectPushService($endpoint);
            
            switch ($serviceType) {
                case 'fcm':
                    return $this->sendFCMNotification($subscription, $payload, $options);
                case 'apns':
                    return $this->sendAPNSNotification($subscription, $payload, $options);
                case 'web_push':
                    return $this->sendWebPushNotification($subscription, $payload, $options);
                default:
                    return $this->sendGenericNotification($subscription, $payload, $options);
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to deliver notification: " . $e->getMessage());
            return false;
        }
    }

    private function detectPushService(string $endpoint): string
    {
        if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
            return 'fcm';
        } elseif (strpos($endpoint, 'push.apple.com') !== false) {
            return 'apns';
        } elseif (strpos($endpoint, 'web.push.apple.com') !== false) {
            return 'web_push';
        } else {
            return 'generic';
        }
    }

    private function sendFCMNotification(array $subscription, array $payload, array $options): bool
    {
        // Mock FCM implementation
        $this->logger->info("Sending FCM notification", [
            'endpoint' => $subscription['endpoint'],
            'payload' => $payload
        ]);
        
        // In a real implementation, this would make an HTTP request to FCM
        return true;
    }

    private function sendAPNSNotification(array $subscription, array $payload, array $options): bool
    {
        // Mock APNS implementation
        $this->logger->info("Sending APNS notification", [
            'endpoint' => $subscription['endpoint'],
            'payload' => $payload
        ]);
        
        // In a real implementation, this would make an HTTP request to APNS
        return true;
    }

    private function sendWebPushNotification(array $subscription, array $payload, array $options): bool
    {
        // Mock Web Push implementation
        $this->logger->info("Sending Web Push notification", [
            'endpoint' => $subscription['endpoint'],
            'payload' => $payload
        ]);
        
        // In a real implementation, this would make an HTTP request using Web Push protocol
        return true;
    }

    private function sendGenericNotification(array $subscription, array $payload, array $options): bool
    {
        // Mock generic implementation
        $this->logger->info("Sending generic push notification", [
            'endpoint' => $subscription['endpoint'],
            'payload' => $payload
        ]);
        
        // In a real implementation, this would make an HTTP request to the endpoint
        return true;
    }

    public function cleanup(): void
    {
        $this->subscriptions = [];
        $this->notifications = [];
        $this->endpoints = [];
        $this->initialized = false;
        $this->logger->info("Push Notification service cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}

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
        $this->initialized = false;
        $this->logger->info("Push Notification Service cleaned up");
    }
}
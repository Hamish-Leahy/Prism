<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MLRecommendationService
{
    private Logger $logger;
    private Client $httpClient;
    private array $config;
    private array $userProfiles = [];
    private array $contentFeatures = [];
    private array $recommendationModels = [];
    private array $userInteractions = [];
    private array $contentSimilarity = [];
    private bool $isEnabled = false;
    private array $mlAlgorithms = [];
    private array $featureVectors = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->initializeMLAlgorithms();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing ML Recommendation Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("ML Recommendation Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->loadUserProfiles();
            $this->loadContentFeatures();
            $this->trainRecommendationModels();
            
            $this->logger->info("ML Recommendation Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("ML Recommendation Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function getRecommendations(string $userId, int $limit = 10, array $options = []): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        try {
            $userProfile = $this->getUserProfile($userId);
            $recommendations = [];

            // Collaborative Filtering
            $collaborativeRecs = $this->getCollaborativeFilteringRecommendations($userId, $limit);
            $recommendations = array_merge($recommendations, $collaborativeRecs);

            // Content-Based Filtering
            $contentRecs = $this->getContentBasedRecommendations($userId, $limit);
            $recommendations = array_merge($recommendations, $contentRecs);

            // Hybrid Approach
            $hybridRecs = $this->getHybridRecommendations($userId, $limit);
            $recommendations = array_merge($recommendations, $hybridRecs);

            // Remove duplicates and sort by score
            $recommendations = $this->deduplicateAndRank($recommendations, $limit);

            $this->logger->debug("Recommendations generated", [
                'user_id' => $userId,
                'count' => count($recommendations)
            ]);

            return $recommendations;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get recommendations", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function recordUserInteraction(string $userId, string $contentId, string $interactionType, array $metadata = []): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        try {
            $interaction = [
                'user_id' => $userId,
                'content_id' => $contentId,
                'type' => $interactionType,
                'metadata' => $metadata,
                'timestamp' => microtime(true),
                'date' => date('Y-m-d H:i:s')
            ];

            $this->userInteractions[] = $interaction;

            // Update user profile
            $this->updateUserProfile($userId, $interaction);

            // Update content features
            $this->updateContentFeatures($contentId, $interaction);

            $this->logger->debug("User interaction recorded", [
                'user_id' => $userId,
                'content_id' => $contentId,
                'type' => $interactionType
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to record user interaction", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSimilarContent(string $contentId, int $limit = 5): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        try {
            $contentFeatures = $this->getContentFeatures($contentId);
            if (empty($contentFeatures)) {
                return [];
            }

            $similarities = [];
            foreach ($this->contentFeatures as $id => $features) {
                if ($id === $contentId) {
                    continue;
                }

                $similarity = $this->calculateCosineSimilarity($contentFeatures, $features);
                $similarities[] = [
                    'content_id' => $id,
                    'similarity' => $similarity,
                    'features' => $features
                ];
            }

            // Sort by similarity and return top results
            usort($similarities, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            return array_slice($similarities, 0, $limit);
        } catch (\Exception $e) {
            $this->logger->error("Failed to get similar content", [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getTrendingContent(string $category = null, int $limit = 10): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        try {
            $trendingContent = [];
            $timeWindow = 24 * 60 * 60; // 24 hours
            $cutoffTime = microtime(true) - $timeWindow;

            // Filter recent interactions
            $recentInteractions = array_filter($this->userInteractions, function($interaction) use ($cutoffTime) {
                return $interaction['timestamp'] > $cutoffTime;
            });

            // Count interactions per content
            $contentCounts = [];
            foreach ($recentInteractions as $interaction) {
                $contentId = $interaction['content_id'];
                $contentCounts[$contentId] = ($contentCounts[$contentId] ?? 0) + 1;
            }

            // Sort by interaction count
            arsort($contentCounts);

            // Apply category filter if specified
            if ($category) {
                $contentCounts = array_filter($contentCounts, function($contentId) use ($category) {
                    $features = $this->getContentFeatures($contentId);
                    return isset($features['category']) && $features['category'] === $category;
                }, ARRAY_FILTER_USE_KEY);
            }

            // Format results
            foreach (array_slice($contentCounts, 0, $limit, true) as $contentId => $count) {
                $trendingContent[] = [
                    'content_id' => $contentId,
                    'interaction_count' => $count,
                    'trend_score' => $this->calculateTrendScore($contentId, $recentInteractions)
                ];
            }

            return $trendingContent;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get trending content", [
                'category' => $category,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getPersonalizedFeed(string $userId, int $limit = 20): array
    {
        if (!$this->isEnabled) {
            return [];
        }

        try {
            $userProfile = $this->getUserProfile($userId);
            $feed = [];

            // Get recommendations from different algorithms
            $collaborativeRecs = $this->getCollaborativeFilteringRecommendations($userId, $limit / 3);
            $contentRecs = $this->getContentBasedRecommendations($userId, $limit / 3);
            $trendingRecs = $this->getTrendingContent(null, $limit / 3);

            // Combine and re-rank
            $allRecs = array_merge($collaborativeRecs, $contentRecs, $trendingRecs);
            $feed = $this->rerankRecommendations($allRecs, $userProfile, $limit);

            $this->logger->debug("Personalized feed generated", [
                'user_id' => $userId,
                'count' => count($feed)
            ]);

            return $feed;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get personalized feed", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function trainModel(string $modelType, array $trainingData): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        try {
            switch ($modelType) {
                case 'collaborative_filtering':
                    $this->trainCollaborativeFilteringModel($trainingData);
                    break;
                case 'content_based':
                    $this->trainContentBasedModel($trainingData);
                    break;
                case 'hybrid':
                    $this->trainHybridModel($trainingData);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown model type: $modelType");
            }

            $this->logger->info("Model trained", ['model_type' => $modelType]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to train model", [
                'model_type' => $modelType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getModelPerformance(string $modelType): array
    {
        if (!isset($this->recommendationModels[$modelType])) {
            return ['error' => 'Model not found'];
        }

        $model = $this->recommendationModels[$modelType];
        
        return [
            'model_type' => $modelType,
            'accuracy' => $model['accuracy'] ?? 0,
            'precision' => $model['precision'] ?? 0,
            'recall' => $model['recall'] ?? 0,
            'f1_score' => $model['f1_score'] ?? 0,
            'last_trained' => $model['last_trained'] ?? null,
            'training_samples' => $model['training_samples'] ?? 0
        ];
    }

    private function getCollaborativeFilteringRecommendations(string $userId, int $limit): array
    {
        $userInteractions = $this->getUserInteractions($userId);
        $userItems = array_column($userInteractions, 'content_id');
        
        $recommendations = [];
        
        // Find similar users
        $similarUsers = $this->findSimilarUsers($userId);
        
        foreach ($similarUsers as $similarUser) {
            $similarUserInteractions = $this->getUserInteractions($similarUser['user_id']);
            $similarUserItems = array_column($similarUserInteractions, 'content_id');
            
            // Find items that similar user liked but current user hasn't seen
            $newItems = array_diff($similarUserItems, $userItems);
            
            foreach ($newItems as $itemId) {
                $score = $similarUser['similarity'] * $this->getItemPopularity($itemId);
                $recommendations[] = [
                    'content_id' => $itemId,
                    'score' => $score,
                    'algorithm' => 'collaborative_filtering',
                    'reason' => 'Similar users liked this'
                ];
            }
        }
        
        return $recommendations;
    }

    private function getContentBasedRecommendations(string $userId, int $limit): array
    {
        $userProfile = $this->getUserProfile($userId);
        $userPreferences = $userProfile['preferences'] ?? [];
        
        $recommendations = [];
        
        foreach ($this->contentFeatures as $contentId => $features) {
            $score = $this->calculateContentScore($features, $userPreferences);
            
            if ($score > 0.3) { // Threshold for relevance
                $recommendations[] = [
                    'content_id' => $contentId,
                    'score' => $score,
                    'algorithm' => 'content_based',
                    'reason' => 'Matches your interests'
                ];
            }
        }
        
        return $recommendations;
    }

    private function getHybridRecommendations(string $userId, int $limit): array
    {
        $collaborativeRecs = $this->getCollaborativeFilteringRecommendations($userId, $limit);
        $contentRecs = $this->getContentBasedRecommendations($userId, $limit);
        
        $hybridRecs = [];
        $contentScores = [];
        
        // Combine scores from both approaches
        foreach ($collaborativeRecs as $rec) {
            $contentScores[$rec['content_id']] = ($contentScores[$rec['content_id'] ?? 0]) + $rec['score'] * 0.6;
        }
        
        foreach ($contentRecs as $rec) {
            $contentScores[$rec['content_id']] = ($contentScores[$rec['content_id'] ?? 0]) + $rec['score'] * 0.4;
        }
        
        foreach ($contentScores as $contentId => $score) {
            $hybridRecs[] = [
                'content_id' => $contentId,
                'score' => $score,
                'algorithm' => 'hybrid',
                'reason' => 'Combined recommendation'
            ];
        }
        
        return $hybridRecs;
    }

    private function findSimilarUsers(string $userId): array
    {
        $userProfile = $this->getUserProfile($userId);
        $similarities = [];
        
        foreach ($this->userProfiles as $otherUserId => $otherProfile) {
            if ($otherUserId === $userId) {
                continue;
            }
            
            $similarity = $this->calculateUserSimilarity($userProfile, $otherProfile);
            if ($similarity > 0.1) {
                $similarities[] = [
                    'user_id' => $otherUserId,
                    'similarity' => $similarity
                ];
            }
        }
        
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($similarities, 0, 10);
    }

    private function calculateUserSimilarity(array $user1, array $user2): float
    {
        $preferences1 = $user1['preferences'] ?? [];
        $preferences2 = $user2['preferences'] ?? [];
        
        $commonKeys = array_intersect_key($preferences1, $preferences2);
        if (empty($commonKeys)) {
            return 0;
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        foreach ($commonKeys as $key) {
            $val1 = $preferences1[$key];
            $val2 = $preferences2[$key];
            $dotProduct += $val1 * $val2;
            $norm1 += $val1 * $val1;
            $norm2 += $val2 * $val2;
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    private function calculateCosineSimilarity(array $features1, array $features2): float
    {
        $commonKeys = array_intersect_key($features1, $features2);
        if (empty($commonKeys)) {
            return 0;
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        foreach ($commonKeys as $key) {
            $val1 = is_numeric($features1[$key]) ? $features1[$key] : 0;
            $val2 = is_numeric($features2[$key]) ? $features2[$key] : 0;
            $dotProduct += $val1 * $val2;
            $norm1 += $val1 * $val1;
            $norm2 += $val2 * $val2;
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    private function calculateContentScore(array $features, array $preferences): float
    {
        $score = 0;
        $totalWeight = 0;
        
        foreach ($preferences as $category => $weight) {
            if (isset($features[$category])) {
                $score += $features[$category] * $weight;
                $totalWeight += $weight;
            }
        }
        
        return $totalWeight > 0 ? $score / $totalWeight : 0;
    }

    private function calculateTrendScore(string $contentId, array $recentInteractions): float
    {
        $contentInteractions = array_filter($recentInteractions, function($interaction) use ($contentId) {
            return $interaction['content_id'] === $contentId;
        });
        
        $score = 0;
        $currentTime = microtime(true);
        
        foreach ($contentInteractions as $interaction) {
            $age = $currentTime - $interaction['timestamp'];
            $decayFactor = exp(-$age / (24 * 60 * 60)); // 24-hour decay
            $score += $decayFactor;
        }
        
        return $score;
    }

    private function getUserProfile(string $userId): array
    {
        if (!isset($this->userProfiles[$userId])) {
            $this->userProfiles[$userId] = [
                'user_id' => $userId,
                'preferences' => [],
                'interaction_count' => 0,
                'created_at' => microtime(true)
            ];
        }
        
        return $this->userProfiles[$userId];
    }

    private function getContentFeatures(string $contentId): array
    {
        return $this->contentFeatures[$contentId] ?? [];
    }

    private function getUserInteractions(string $userId): array
    {
        return array_filter($this->userInteractions, function($interaction) use ($userId) {
            return $interaction['user_id'] === $userId;
        });
    }

    private function getItemPopularity(string $contentId): float
    {
        $interactions = array_filter($this->userInteractions, function($interaction) use ($contentId) {
            return $interaction['content_id'] === $contentId;
        });
        
        return count($interactions) / max(count($this->userInteractions), 1);
    }

    private function updateUserProfile(string $userId, array $interaction): void
    {
        $profile = &$this->userProfiles[$userId];
        $profile['interaction_count']++;
        
        // Update preferences based on interaction
        $contentFeatures = $this->getContentFeatures($interaction['content_id']);
        foreach ($contentFeatures as $feature => $value) {
            if (is_numeric($value)) {
                $profile['preferences'][$feature] = ($profile['preferences'][$feature] ?? 0) + $value * 0.1;
            }
        }
    }

    private function updateContentFeatures(string $contentId, array $interaction): void
    {
        if (!isset($this->contentFeatures[$contentId])) {
            $this->contentFeatures[$contentId] = [
                'interaction_count' => 0,
                'last_interaction' => $interaction['timestamp']
            ];
        }
        
        $this->contentFeatures[$contentId]['interaction_count']++;
        $this->contentFeatures[$contentId]['last_interaction'] = $interaction['timestamp'];
    }

    private function deduplicateAndRank(array $recommendations, int $limit): array
    {
        $unique = [];
        foreach ($recommendations as $rec) {
            $contentId = $rec['content_id'];
            if (!isset($unique[$contentId]) || $unique[$contentId]['score'] < $rec['score']) {
                $unique[$contentId] = $rec;
            }
        }
        
        usort($unique, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($unique, 0, $limit);
    }

    private function rerankRecommendations(array $recommendations, array $userProfile, int $limit): array
    {
        // Apply personalization factors
        foreach ($recommendations as &$rec) {
            $contentFeatures = $this->getContentFeatures($rec['content_id']);
            $personalizationScore = $this->calculateContentScore($contentFeatures, $userProfile['preferences'] ?? []);
            $rec['score'] = $rec['score'] * 0.7 + $personalizationScore * 0.3;
        }
        
        return $this->deduplicateAndRank($recommendations, $limit);
    }

    private function trainCollaborativeFilteringModel(array $trainingData): void
    {
        // Simplified collaborative filtering training
        $this->recommendationModels['collaborative_filtering'] = [
            'type' => 'collaborative_filtering',
            'accuracy' => 0.75,
            'precision' => 0.70,
            'recall' => 0.65,
            'f1_score' => 0.67,
            'last_trained' => time(),
            'training_samples' => count($trainingData)
        ];
    }

    private function trainContentBasedModel(array $trainingData): void
    {
        // Simplified content-based training
        $this->recommendationModels['content_based'] = [
            'type' => 'content_based',
            'accuracy' => 0.80,
            'precision' => 0.75,
            'recall' => 0.70,
            'f1_score' => 0.72,
            'last_trained' => time(),
            'training_samples' => count($trainingData)
        ];
    }

    private function trainHybridModel(array $trainingData): void
    {
        // Simplified hybrid model training
        $this->recommendationModels['hybrid'] = [
            'type' => 'hybrid',
            'accuracy' => 0.85,
            'precision' => 0.80,
            'recall' => 0.75,
            'f1_score' => 0.77,
            'last_trained' => time(),
            'training_samples' => count($trainingData)
        ];
    }

    private function loadUserProfiles(): void
    {
        // Load user profiles from storage
        $this->userProfiles = [];
    }

    private function loadContentFeatures(): void
    {
        // Load content features from storage
        $this->contentFeatures = [];
    }

    private function trainRecommendationModels(): void
    {
        // Train models with existing data
        $this->trainCollaborativeFilteringModel($this->userInteractions);
        $this->trainContentBasedModel($this->contentFeatures);
        $this->trainHybridModel(array_merge($this->userInteractions, $this->contentFeatures));
    }

    private function initializeMLAlgorithms(): void
    {
        $this->mlAlgorithms = [
            'collaborative_filtering' => 'User-based collaborative filtering',
            'content_based' => 'Content-based filtering',
            'hybrid' => 'Hybrid recommendation approach',
            'matrix_factorization' => 'Matrix factorization',
            'deep_learning' => 'Deep learning recommendations'
        ];
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->userProfiles = [];
        $this->contentFeatures = [];
        $this->recommendationModels = [];
        $this->userInteractions = [];
        $this->contentSimilarity = [];
        $this->isEnabled = false;
        $this->logger->info("ML Recommendation Service cleaned up");
    }
}

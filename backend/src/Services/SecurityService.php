<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use React\EventLoop\LoopInterface;

class SecurityService
{
    private Logger $logger;
    private Client $httpClient;
    private LoopInterface $loop;
    private array $config;
    private array $threatDatabase = [];
    private array $securityRules = [];
    private array $blockedDomains = [];
    private array $suspiciousPatterns = [];
    private array $securityEvents = [];
    private bool $isEnabled = false;
    private array $threatIntelligence = [];
    private array $malwareSignatures = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->initializeSecurityRules();
        $this->loadThreatDatabase();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Security Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Security Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->startThreatMonitoring();
            $this->updateThreatIntelligence();
            
            $this->logger->info("Security Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Security Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function scanUrl(string $url): array
    {
        if (!$this->isEnabled) {
            return ['safe' => true, 'message' => 'Security scanning disabled'];
        }

        try {
            $scanResult = [
                'url' => $url,
                'safe' => true,
                'threats' => [],
                'risk_score' => 0,
                'recommendations' => [],
                'scan_timestamp' => time()
            ];

            // Check against blocked domains
            if ($this->isBlockedDomain($url)) {
                $scanResult['safe'] = false;
                $scanResult['threats'][] = 'Blocked domain';
                $scanResult['risk_score'] = 100;
                return $scanResult;
            }

            // Check for suspicious patterns
            $suspiciousPatterns = $this->checkSuspiciousPatterns($url);
            if (!empty($suspiciousPatterns)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $suspiciousPatterns);
                $scanResult['risk_score'] += count($suspiciousPatterns) * 20;
            }

            // Check for malware signatures
            $malwareThreats = $this->checkMalwareSignatures($url);
            if (!empty($malwareThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $malwareThreats);
                $scanResult['risk_score'] += count($malwareThreats) * 30;
            }

            // Check SSL/TLS security
            $sslIssues = $this->checkSSLSecurity($url);
            if (!empty($sslIssues)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $sslIssues);
                $scanResult['risk_score'] += count($sslIssues) * 15;
            }

            // Check for phishing indicators
            $phishingThreats = $this->checkPhishingIndicators($url);
            if (!empty($phishingThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $phishingThreats);
                $scanResult['risk_score'] += count($phishingThreats) * 25;
            }

            // Check reputation
            $reputationScore = $this->checkDomainReputation($url);
            $scanResult['risk_score'] += (100 - $reputationScore) / 2;

            // Determine if URL is safe
            $scanResult['safe'] = $scanResult['risk_score'] < 50;
            $scanResult['recommendations'] = $this->generateSecurityRecommendations($scanResult);

            $this->logSecurityEvent('url_scan', $scanResult);

            return $scanResult;
        } catch (\Exception $e) {
            $this->logger->error("URL scan failed", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return [
                'url' => $url,
                'safe' => false,
                'threats' => ['Scan failed'],
                'risk_score' => 50,
                'recommendations' => ['Unable to complete security scan'],
                'scan_timestamp' => time()
            ];
        }
    }

    public function scanContent(string $content, string $contentType = 'html'): array
    {
        if (!$this->isEnabled) {
            return ['safe' => true, 'message' => 'Content scanning disabled'];
        }

        try {
            $scanResult = [
                'content_type' => $contentType,
                'safe' => true,
                'threats' => [],
                'risk_score' => 0,
                'recommendations' => [],
                'scan_timestamp' => time()
            ];

            // Check for malicious scripts
            $scriptThreats = $this->checkMaliciousScripts($content);
            if (!empty($scriptThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $scriptThreats);
                $scanResult['risk_score'] += count($scriptThreats) * 25;
            }

            // Check for suspicious URLs
            $urlThreats = $this->extractAndScanUrls($content);
            if (!empty($urlThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $urlThreats);
                $scanResult['risk_score'] += count($urlThreats) * 20;
            }

            // Check for data exfiltration patterns
            $exfiltrationThreats = $this->checkDataExfiltration($content);
            if (!empty($exfiltrationThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $exfiltrationThreats);
                $scanResult['risk_score'] += count($exfiltrationThreats) * 30;
            }

            // Check for suspicious file types
            $fileThreats = $this->checkSuspiciousFileTypes($content);
            if (!empty($fileThreats)) {
                $scanResult['threats'] = array_merge($scanResult['threats'], $fileThreats);
                $scanResult['risk_score'] += count($fileThreats) * 15;
            }

            $scanResult['safe'] = $scanResult['risk_score'] < 50;
            $scanResult['recommendations'] = $this->generateContentRecommendations($scanResult);

            $this->logSecurityEvent('content_scan', $scanResult);

            return $scanResult;
        } catch (\Exception $e) {
            $this->logger->error("Content scan failed", [
                'content_type' => $contentType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'content_type' => $contentType,
                'safe' => false,
                'threats' => ['Content scan failed'],
                'risk_score' => 50,
                'recommendations' => ['Unable to complete content scan'],
                'scan_timestamp' => time()
            ];
        }
    }

    public function detectIntrusion(array $requestData): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        try {
            $intrusionIndicators = [];

            // Check for SQL injection patterns
            if ($this->detectSQLInjection($requestData)) {
                $intrusionIndicators[] = 'SQL injection attempt';
            }

            // Check for XSS patterns
            if ($this->detectXSS($requestData)) {
                $intrusionIndicators[] = 'XSS attempt';
            }

            // Check for CSRF patterns
            if ($this->detectCSRF($requestData)) {
                $intrusionIndicators[] = 'CSRF attempt';
            }

            // Check for directory traversal
            if ($this->detectDirectoryTraversal($requestData)) {
                $intrusionIndicators[] = 'Directory traversal attempt';
            }

            // Check for command injection
            if ($this->detectCommandInjection($requestData)) {
                $intrusionIndicators[] = 'Command injection attempt';
            }

            if (!empty($intrusionIndicators)) {
                $this->logSecurityEvent('intrusion_detected', [
                    'indicators' => $intrusionIndicators,
                    'request_data' => $requestData,
                    'timestamp' => time()
                ]);

                $this->blockSuspiciousIP($requestData['ip'] ?? 'unknown');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error("Intrusion detection failed", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSecurityReport(string $period = '1d'): array
    {
        $startTime = $this->getPeriodStartTime($period);
        $filteredEvents = array_filter($this->securityEvents, function($event) use ($startTime) {
            return $event['timestamp'] >= $startTime;
        });

        $threatCounts = [];
        $riskLevels = [];
        $blockedDomains = [];
        $topThreats = [];

        foreach ($filteredEvents as $event) {
            $type = $event['type'];
            $threatCounts[$type] = ($threatCounts[$type] ?? 0) + 1;

            if (isset($event['risk_score'])) {
                $riskLevels[] = $event['risk_score'];
            }

            if (isset($event['blocked_domain'])) {
                $blockedDomains[] = $event['blocked_domain'];
            }

            if (isset($event['threats'])) {
                foreach ($event['threats'] as $threat) {
                    $topThreats[$threat] = ($topThreats[$threat] ?? 0) + 1;
                }
            }
        }

        return [
            'period' => $period,
            'total_events' => count($filteredEvents),
            'threat_counts' => $threatCounts,
            'average_risk_score' => !empty($riskLevels) ? array_sum($riskLevels) / count($riskLevels) : 0,
            'blocked_domains' => array_unique($blockedDomains),
            'top_threats' => array_slice($topThreats, 0, 10, true),
            'security_score' => $this->calculateSecurityScore($filteredEvents),
            'recommendations' => $this->generateSecurityRecommendations($filteredEvents)
        ];
    }

    public function addSecurityRule(string $name, array $rule): bool
    {
        try {
            $this->securityRules[$name] = [
                'name' => $name,
                'pattern' => $rule['pattern'],
                'action' => $rule['action'] ?? 'block',
                'severity' => $rule['severity'] ?? 'medium',
                'enabled' => $rule['enabled'] ?? true,
                'created_at' => time()
            ];

            $this->logger->info("Security rule added", ['rule' => $name]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add security rule", [
                'rule' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function blockDomain(string $domain, string $reason = ''): bool
    {
        $this->blockedDomains[$domain] = [
            'domain' => $domain,
            'reason' => $reason,
            'blocked_at' => time()
        ];

        $this->logger->info("Domain blocked", [
            'domain' => $domain,
            'reason' => $reason
        ]);

        return true;
    }

    public function unblockDomain(string $domain): bool
    {
        if (isset($this->blockedDomains[$domain])) {
            unset($this->blockedDomains[$domain]);
            $this->logger->info("Domain unblocked", ['domain' => $domain]);
            return true;
        }

        return false;
    }

    public function getBlockedDomains(): array
    {
        return $this->blockedDomains;
    }

    public function updateThreatIntelligence(): bool
    {
        try {
            // This would typically fetch from threat intelligence feeds
            $this->threatIntelligence = [
                'malware_domains' => $this->loadMalwareDomains(),
                'phishing_urls' => $this->loadPhishingUrls(),
                'suspicious_ips' => $this->loadSuspiciousIPs(),
                'updated_at' => time()
            ];

            $this->logger->info("Threat intelligence updated");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to update threat intelligence", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function isBlockedDomain(string $url): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);
        return isset($this->blockedDomains[$domain]);
    }

    private function checkSuspiciousPatterns(string $url): array
    {
        $threats = [];

        foreach ($this->suspiciousPatterns as $pattern => $description) {
            if (preg_match($pattern, $url)) {
                $threats[] = $description;
            }
        }

        return $threats;
    }

    private function checkMalwareSignatures(string $url): array
    {
        $threats = [];

        foreach ($this->malwareSignatures as $signature => $description) {
            if (strpos($url, $signature) !== false) {
                $threats[] = $description;
            }
        }

        return $threats;
    }

    private function checkSSLSecurity(string $url): array
    {
        $threats = [];

        if (strpos($url, 'http://') === 0) {
            $threats[] = 'Insecure HTTP connection';
        }

        // Additional SSL checks would go here
        return $threats;
    }

    private function checkPhishingIndicators(string $url): array
    {
        $threats = [];

        // Check for suspicious domain patterns
        $suspiciousPatterns = [
            '/\d+\.\d+\.\d+\.\d+/', // IP addresses
            '/[a-z0-9-]+\.tk$/', // .tk domains
            '/[a-z0-9-]+\.ml$/', // .ml domains
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                $threats[] = 'Suspicious domain pattern';
            }
        }

        return $threats;
    }

    private function checkDomainReputation(string $url): int
    {
        // Simplified reputation check
        $domain = parse_url($url, PHP_URL_HOST);
        
        if (isset($this->threatIntelligence['malware_domains'][$domain])) {
            return 0;
        }

        if (isset($this->threatIntelligence['phishing_urls'][$domain])) {
            return 20;
        }

        return 80; // Default reputation score
    }

    private function checkMaliciousScripts(string $content): array
    {
        $threats = [];
        $maliciousPatterns = [
            '/eval\s*\(/i' => 'eval() function detected',
            '/document\.write\s*\(/i' => 'document.write() detected',
            '/innerHTML\s*=/i' => 'innerHTML assignment detected',
            '/setTimeout\s*\(/i' => 'setTimeout() detected',
            '/setInterval\s*\(/i' => 'setInterval() detected'
        ];

        foreach ($maliciousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                $threats[] = $description;
            }
        }

        return $threats;
    }

    private function extractAndScanUrls(string $content): array
    {
        $threats = [];
        $urlPattern = '/https?:\/\/[^\s<>"]+/';
        preg_match_all($urlPattern, $content, $matches);

        foreach ($matches[0] as $url) {
            $urlScan = $this->scanUrl($url);
            if (!$urlScan['safe']) {
                $threats[] = 'Suspicious URL: ' . $url;
            }
        }

        return $threats;
    }

    private function checkDataExfiltration(string $content): array
    {
        $threats = [];
        $exfiltrationPatterns = [
            '/XMLHttpRequest/i' => 'XMLHttpRequest detected',
            '/fetch\s*\(/i' => 'fetch() API detected',
            '/navigator\.sendBeacon/i' => 'sendBeacon() detected'
        ];

        foreach ($exfiltrationPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                $threats[] = $description;
            }
        }

        return $threats;
    }

    private function checkSuspiciousFileTypes(string $content): array
    {
        $threats = [];
        $suspiciousExtensions = ['.exe', '.bat', '.cmd', '.scr', '.pif', '.com'];

        foreach ($suspiciousExtensions as $ext) {
            if (stripos($content, $ext) !== false) {
                $threats[] = 'Suspicious file type: ' . $ext;
            }
        }

        return $threats;
    }

    private function detectSQLInjection(array $requestData): bool
    {
        $sqlPatterns = [
            '/union\s+select/i',
            '/or\s+1\s*=\s*1/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i'
        ];

        foreach ($requestData as $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function detectXSS(array $requestData): bool
    {
        $xssPatterns = [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i'
        ];

        foreach ($requestData as $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function detectCSRF(array $requestData): bool
    {
        // Check for missing CSRF token
        return !isset($requestData['csrf_token']) && 
               in_array($requestData['method'] ?? '', ['POST', 'PUT', 'DELETE']);
    }

    private function detectDirectoryTraversal(array $requestData): bool
    {
        $traversalPatterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/',
            '/%2e%2e%5c/'
        ];

        foreach ($requestData as $value) {
            if (is_string($value)) {
                foreach ($traversalPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function detectCommandInjection(array $requestData): bool
    {
        $commandPatterns = [
            '/;\s*\w+/',
            '/\|\s*\w+/',
            '/&&\s*\w+/',
            '/`[^`]+`/'
        ];

        foreach ($requestData as $value) {
            if (is_string($value)) {
                foreach ($commandPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function blockSuspiciousIP(string $ip): void
    {
        // Implementation would block the IP address
        $this->logger->warning("Suspicious IP blocked", ['ip' => $ip]);
    }

    private function generateSecurityRecommendations(array $scanResult): array
    {
        $recommendations = [];

        if ($scanResult['risk_score'] > 70) {
            $recommendations[] = 'High risk detected - avoid visiting this site';
        } elseif ($scanResult['risk_score'] > 40) {
            $recommendations[] = 'Medium risk detected - proceed with caution';
        }

        if (in_array('Insecure HTTP connection', $scanResult['threats'])) {
            $recommendations[] = 'Use HTTPS for secure communication';
        }

        if (in_array('Suspicious domain pattern', $scanResult['threats'])) {
            $recommendations[] = 'Verify the domain before proceeding';
        }

        return $recommendations;
    }

    private function generateContentRecommendations(array $scanResult): array
    {
        $recommendations = [];

        if (in_array('eval() function detected', $scanResult['threats'])) {
            $recommendations[] = 'Avoid using eval() function - it can execute arbitrary code';
        }

        if (in_array('document.write() detected', $scanResult['threats'])) {
            $recommendations[] = 'Consider using safer DOM manipulation methods';
        }

        return $recommendations;
    }

    private function calculateSecurityScore(array $events): int
    {
        if (empty($events)) {
            return 100;
        }

        $totalRisk = 0;
        foreach ($events as $event) {
            $totalRisk += $event['risk_score'] ?? 0;
        }

        $averageRisk = $totalRisk / count($events);
        return max(0, 100 - $averageRisk);
    }

    private function logSecurityEvent(string $type, array $data): void
    {
        $this->securityEvents[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ];

        // Keep only last 1000 events
        if (count($this->securityEvents) > 1000) {
            $this->securityEvents = array_slice($this->securityEvents, -1000);
        }
    }

    private function getPeriodStartTime(string $period): int
    {
        $now = time();
        $periods = [
            '1h' => 3600,
            '1d' => 86400,
            '1w' => 604800,
            '1m' => 2592000
        ];

        $seconds = $periods[$period] ?? 86400;
        return $now - $seconds;
    }

    private function initializeSecurityRules(): void
    {
        $this->securityRules = [
            'sql_injection' => [
                'pattern' => '/union\s+select/i',
                'action' => 'block',
                'severity' => 'high'
            ],
            'xss_attempt' => [
                'pattern' => '/<script[^>]*>/i',
                'action' => 'block',
                'severity' => 'high'
            ],
            'directory_traversal' => [
                'pattern' => '/\.\.\//',
                'action' => 'block',
                'severity' => 'medium'
            ]
        ];
    }

    private function loadThreatDatabase(): void
    {
        $this->suspiciousPatterns = [
            '/\d+\.\d+\.\d+\.\d+/' => 'IP address in URL',
            '/[a-z0-9-]+\.tk$/' => 'Suspicious .tk domain',
            '/bit\.ly/' => 'Shortened URL',
            '/tinyurl\.com/' => 'Shortened URL'
        ];

        $this->malwareSignatures = [
            'malware' => 'Malware signature detected',
            'trojan' => 'Trojan signature detected',
            'virus' => 'Virus signature detected'
        ];
    }

    private function loadMalwareDomains(): array
    {
        // This would load from a threat intelligence feed
        return [];
    }

    private function loadPhishingUrls(): array
    {
        // This would load from a threat intelligence feed
        return [];
    }

    private function loadSuspiciousIPs(): array
    {
        // This would load from a threat intelligence feed
        return [];
    }

    private function startThreatMonitoring(): void
    {
        if (!$this->loop) {
            return;
        }

        // Update threat intelligence every hour
        $this->loop->addPeriodicTimer(3600, function() {
            $this->updateThreatIntelligence();
        });
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->threatDatabase = [];
        $this->securityRules = [];
        $this->blockedDomains = [];
        $this->suspiciousPatterns = [];
        $this->securityEvents = [];
        $this->isEnabled = false;
        $this->logger->info("Security Service cleaned up");
    }
}

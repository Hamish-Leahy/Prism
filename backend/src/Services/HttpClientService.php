<?php

namespace Prism\Backend\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Monolog\Logger;

class HttpClientService
{
    private Client $client;
    private array $config;
    private Logger $logger;
    private array $requestHistory = [];
    private array $responseCache = [];
    private int $maxCacheSize = 100;
    private int $maxHistorySize = 50;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $handlerStack = HandlerStack::create();
        
        // Add retry middleware
        $handlerStack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        // Add logging middleware
        $handlerStack->push(Middleware::mapRequest($this->logRequest()));
        $handlerStack->push(Middleware::mapResponse($this->logResponse()));

        // Add caching middleware
        $handlerStack->push(Middleware::mapRequest($this->cacheRequest()));
        $handlerStack->push(Middleware::mapResponse($this->cacheResponse()));

        // Add user agent rotation middleware
        $handlerStack->push(Middleware::mapRequest($this->rotateUserAgent()));

        $this->client = new Client([
            'handler' => $handlerStack,
            'timeout' => $this->config['timeout'] ?? 30,
            'connect_timeout' => $this->config['connect_timeout'] ?? 10,
            'read_timeout' => $this->config['read_timeout'] ?? 30,
            'verify' => $this->config['verify_ssl'] ?? true,
            'allow_redirects' => [
                'max' => $this->config['max_redirects'] ?? 10,
                'strict' => $this->config['strict_redirects'] ?? false,
                'referer' => $this->config['follow_referer'] ?? true,
                'protocols' => $this->config['allowed_protocols'] ?? ['http', 'https'],
                'track_redirects' => true
            ],
            'headers' => $this->getDefaultHeaders(),
            'cookies' => $this->config['enable_cookies'] ?? true,
            'http_errors' => false, // Don't throw exceptions on HTTP error status codes
            'stream' => false,
            'decode_content' => true,
            'sink' => null,
            'expect' => false,
            'version' => '1.1',
            'curl' => $this->getCurlOptions(),
        ]);
    }

    private function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ];
    }

    private function getCurlOptions(): array
    {
        return [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->config['max_redirects'] ?? 10,
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => $this->getRandomUserAgent(),
            CURLOPT_ENCODING => 'gzip,deflate,br',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => $this->config['connect_timeout'] ?? 10,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_DNS_CACHE_TIMEOUT => 300,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 60,
            CURLOPT_TCP_KEEPINTVL => 10,
        ];
    }

    private function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
        ];

        return $userAgents[array_rand($userAgents)];
    }

    private function retryDecider(): callable
    {
        return function ($retries, RequestInterface $request, ResponseInterface $response = null, $exception = null) {
            // Don't retry if we've already retried too many times
            if ($retries >= ($this->config['max_retries'] ?? 3)) {
                return false;
            }

            // Retry on connection exceptions
            if ($exception instanceof ConnectException) {
                $this->logger->warning("Connection failed, retrying...", [
                    'url' => (string) $request->getUri(),
                    'retry' => $retries + 1
                ]);
                return true;
            }

            // Retry on server errors (5xx)
            if ($response && $response->getStatusCode() >= 500) {
                $this->logger->warning("Server error, retrying...", [
                    'url' => (string) $request->getUri(),
                    'status' => $response->getStatusCode(),
                    'retry' => $retries + 1
                ]);
                return true;
            }

            // Retry on too many redirects
            if ($exception instanceof TooManyRedirectsException) {
                $this->logger->warning("Too many redirects, retrying...", [
                    'url' => (string) $request->getUri(),
                    'retry' => $retries + 1
                ]);
                return true;
            }

            return false;
        };
    }

    private function retryDelay(): callable
    {
        return function ($retries) {
            // Exponential backoff with jitter
            $delay = pow(2, $retries) * 1000; // Start with 1 second
            $jitter = rand(0, 1000); // Add up to 1 second of jitter
            return $delay + $jitter;
        };
    }

    private function logRequest(): callable
    {
        return function (RequestInterface $request) {
            $this->requestHistory[] = [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'headers' => $request->getHeaders(),
                'timestamp' => microtime(true)
            ];

            // Keep only the last N requests
            if (count($this->requestHistory) > $this->maxHistorySize) {
                array_shift($this->requestHistory);
            }

            $this->logger->info("HTTP Request", [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'headers' => $request->getHeaders()
            ]);

            return $request;
        };
    }

    private function logResponse(): callable
    {
        return function (ResponseInterface $response) {
            $this->logger->info("HTTP Response", [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'size' => $response->getBody()->getSize()
            ]);

            return $response;
        };
    }

    private function cacheRequest(): callable
    {
        return function (RequestInterface $request) {
            $cacheKey = $this->generateCacheKey($request);
            
            if (isset($this->responseCache[$cacheKey])) {
                $cached = $this->responseCache[$cacheKey];
                
                // Check if cache is still valid
                if (time() - $cached['timestamp'] < ($this->config['cache_ttl'] ?? 300)) {
                    $this->logger->debug("Cache hit", ['url' => (string) $request->getUri()]);
                    return $request->withHeader('X-Cache', 'HIT');
                }
            }

            return $request->withHeader('X-Cache', 'MISS');
        };
    }

    private function cacheResponse(): callable
    {
        return function (ResponseInterface $response) {
            // Only cache successful responses
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $cacheKey = $this->generateCacheKeyFromResponse($response);
                
                $this->responseCache[$cacheKey] = [
                    'response' => $response,
                    'timestamp' => time()
                ];

                // Keep cache size under limit
                if (count($this->responseCache) > $this->maxCacheSize) {
                    // Remove oldest entries
                    $this->responseCache = array_slice($this->responseCache, -$this->maxCacheSize, null, true);
                }
            }

            return $response;
        };
    }

    private function rotateUserAgent(): callable
    {
        return function (RequestInterface $request) {
            $userAgent = $this->getRandomUserAgent();
            return $request->withHeader('User-Agent', $userAgent);
        };
    }

    private function generateCacheKey(RequestInterface $request): string
    {
        return md5($request->getMethod() . '|' . (string) $request->getUri());
    }

    private function generateCacheKeyFromResponse(ResponseInterface $response): string
    {
        // This is a simplified cache key - in a real implementation,
        // you'd want to extract the original request URL from the response
        return md5('response_' . time());
    }

    public function get(string $url, array $options = []): array
    {
        try {
            $response = $this->client->get($url, $options);
            
            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents(),
                'url' => $url,
                'final_url' => $this->getFinalUrl($response),
                'redirects' => $this->getRedirectHistory($response),
                'cached' => $response->hasHeader('X-Cache') && $response->getHeader('X-Cache')[0] === 'HIT'
            ];
        } catch (RequestException $e) {
            $this->logger->error("HTTP Request failed", [
                'url' => $url,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $url
            ];
        }
    }

    public function post(string $url, array $data = [], array $options = []): array
    {
        try {
            $options['json'] = $data;
            $response = $this->client->post($url, $options);
            
            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents(),
                'url' => $url,
                'final_url' => $this->getFinalUrl($response)
            ];
        } catch (RequestException $e) {
            $this->logger->error("HTTP POST failed", [
                'url' => $url,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $url
            ];
        }
    }

    public function head(string $url, array $options = []): array
    {
        try {
            $response = $this->client->head($url, $options);
            
            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'url' => $url,
                'final_url' => $this->getFinalUrl($response)
            ];
        } catch (RequestException $e) {
            $this->logger->error("HTTP HEAD failed", [
                'url' => $url,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $url
            ];
        }
    }

    public function download(string $url, string $destination, array $options = []): array
    {
        try {
            $options['sink'] = $destination;
            $response = $this->client->get($url, $options);
            
            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'destination' => $destination,
                'size' => filesize($destination),
                'url' => $url,
                'final_url' => $this->getFinalUrl($response)
            ];
        } catch (RequestException $e) {
            $this->logger->error("Download failed", [
                'url' => $url,
                'destination' => $destination,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $url
            ];
        }
    }

    public function getRequestHistory(): array
    {
        return $this->requestHistory;
    }

    public function clearCache(): void
    {
        $this->responseCache = [];
        $this->logger->info("HTTP cache cleared");
    }

    public function getCacheStats(): array
    {
        return [
            'cache_size' => count($this->responseCache),
            'max_cache_size' => $this->maxCacheSize,
            'history_size' => count($this->requestHistory),
            'max_history_size' => $this->maxHistorySize
        ];
    }

    private function getFinalUrl(ResponseInterface $response): string
    {
        // Extract final URL from redirect history
        $redirects = $response->getHeader('X-Guzzle-Redirect-History');
        if (!empty($redirects)) {
            return end($redirects);
        }
        
        return $response->getHeader('Location')[0] ?? '';
    }

    private function getRedirectHistory(ResponseInterface $response): array
    {
        return $response->getHeader('X-Guzzle-Redirect-History') ?? [];
    }

    public function setProxy(string $proxy): void
    {
        $this->client = new Client(array_merge($this->client->getConfig(), [
            'proxy' => $proxy
        ]));
    }

    public function setHeaders(array $headers): void
    {
        $this->client = new Client(array_merge($this->client->getConfig(), [
            'headers' => array_merge($this->getDefaultHeaders(), $headers)
        ]));
    }

    public function setTimeout(int $timeout): void
    {
        $this->client = new Client(array_merge($this->client->getConfig(), [
            'timeout' => $timeout
        ]));
    }

    public function close(): void
    {
        $this->client = null;
        $this->responseCache = [];
        $this->requestHistory = [];
    }
}

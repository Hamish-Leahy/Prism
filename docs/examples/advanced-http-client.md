# Advanced HTTP Client Usage Examples

This document demonstrates how to use the advanced HTTP client service in the Prism Browser backend.

## Basic Usage

### Simple GET Request

```php
use Prism\Backend\Services\HttpClientService;
use Monolog\Logger;

$logger = new Logger('http-client');
$httpClient = new HttpClientService([
    'timeout' => 30,
    'verify_ssl' => true,
    'max_retries' => 3
], $logger);

$response = $httpClient->get('https://example.com');

if ($response['success']) {
    echo "Status: " . $response['status'] . "\n";
    echo "Content: " . $response['body'] . "\n";
    echo "Headers: " . print_r($response['headers'], true) . "\n";
} else {
    echo "Error: " . $response['error'] . "\n";
}
```

### POST Request with JSON Data

```php
$data = [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'message' => 'Hello World'
];

$response = $httpClient->post('https://api.example.com/users', $data);

if ($response['success']) {
    $result = json_decode($response['body'], true);
    echo "User created with ID: " . $result['id'] . "\n";
}
```

### File Download

```php
$response = $httpClient->download(
    'https://example.com/large-file.zip',
    '/tmp/downloaded-file.zip'
);

if ($response['success']) {
    echo "Downloaded " . $response['size'] . " bytes to " . $response['destination'] . "\n";
}
```

## Advanced Configuration

### Custom Headers and User Agent

```php
$httpClient->setHeaders([
    'Authorization' => 'Bearer your-token-here',
    'X-Custom-Header' => 'custom-value',
    'Accept' => 'application/json'
]);

$httpClient->setCustomHeaders([
    'X-API-Version' => '2.0',
    'X-Client-Name' => 'Prism Browser'
]);
```

### Proxy Configuration

```php
$httpClient->setProxy('http://proxy.example.com:8080');
// Or with authentication
$httpClient->setProxy('http://username:password@proxy.example.com:8080');
```

### Timeout Configuration

```php
$httpClient->setTimeout(60); // 60 seconds
```

## Caching and Performance

### Cache Statistics

```php
$stats = $httpClient->getCacheStats();
echo "Cache size: " . $stats['cache_size'] . "\n";
echo "Max cache size: " . $stats['max_cache_size'] . "\n";
echo "Request history: " . $stats['history_size'] . "\n";
```

### Clear Cache

```php
$httpClient->clearCache();
echo "Cache cleared\n";
```

### Request History

```php
$history = $httpClient->getRequestHistory();
foreach ($history as $request) {
    echo "Method: " . $request['method'] . "\n";
    echo "URL: " . $request['url'] . "\n";
    echo "Timestamp: " . date('Y-m-d H:i:s', $request['timestamp']) . "\n";
}
```

## Error Handling

### Comprehensive Error Handling

```php
try {
    $response = $httpClient->get('https://example.com');
    
    if (!$response['success']) {
        switch ($response['code']) {
            case 404:
                echo "Page not found\n";
                break;
            case 500:
                echo "Server error\n";
                break;
            case 0:
                echo "Connection failed: " . $response['error'] . "\n";
                break;
            default:
                echo "HTTP Error " . $response['code'] . ": " . $response['error'] . "\n";
        }
    } else {
        echo "Success: " . $response['body'] . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
```

## Integration with Prism Engine

### Using with PrismEngine

```php
use Prism\Backend\Services\Engines\PrismEngine;

$engine = new PrismEngine([
    'timeout' => 30,
    'verify_ssl' => true,
    'cache_ttl' => 600,
    'max_retries' => 3
]);

$engine->initialize();
$engine->navigate('https://example.com');

// Get page metadata
$metadata = $engine->getPageMetadata();
echo "Title: " . $metadata['title'] . "\n";
echo "Description: " . $metadata['description'] . "\n";
echo "Response time: " . $metadata['response_time'] . "s\n";

// Get performance metrics
$metrics = $engine->getPerformanceMetrics();
echo "Memory usage: " . $metrics['memory_usage'] . " bytes\n";
echo "Content length: " . $metrics['content_length'] . " bytes\n";
echo "Request count: " . $metrics['request_count'] . "\n";

$engine->close();
```

### Download Resources

```php
$engine->navigate('https://example.com');

// Download an image
$result = $engine->downloadResource(
    'https://example.com/image.jpg',
    '/tmp/image.jpg'
);

if ($result['success']) {
    echo "Downloaded " . $result['size'] . " bytes\n";
}
```

### POST Data

```php
$result = $engine->postData('https://api.example.com/submit', [
    'form_data' => 'value',
    'another_field' => 'another_value'
]);

if ($result['success']) {
    echo "POST successful: " . $result['body'] . "\n";
}
```

## Configuration Examples

### Development Configuration

```php
$config = [
    'timeout' => 10,
    'connect_timeout' => 5,
    'verify_ssl' => false, // For development
    'max_retries' => 1,
    'cache_ttl' => 60,
    'user_agent' => 'Prism/1.0-dev (Development)'
];
```

### Production Configuration

```php
$config = [
    'timeout' => 30,
    'connect_timeout' => 10,
    'verify_ssl' => true,
    'max_retries' => 3,
    'cache_ttl' => 1800, // 30 minutes
    'user_agent' => 'Prism/1.0 (Production)',
    'max_redirects' => 10,
    'follow_referer' => true
];
```

### High-Performance Configuration

```php
$config = [
    'timeout' => 60,
    'connect_timeout' => 15,
    'verify_ssl' => true,
    'max_retries' => 5,
    'cache_ttl' => 3600, // 1 hour
    'max_redirects' => 20,
    'connection_pooling' => true,
    'keep_alive' => true,
    'compression' => true
];
```

## Monitoring and Debugging

### Enable Detailed Logging

```php
$logger = new Logger('http-client');
$logger->pushHandler(new StreamHandler('logs/http-client.log', Logger::DEBUG));

$httpClient = new HttpClientService($config, $logger);
```

### Performance Monitoring

```php
$startTime = microtime(true);
$response = $httpClient->get('https://example.com');
$endTime = microtime(true);

echo "Request took " . ($endTime - $startTime) . " seconds\n";
echo "Response time: " . $response['response_time'] . " seconds\n";
```

### Memory Usage Monitoring

```php
$memoryBefore = memory_get_usage(true);
$response = $httpClient->get('https://example.com');
$memoryAfter = memory_get_usage(true);

echo "Memory used: " . ($memoryAfter - $memoryBefore) . " bytes\n";
echo "Peak memory: " . memory_get_peak_usage(true) . " bytes\n";
```

## Best Practices

1. **Always check response success**: Check `$response['success']` before processing
2. **Handle errors gracefully**: Implement proper error handling for different scenarios
3. **Use appropriate timeouts**: Set timeouts based on your use case
4. **Enable caching**: Use caching for frequently accessed resources
5. **Monitor performance**: Track response times and memory usage
6. **Clean up resources**: Call `close()` when done with the client
7. **Use HTTPS**: Always prefer HTTPS for security
8. **Validate responses**: Validate response data before using it
9. **Implement retry logic**: Use the built-in retry mechanism for reliability
10. **Log important events**: Enable logging for debugging and monitoring

## Troubleshooting

### Common Issues

1. **SSL Certificate Errors**: Set `verify_ssl => false` for development
2. **Timeout Issues**: Increase timeout values for slow connections
3. **Memory Issues**: Monitor memory usage and clear cache regularly
4. **Connection Issues**: Check network connectivity and proxy settings
5. **Redirect Issues**: Adjust `max_redirects` setting if needed

### Debug Information

```php
// Get detailed request information
$history = $httpClient->getRequestHistory();
$lastRequest = end($history);
echo "Last request: " . print_r($lastRequest, true) . "\n";

// Get cache statistics
$stats = $httpClient->getCacheStats();
echo "Cache stats: " . print_r($stats, true) . "\n";
```

This advanced HTTP client provides a robust foundation for web scraping, API interactions, and general HTTP operations in the Prism Browser backend.

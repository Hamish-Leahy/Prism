# Prism Engine

Custom lightweight rendering engine for the Prism browser, optimized for performance and minimal resource usage.

## Features

- **Lightweight**: Minimal memory and CPU footprint
- **Fast**: Optimized for speed and responsiveness
- **Customizable**: Highly configurable rendering pipeline
- **Extensible**: Plugin-based architecture
- **Privacy-First**: Built with privacy in mind

## Architecture

The Prism engine is built with a modular architecture:

```
Prism Engine/
├── Core/           # Core rendering engine
├── Parser/         # HTML/CSS/JS parsers
├── Renderer/       # Rendering pipeline
├── Network/        # HTTP client and caching
├── Plugins/        # Extensible plugin system
└── Utils/          # Utility functions
```

## Components

### HTML Parser
- Fast, streaming HTML parser
- Supports HTML5 standards
- Minimal memory usage
- Error recovery

### CSS Engine
- CSS3 selector support
- Flexbox and Grid layout
- Custom properties support
- Optimized rendering

### JavaScript Engine
- V8-based JavaScript execution
- ES6+ support
- Module system
- Performance optimizations

### Network Layer
- HTTP/2 support
- Connection pooling
- Intelligent caching
- Request prioritization

## Requirements

- PHP 8.1+
- V8 JavaScript engine
- libcurl for HTTP requests
- libxml2 for HTML parsing

## Installation

1. **Install Dependencies**:
   ```bash
   # macOS
   brew install v8 libcurl libxml2
   
   # Ubuntu/Debian
   sudo apt-get install libv8-dev libcurl4-openssl-dev libxml2-dev
   ```

2. **Install PHP Extensions**:
   ```bash
   # Install V8 extension
   pecl install v8js
   
   # Install cURL extension (usually included)
   # Install XML extension (usually included)
   ```

3. **Configure PHP**:
   Add to your `php.ini`:
   ```ini
   extension=v8js.so
   extension=curl.so
   extension=xml.so
   ```

## Configuration

### Basic Configuration
```php
'config' => [
    'memory_limit' => '256M',
    'timeout' => 30,
    'cache_enabled' => true,
    'javascript_enabled' => true,
    'css_enabled' => true,
    'images_enabled' => true,
    'max_connections' => 10,
    'user_agent' => 'Prism/1.0 (Custom Engine)'
]
```

### Performance Options
- `memory_limit`: Maximum memory usage
- `timeout`: Request timeout in seconds
- `cache_enabled`: Enable response caching
- `max_connections`: Maximum concurrent connections
- `connection_timeout`: Connection timeout

### Feature Flags
- `javascript_enabled`: Enable JavaScript execution
- `css_enabled`: Enable CSS rendering
- `images_enabled`: Enable image loading
- `cookies_enabled`: Enable cookie support
- `local_storage_enabled`: Enable localStorage

## Usage

```php
use Prism\Backend\Services\Engines\PrismEngine;

$engine = new PrismEngine($config);
$engine->initialize();
$engine->navigate('https://example.com');
$content = $engine->getPageContent();
$title = $engine->getPageTitle();
$engine->close();
```

## Performance

- **Memory Usage**: ~50-150MB per tab
- **CPU Usage**: Low to Medium
- **Startup Time**: <1 second
- **Compatibility**: Good (85%+)

## Plugin System

The Prism engine supports a plugin system for extending functionality:

### Built-in Plugins
- **Ad Blocker**: Block advertisements
- **Privacy Guard**: Enhanced privacy protection
- **Performance Monitor**: Monitor rendering performance
- **Developer Tools**: Basic debugging tools

### Custom Plugins
```php
interface PrismPlugin {
    public function initialize(Engine $engine): void;
    public function handleRequest(Request $request): ?Response;
    public function handleResponse(Response $response): Response;
    public function cleanup(): void;
}
```

## Caching System

### Multi-Level Caching
1. **Memory Cache**: Fast access to frequently used data
2. **Disk Cache**: Persistent storage for larger data
3. **Network Cache**: HTTP caching headers support

### Cache Configuration
```php
'cache' => [
    'enabled' => true,
    'memory_limit' => '64M',
    'disk_limit' => '512M',
    'ttl' => 3600, // 1 hour
    'strategy' => 'lru' // or 'fifo', 'lfu'
]
```

## Security Features

### Sandboxing
- Isolated JavaScript execution
- Restricted file system access
- Network request filtering
- Memory protection

### Privacy Protection
- No tracking by default
- Minimal data collection
- Secure cookie handling
- Private browsing mode

## Troubleshooting

### Common Issues

1. **V8 Extension Not Found**:
   - Install V8 extension: `pecl install v8js`
   - Check PHP configuration
   - Restart web server

2. **Memory Issues**:
   - Increase memory limit
   - Enable caching
   - Reduce concurrent connections

3. **Performance Issues**:
   - Enable caching
   - Optimize JavaScript
   - Use connection pooling

### Debug Mode

Enable debug logging:
```php
'config' => [
    'debug' => true,
    'log_level' => 'debug',
    'profile' => true
]
```

## Contributing

1. Follow the engine interface standards
2. Add comprehensive tests
3. Update documentation
4. Test performance impact
5. Submit pull requests

## Roadmap

- [ ] WebAssembly support
- [ ] Service Worker support
- [ ] WebRTC support
- [ ] Advanced CSS features
- [ ] Plugin marketplace

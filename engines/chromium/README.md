# Chromium Engine

Chromium-based rendering engine for the Prism browser, providing full web compatibility and Chrome extension support.

## Features

- **Full Web Compatibility**: Supports all modern web standards
- **Chrome Extensions**: Compatible with Chrome extension ecosystem
- **Hardware Acceleration**: GPU-accelerated rendering
- **DevTools**: Built-in developer tools
- **Security**: Sandboxed processes for security

## Requirements

- Chromium browser binary
- PHP 8.1+
- ChromeDriver (for automation)

## Installation

1. **Install Chromium**:
   ```bash
   # macOS
   brew install chromium
   
   # Ubuntu/Debian
   sudo apt-get install chromium-browser
   
   # Windows
   # Download from https://www.chromium.org/getting-involved/download-chromium
   ```

2. **Install ChromeDriver**:
   ```bash
   # macOS
   brew install chromedriver
   
   # Or download from https://chromedriver.chromium.org/
   ```

3. **Configure Path**:
   Update `backend/config/app.php` with your Chromium binary path:
   ```php
   'chromium' => [
       'config' => [
           'binary_path' => '/usr/bin/chromium-browser', // or your path
           'driver_path' => '/usr/local/bin/chromedriver'
       ]
   ]
   ```

## Configuration

### Basic Configuration
```php
'config' => [
    'binary_path' => '/usr/bin/chromium-browser',
    'driver_path' => '/usr/local/bin/chromedriver',
    'headless' => false,
    'sandbox' => true,
    'extensions' => true,
    'window_size' => ['width' => 1920, 'height' => 1080],
    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
]
```

### Advanced Options
- `headless`: Run in headless mode (no GUI)
- `sandbox`: Enable sandboxing for security
- `extensions`: Allow Chrome extensions
- `window_size`: Default window dimensions
- `user_agent`: Custom user agent string
- `proxy`: Proxy server configuration
- `cookies`: Cookie management settings

## Usage

```php
use Prism\Backend\Services\Engines\ChromiumEngine;

$engine = new ChromiumEngine($config);
$engine->initialize();
$engine->navigate('https://example.com');
$content = $engine->getPageContent();
$title = $engine->getPageTitle();
$engine->close();
```

## Performance

- **Memory Usage**: ~200-500MB per tab
- **CPU Usage**: Medium to High
- **Startup Time**: 2-5 seconds
- **Compatibility**: Excellent (99%+)

## Troubleshooting

### Common Issues

1. **Binary Not Found**:
   - Check if Chromium is installed
   - Verify the binary path in configuration
   - Ensure executable permissions

2. **ChromeDriver Issues**:
   - Update ChromeDriver to match Chromium version
   - Check if ChromeDriver is in PATH
   - Verify ChromeDriver permissions

3. **Sandbox Errors**:
   - Disable sandbox if running in containers
   - Check system security settings
   - Run with appropriate permissions

### Debug Mode

Enable debug logging:
```php
'config' => [
    'debug' => true,
    'log_level' => 'debug'
]
```

## Security Considerations

- Always run with sandbox enabled in production
- Keep Chromium and ChromeDriver updated
- Use secure proxy settings
- Implement proper user data isolation

## Contributing

1. Follow the engine interface standards
2. Add comprehensive tests
3. Update documentation
4. Test with various websites
5. Submit pull requests

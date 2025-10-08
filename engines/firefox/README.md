# Firefox Engine

Firefox-based rendering engine for the Prism browser, providing privacy-focused browsing with Gecko rendering.

## Features

- **Privacy-Focused**: Enhanced privacy and security features
- **Firefox Extensions**: Compatible with Firefox extension ecosystem
- **Gecko Rendering**: Mozilla's rendering engine
- **Enhanced Tracking Protection**: Built-in privacy protection
- **Customizable**: Highly configurable for privacy needs

## Requirements

- Firefox browser binary
- GeckoDriver (for automation)
- PHP 8.1+

## Installation

1. **Install Firefox**:
   ```bash
   # macOS
   brew install firefox
   
   # Ubuntu/Debian
   sudo apt-get install firefox
   
   # Windows
   # Download from https://www.mozilla.org/firefox/
   ```

2. **Install GeckoDriver**:
   ```bash
   # macOS
   brew install geckodriver
   
   # Or download from https://github.com/mozilla/geckodriver/releases
   ```

3. **Configure Path**:
   Update `backend/config/app.php` with your Firefox binary path:
   ```php
   'firefox' => [
       'config' => [
           'binary_path' => '/usr/bin/firefox',
           'driver_path' => '/usr/local/bin/geckodriver'
       ]
   ]
   ```

## Configuration

### Basic Configuration
```php
'config' => [
    'binary_path' => '/usr/bin/firefox',
    'driver_path' => '/usr/local/bin/geckodriver',
    'headless' => false,
    'private_mode' => true,
    'extensions' => true,
    'window_size' => ['width' => 1920, 'height' => 1080],
    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0'
]
```

### Privacy Options
- `private_mode`: Always use private browsing
- `tracking_protection`: Enable enhanced tracking protection
- `cookies`: Cookie management settings
- `do_not_track`: Send Do Not Track header
- `block_third_party`: Block third-party cookies

### Advanced Options
- `headless`: Run in headless mode
- `extensions`: Allow Firefox extensions
- `window_size`: Default window dimensions
- `user_agent`: Custom user agent string
- `proxy`: Proxy server configuration
- `preferences`: Custom Firefox preferences

## Usage

```php
use Prism\Backend\Services\Engines\FirefoxEngine;

$engine = new FirefoxEngine($config);
$engine->initialize();
$engine->navigate('https://example.com');
$content = $engine->getPageContent();
$title = $engine->getPageTitle();
$engine->close();
```

## Performance

- **Memory Usage**: ~150-400MB per tab
- **CPU Usage**: Medium
- **Startup Time**: 3-6 seconds
- **Compatibility**: Very Good (95%+)

## Privacy Features

### Enhanced Tracking Protection
- Blocks tracking cookies
- Prevents fingerprinting
- Blocks cryptomining
- Blocks social trackers

### Customizable Privacy Settings
- Cookie management
- History settings
- Download settings
- Security preferences

## Troubleshooting

### Common Issues

1. **Binary Not Found**:
   - Check if Firefox is installed
   - Verify the binary path in configuration
   - Ensure executable permissions

2. **GeckoDriver Issues**:
   - Update GeckoDriver to match Firefox version
   - Check if GeckoDriver is in PATH
   - Verify GeckoDriver permissions

3. **Profile Issues**:
   - Clear Firefox profile if corrupted
   - Check profile permissions
   - Reset Firefox preferences

### Debug Mode

Enable debug logging:
```php
'config' => [
    'debug' => true,
    'log_level' => 'debug',
    'verbose' => true
]
```

## Security Considerations

- Use private mode for sensitive browsing
- Keep Firefox and GeckoDriver updated
- Configure appropriate privacy settings
- Use secure proxy settings
- Implement proper user data isolation

## Contributing

1. Follow the engine interface standards
2. Add comprehensive tests
3. Update documentation
4. Test privacy features
5. Submit pull requests

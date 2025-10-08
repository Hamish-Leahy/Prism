# Prism Engines

Multiple rendering engine implementations for the Prism browser, allowing users to choose their preferred browsing experience.

## Available Engines

### 1. Chromium Engine (`chromium/`)
- **Base**: Chromium/Blink rendering engine
- **Features**: Full web compatibility, Chrome extension support
- **Best For**: Maximum compatibility, extension ecosystem
- **Resource Usage**: High

### 2. Firefox Engine (`firefox/`)
- **Base**: Gecko rendering engine
- **Features**: Privacy-focused, Firefox extension support
- **Best For**: Privacy-conscious users, Firefox ecosystem
- **Resource Usage**: Medium

### 3. Prism Engine (`prism/`)
- **Base**: Custom lightweight engine
- **Features**: Optimized performance, minimal footprint
- **Best For**: Performance, resource efficiency
- **Resource Usage**: Low

## Engine Architecture

Each engine follows a common interface:

```php
interface EngineInterface {
    public function initialize(): bool;
    public function navigate(string $url): void;
    public function executeScript(string $script): mixed;
    public function getPageContent(): string;
    public function getPageTitle(): string;
    public function close(): void;
}
```

## Engine Selection

Users can switch engines through:
1. **Settings UI**: Real-time engine switching
2. **Command Line**: `prism --engine=chromium`
3. **Configuration**: Default engine in settings

## Development

### Adding a New Engine

1. Create new directory in `engines/`
2. Implement `EngineInterface`
3. Add engine configuration
4. Update engine registry
5. Add tests

### Engine Configuration

Each engine has its own config file:
- `engines/{engine}/config.php`
- Engine-specific settings
- Resource limits
- Feature flags

## Performance Comparison

| Engine | Memory Usage | CPU Usage | Startup Time | Compatibility |
|--------|-------------|-----------|--------------|---------------|
| Chromium | High | High | Slow | Excellent |
| Firefox | Medium | Medium | Medium | Very Good |
| Prism | Low | Low | Fast | Good |

## Testing

```bash
# Test all engines
composer test:engines

# Test specific engine
composer test:engine chromium
```

## Contributing

1. Follow engine interface standards
2. Include comprehensive tests
3. Document engine-specific features
4. Update performance benchmarks

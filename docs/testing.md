# Testing Guide

This document provides comprehensive information about testing the Prism Browser project.

## Overview

The Prism Browser project uses a multi-layered testing approach:

- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test component interactions
- **Feature Tests**: Test complete user workflows
- **Engine Tests**: Test browser engine functionality
- **End-to-End Tests**: Test the complete application

## Test Structure

```
tests/
├── Unit/                    # Unit tests
│   ├── Services/           # Service layer tests
│   ├── Controllers/        # Controller tests
│   └── Models/             # Model tests
├── Integration/            # Integration tests
│   ├── API/               # API integration tests
│   └── Database/          # Database integration tests
├── Feature/               # Feature tests
│   ├── Browser/           # Browser feature tests
│   └── UI/                # UI feature tests
├── Engines/               # Engine-specific tests
│   ├── PrismEngineTest.php
│   ├── ChromiumEngineTest.php
│   └── FirefoxEngineTest.php
├── bootstrap.php          # Test bootstrap file
└── fixtures/              # Test fixtures and data
```

## Running Tests

### Prerequisites

1. **Backend Dependencies**:
   ```bash
   cd backend
   composer install
   ```

2. **Frontend Dependencies**:
   ```bash
   cd frontend
   npm install
   ```

3. **System Requirements**:
   - PHP 8.1+
   - Node.js 18+
   - ChromeDriver (for Chromium tests)
   - GeckoDriver (for Firefox tests)

### Running All Tests

```bash
# From project root
./scripts/run-tests.sh

# Or with specific options
./scripts/run-tests.sh --verbose --coverage
```

### Running Specific Test Suites

```bash
# Backend tests only
./scripts/run-tests.sh backend

# Frontend tests only
./scripts/run-tests.sh frontend

# Integration tests only
./scripts/run-tests.sh integration

# Specific test file
cd backend
./vendor/bin/phpunit tests/Engines/PrismEngineTest.php
```

### Running Tests with Coverage

```bash
# Backend coverage
cd backend
./vendor/bin/phpunit --coverage-html coverage/html

# Frontend coverage
cd frontend
npm run test:coverage
```

## Test Configuration

### Backend (PHPUnit)

Configuration file: `backend/phpunit.xml`

Key settings:
- **Bootstrap**: `tests/bootstrap.php`
- **Test Suites**: Unit, Integration, Feature, Engines
- **Coverage**: HTML, Text, Clover reports
- **Environment**: Testing mode with in-memory database

### Frontend (Vitest)

Configuration file: `frontend/vitest.config.ts`

Key settings:
- **Test Environment**: jsdom
- **Coverage**: Istanbul
- **TypeScript**: Full support
- **React**: Testing Library integration

## Test Categories

### 1. Unit Tests

Test individual components in isolation:

```php
// Example: Service test
class CssParserServiceTest extends TestCase
{
    public function testParseBasicCss()
    {
        $service = new CssParserService();
        $result = $service->parse('body { color: red; }');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rules', $result);
    }
}
```

### 2. Integration Tests

Test component interactions:

```php
// Example: API integration test
class TabControllerTest extends TestCase
{
    public function testCreateTab()
    {
        $request = $this->createRequest('POST', '/api/tabs', [
            'url' => 'https://example.com',
            'title' => 'Test Tab'
        ]);
        
        $response = $this->controller->createTab($request, $response, []);
        
        $this->assertEquals(201, $response->getStatusCode());
    }
}
```

### 3. Engine Tests

Test browser engine functionality:

```php
// Example: Engine test
class PrismEngineTest extends TestCase
{
    public function testNavigation()
    {
        $engine = new PrismEngine($config);
        $engine->initialize();
        
        $engine->navigate('data:text/html,<html><body>Test</body></html>');
        
        $this->assertStringContains('Test', $engine->getPageContent());
    }
}
```

### 4. Feature Tests

Test complete user workflows:

```php
// Example: Browser feature test
class BrowserFeatureTest extends TestCase
{
    public function testCompleteBrowsingSession()
    {
        // Create tab
        $tab = $this->createTab('https://example.com');
        
        // Navigate
        $this->navigateTab($tab, 'https://google.com');
        
        // Execute script
        $result = $this->executeScript($tab, 'return document.title;');
        
        $this->assertStringContains('Google', $result);
    }
}
```

## Test Data and Fixtures

### Test Database

Tests use an in-memory SQLite database with the following tables:
- `bookmarks` - Bookmark storage
- `bookmark_folders` - Bookmark organization
- `history` - Browsing history
- `settings` - Application settings
- `downloads` - Download tracking
- `tabs` - Tab management
- `users` - User accounts
- `sessions` - User sessions

### Test Fixtures

Test fixtures are stored in `tests/fixtures/`:
- `html/` - HTML test files
- `css/` - CSS test files
- `js/` - JavaScript test files
- `images/` - Image test files
- `data/` - JSON test data

## Mocking and Stubbing

### Backend Mocking

```php
// Mock external services
$mockHttpClient = $this->createMock(HttpClientService::class);
$mockHttpClient->method('get')
    ->willReturn(['status' => 200, 'body' => 'test content']);

// Mock database
$mockDatabase = $this->createMock(DatabaseService::class);
$mockDatabase->method('query')
    ->willReturn(['id' => 'test-id', 'title' => 'Test']);
```

### Frontend Mocking

```typescript
// Mock API calls
vi.mock('../services/api', () => ({
  apiService: {
    getTabs: vi.fn().mockResolvedValue([
      { id: '1', title: 'Test Tab', url: 'https://example.com' }
    ])
  }
}));

// Mock browser APIs
Object.defineProperty(window, 'localStorage', {
  value: {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn()
  }
});
```

## Performance Testing

### Backend Performance

```php
// Example: Performance test
public function testEnginePerformance()
{
    $start = microtime(true);
    
    $engine = new PrismEngine($config);
    $engine->initialize();
    $engine->navigate('https://example.com');
    
    $end = microtime(true);
    $duration = $end - $start;
    
    $this->assertLessThan(5.0, $duration, 'Navigation should complete within 5 seconds');
}
```

### Frontend Performance

```typescript
// Example: Performance test
test('renders large tab list efficiently', async () => {
  const start = performance.now();
  
  render(<TabManager tabs={generateLargeTabList(1000)} />);
  
  const end = performance.now();
  const duration = end - start;
  
  expect(duration).toBeLessThan(100); // Should render within 100ms
});
```

## Security Testing

### Input Validation

```php
// Test SQL injection prevention
public function testSqlInjectionPrevention()
{
    $maliciousInput = "'; DROP TABLE users; --";
    
    $this->expectException(InvalidArgumentException::class);
    $this->controller->createTab($maliciousInput);
}
```

### XSS Prevention

```php
// Test XSS prevention
public function testXssPrevention()
{
    $xssInput = '<script>alert("XSS")</script>';
    
    $result = $this->sanitizeInput($xssInput);
    
    $this->assertStringNotContains('<script>', $result);
    $this->assertStringNotContains('alert', $result);
}
```

## Continuous Integration

### GitHub Actions

The project includes GitHub Actions workflows for:
- **Test Runner**: Runs all tests on push/PR
- **Coverage**: Generates and uploads coverage reports
- **Security**: Scans for vulnerabilities
- **Performance**: Runs performance benchmarks

### Test Reports

Test results are available in:
- **Console**: Real-time output during test execution
- **HTML**: `coverage/html/index.html`
- **JUnit**: `test-results/junit.xml`
- **Coverage**: `coverage/clover.xml`

## Debugging Tests

### Backend Debugging

```php
// Enable debug output
public function testWithDebugOutput()
{
    $this->markTestSkipped('Debug test');
    
    // Add debug output
    echo "Debug: " . print_r($data, true);
    
    // Use debugger
    xdebug_break();
}
```

### Frontend Debugging

```typescript
// Enable debug output
test('debug test', () => {
  console.log('Debug data:', data);
  
  // Use debugger
  debugger;
  
  expect(true).toBe(true);
});
```

## Best Practices

### 1. Test Naming

- Use descriptive test names
- Follow the pattern: `test[Method][Scenario][ExpectedResult]`
- Example: `testCreateTabWithValidDataReturnsSuccess`

### 2. Test Organization

- Group related tests in classes
- Use `setUp()` and `tearDown()` for common setup
- Keep tests independent and isolated

### 3. Test Data

- Use realistic test data
- Create reusable fixtures
- Clean up after tests

### 4. Assertions

- Use specific assertions
- Test both positive and negative cases
- Verify error conditions

### 5. Performance

- Keep tests fast
- Use mocks for slow operations
- Test performance-critical code

## Troubleshooting

### Common Issues

1. **ChromeDriver/GeckoDriver not found**:
   - Install WebDriver binaries
   - Add to PATH or specify full path

2. **Database connection errors**:
   - Check database configuration
   - Ensure test database is created

3. **Memory issues**:
   - Increase PHP memory limit
   - Clean up test data

4. **Timeout errors**:
   - Increase test timeouts
   - Check for infinite loops

### Getting Help

- Check test logs in `tests/logs/`
- Review test output for specific errors
- Consult PHPUnit/Vitest documentation
- Check project issues on GitHub

## Contributing

When adding new tests:

1. Follow existing patterns
2. Add appropriate test categories
3. Include both positive and negative cases
4. Update this documentation if needed
5. Ensure tests pass in CI

## Resources

- [PHPUnit Documentation](https://phpunit.readthedocs.io/)
- [Vitest Documentation](https://vitest.dev/)
- [React Testing Library](https://testing-library.com/docs/react-testing-library/intro/)
- [WebDriver Documentation](https://www.selenium.dev/documentation/webdriver/)

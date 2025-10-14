<?php

/**
 * Test Bootstrap File
 * 
 * This file is loaded before running tests to set up the testing environment.
 * It ensures that all necessary dependencies are loaded and the environment
 * is properly configured for testing.
 */

// Set error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Define test constants
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));
define('VENDOR_ROOT', PROJECT_ROOT . '/vendor');

// Ensure vendor autoloader is available
if (!file_exists(VENDOR_ROOT . '/autoload.php')) {
    throw new RuntimeException('Vendor autoloader not found. Please run "composer install" first.');
}

require_once VENDOR_ROOT . '/autoload.php';

// Set up test environment variables
$_ENV['APP_ENV'] = 'testing';
$_ENV['DATABASE_URL'] = 'sqlite::memory:';
$_ENV['LOG_LEVEL'] = 'debug';
$_ENV['CACHE_DRIVER'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';

// Create test directories if they don't exist
$testDirs = [
    PROJECT_ROOT . '/tests/tmp',
    PROJECT_ROOT . '/tests/fixtures',
    PROJECT_ROOT . '/tests/logs'
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set up test database
$testDbPath = PROJECT_ROOT . '/tests/tmp/test.db';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

// Configure PDO for testing
$pdo = new PDO('sqlite:' . $testDbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create test tables
$pdo->exec("
    CREATE TABLE IF NOT EXISTS bookmarks (
        id VARCHAR(36) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        url TEXT NOT NULL,
        folder_id VARCHAR(36),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS bookmark_folders (
        id VARCHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id VARCHAR(36),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS history (
        id VARCHAR(36) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        url TEXT NOT NULL,
        visit_count INTEGER DEFAULT 1,
        last_visited DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id VARCHAR(36) PRIMARY KEY,
        key VARCHAR(255) UNIQUE NOT NULL,
        value TEXT,
        category VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS downloads (
        id VARCHAR(36) PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        url TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        progress INTEGER DEFAULT 0,
        total_size INTEGER,
        downloaded_size INTEGER DEFAULT 0,
        file_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tabs (
        id VARCHAR(36) PRIMARY KEY,
        title VARCHAR(255),
        url TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(36) PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(36) PRIMARY KEY,
        user_id VARCHAR(36),
        token VARCHAR(255) UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Insert test data
$pdo->exec("
    INSERT INTO settings (id, key, value, category) VALUES 
    ('test-setting-1', 'browser.homepage', 'https://example.com', 'browser'),
    ('test-setting-2', 'browser.search_engine', 'https://google.com', 'browser'),
    ('test-setting-3', 'privacy.block_trackers', 'true', 'privacy'),
    ('test-setting-4', 'appearance.theme', 'light', 'appearance'),
    ('test-setting-5', 'performance.cache_enabled', 'true', 'performance')
");

// Clean up
$pdo = null;

// Set up test logging
$logger = new Monolog\Logger('test');
$logger->pushHandler(new Monolog\Handler\StreamHandler(PROJECT_ROOT . '/tests/logs/test.log', Monolog\Logger::DEBUG));

// Register test logger globally
$GLOBALS['test_logger'] = $logger;

// Set up error handler for tests
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Set up exception handler for tests
set_exception_handler(function ($exception) {
    echo "Uncaught exception: " . $exception->getMessage() . "\n";
    echo "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    exit(1);
});

// Clean up function for after tests
register_shutdown_function(function () {
    // Clean up test files
    $testFiles = glob(PROJECT_ROOT . '/tests/tmp/*');
    foreach ($testFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    // Clean up test directories
    $testDirs = [
        PROJECT_ROOT . '/tests/tmp',
        PROJECT_ROOT . '/tests/logs'
    ];
    
    foreach ($testDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
});

// Test helper functions
if (!function_exists('test_log')) {
    function test_log($message, $context = []) {
        global $test_logger;
        if (isset($test_logger)) {
            $test_logger->info($message, $context);
        }
    }
}

if (!function_exists('test_debug')) {
    function test_debug($message, $context = []) {
        global $test_logger;
        if (isset($test_logger)) {
            $test_logger->debug($message, $context);
        }
    }
}

if (!function_exists('test_error')) {
    function test_error($message, $context = []) {
        global $test_logger;
        if (isset($test_logger)) {
            $test_logger->error($message, $context);
        }
    }
}

// Output test environment info
if (php_sapi_name() === 'cli') {
    echo "Test environment initialized\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Test Root: " . TEST_ROOT . "\n";
    echo "Project Root: " . PROJECT_ROOT . "\n";
    echo "Vendor Root: " . VENDOR_ROOT . "\n";
    echo "Test Database: " . $testDbPath . "\n";
    echo "Log Level: " . ($_ENV['LOG_LEVEL'] ?? 'info') . "\n";
    echo "Environment: " . ($_ENV['APP_ENV'] ?? 'production') . "\n";
    echo "\n";
}

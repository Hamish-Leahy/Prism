<?php

namespace Prism\Backend\Services;

use PDO;
use PDOException;

class DatabaseService
{
    private PDO $pdo;
    private array $config;
    private ?SupabaseService $supabase = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Use Supabase if configured, otherwise fall back to local database
        if (isset($config['supabase']) && $config['supabase']['enabled']) {
            $this->supabase = new SupabaseService($config['supabase']);
            $this->pdo = $this->supabase->getPdo();
        } else {
            $this->connect();
            $this->initializeTables();
        }
    }

    private function connect(): void
    {
        try {
            if ($this->config['driver'] === 'sqlite') {
                $dsn = 'sqlite:' . $this->config['database'];
                $this->pdo = new PDO($dsn);
            } else {
                $dsn = sprintf(
                    '%s:host=%s;port=%d;dbname=%s;charset=%s',
                    $this->config['driver'],
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database'],
                    $this->config['charset']
                );
                $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password']);
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    private function initializeTables(): void
    {
        // Bookmarks table
        $sql = "
            CREATE TABLE IF NOT EXISTS bookmarks (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                favicon TEXT,
                folder_id VARCHAR(36),
                tags TEXT,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (folder_id) REFERENCES bookmark_folders(id) ON DELETE SET NULL
            )
        ";
        $this->pdo->exec($sql);

        // Bookmark folders table
        $sql = "
            CREATE TABLE IF NOT EXISTS bookmark_folders (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                parent_id VARCHAR(36),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES bookmark_folders(id) ON DELETE CASCADE
            )
        ";
        $this->pdo->exec($sql);

        // History table
        $sql = "
            CREATE TABLE IF NOT EXISTS history (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                visit_count INTEGER DEFAULT 1,
                engine_used VARCHAR(50),
                response_time INTEGER,
                content_type VARCHAR(100)
            )
        ";
        $this->pdo->exec($sql);

        // Settings table
        $sql = "
            CREATE TABLE IF NOT EXISTS settings (
                key VARCHAR(255) PRIMARY KEY,
                value TEXT,
                category VARCHAR(100) DEFAULT 'general',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);

        // Downloads table
        $sql = "
            CREATE TABLE IF NOT EXISTS downloads (
                id VARCHAR(36) PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                file_path TEXT,
                file_size BIGINT,
                downloaded_size BIGINT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME,
                error_message TEXT
            )
        ";
        $this->pdo->exec($sql);

        // Tabs table for persistence
        $sql = "
            CREATE TABLE IF NOT EXISTS tabs (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                is_active BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);

        // Users table for authentication
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(36) PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_verified BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                verification_token VARCHAR(64),
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);

        // Refresh tokens table
        $sql = "
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $this->pdo->exec($sql);

        // Password resets table
        $sql = "
            CREATE TABLE IF NOT EXISTS password_resets (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $this->pdo->exec($sql);

        // User settings table
        $sql = "
            CREATE TABLE IF NOT EXISTS user_settings (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                setting_key VARCHAR(255) NOT NULL,
                setting_value TEXT,
                category VARCHAR(100) DEFAULT 'general',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_setting (user_id, setting_key)
            )
        ";
        $this->pdo->exec($sql);

        // Sessions table (for session management)
        $sql = "
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                data TEXT,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        $this->pdo->exec($sql);

        // Create indexes for better performance
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_history_url ON history(url)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_history_visited_at ON history(visited_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookmarks_folder_id ON bookmarks(folder_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_downloads_status ON downloads(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at)");

        // Insert default settings
        $this->insertDefaultSettings();
    }

    private function insertDefaultSettings(): void
    {
        $defaultSettings = [
            ['browser.default_engine', 'prism', 'engine'],
            ['browser.homepage', 'about:blank', 'general'],
            ['browser.new_tab_page', 'about:blank', 'general'],
            ['privacy.block_trackers', 'true', 'privacy'],
            ['privacy.block_ads', 'true', 'privacy'],
            ['privacy.clear_data_on_exit', 'false', 'privacy'],
            ['appearance.theme', 'dark', 'appearance'],
            ['appearance.font_size', '14', 'appearance'],
            ['performance.cache_size', '100', 'performance'],
            ['security.https_only', 'false', 'security']
        ];

        foreach ($defaultSettings as $setting) {
            $this->pdo->exec("
                INSERT OR IGNORE INTO settings (key, value, category) 
                VALUES ('{$setting[0]}', '{$setting[1]}', '{$setting[2]}')
            ");
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        if ($this->supabase) {
            return $this->supabase->query($sql, $params);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        if ($this->supabase) {
            return $this->supabase->execute($sql, $params);
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastInsertId(): string
    {
        if ($this->supabase) {
            return $this->supabase->lastInsertId();
        }
        
        return $this->pdo->lastInsertId();
    }

    public function runMigration(string $migrationSql): bool
    {
        if ($this->supabase) {
            return $this->supabase->runMigration($migrationSql);
        }
        
        return $this->pdo->exec($migrationSql) !== false;
    }

    public function tableExists(string $tableName): bool
    {
        if ($this->supabase) {
            return $this->supabase->tableExists($tableName);
        }
        
        $result = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$tableName]
        );
        return !empty($result);
    }
}

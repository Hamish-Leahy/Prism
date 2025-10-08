<?php

namespace Prism\Backend\Services;

use PDO;
use PDOException;

class DatabaseService
{
    private PDO $pdo;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
        $this->initializeTables();
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
        $sql = "
            CREATE TABLE IF NOT EXISTS bookmarks (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                favicon TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);

        $sql = "
            CREATE TABLE IF NOT EXISTS history (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                visit_count INTEGER DEFAULT 1
            )
        ";
        $this->pdo->exec($sql);

        $sql = "
            CREATE TABLE IF NOT EXISTS settings (
                key VARCHAR(255) PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}

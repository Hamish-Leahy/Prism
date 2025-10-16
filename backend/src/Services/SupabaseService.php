<?php

namespace Prism\Backend\Services;

use PDO;
use PDOException;

class SupabaseService
{
    private PDO $pdo;
    private string $host;
    private string $database;
    private string $username;
    private string $password;
    private int $port;

    public function __construct(array $config)
    {
        $this->host = $config['host'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->port = $config['port'] ?? 5432;
        
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database}";
            $this->pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException("Failed to connect to Supabase: " . $e->getMessage());
        }
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new \RuntimeException("Database query failed: " . $e->getMessage());
        }
    }

    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database execution failed: " . $e->getMessage());
        }
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function runMigration(string $migrationSql): bool
    {
        try {
            $this->pdo->exec($migrationSql);
            return true;
        } catch (PDOException $e) {
            throw new \RuntimeException("Migration failed: " . $e->getMessage());
        }
    }

    public function tableExists(string $tableName): bool
    {
        $result = $this->query(
            "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)",
            [$tableName]
        );
        return $result[0]['exists'] ?? false;
    }

    public function getTableColumns(string $tableName): array
    {
        return $this->query(
            "SELECT column_name, data_type, is_nullable, column_default 
             FROM information_schema.columns 
             WHERE table_schema = 'public' AND table_name = ? 
             ORDER BY ordinal_position",
            [$tableName]
        );
    }
}

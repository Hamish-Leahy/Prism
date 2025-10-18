<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class SupabaseService
{
    private array $config;
    private Logger $logger;
    private ?\PDO $pdo = null;
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Supabase Service");
            
            // Check if Supabase is enabled
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Supabase Service disabled by configuration");
                return true;
            }

            // Validate required configuration
            if (empty($this->config['url']) || empty($this->config['key'])) {
                throw new \RuntimeException('Supabase URL and API key are required');
            }

            // Initialize database connection
            $this->initializeDatabase();
            
            $this->initialized = true;
            $this->logger->info("Supabase Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Supabase Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    private function initializeDatabase(): void
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 5432,
            $this->config['database'] ?? 'postgres'
        );

        $this->pdo = new \PDO(
            $dsn,
            $this->config['username'] ?? 'postgres',
            $this->config['password'] ?? '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }

    public function query(string $table, array $filters = [], array $options = []): array
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $sql = "SELECT * FROM {$table}";
            $params = [];

            if (!empty($filters)) {
                $whereClauses = [];
                foreach ($filters as $column => $value) {
                    $whereClauses[] = "{$column} = :{$column}";
                    $params[$column] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            if (isset($options['order_by'])) {
                $sql .= " ORDER BY {$options['order_by']}";
                if (isset($options['order_direction'])) {
                    $sql .= " " . strtoupper($options['order_direction']);
                }
            }

            if (isset($options['limit'])) {
                $sql .= " LIMIT " . (int)$options['limit'];
            }

            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            $this->logger->error("Supabase query failed", [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Query failed: " . $e->getMessage());
        }
    }

    public function insert(string $table, array $data): string
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $result = $stmt->fetch();
            return $result['id'];
        } catch (\Exception $e) {
            $this->logger->error("Supabase insert failed", [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Insert failed: " . $e->getMessage());
        }
    }

    public function update(string $table, array $data, array $filters): int
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $setClauses = [];
            $params = [];
            
            foreach ($data as $column => $value) {
                $setClauses[] = "{$column} = :set_{$column}";
                $params["set_{$column}"] = $value;
            }
            
            $whereClauses = [];
            foreach ($filters as $column => $value) {
                $whereClauses[] = "{$column} = :where_{$column}";
                $params["where_{$column}"] = $value;
            }
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $this->logger->error("Supabase update failed", [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Update failed: " . $e->getMessage());
        }
    }

    public function delete(string $table, array $filters): int
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $whereClauses = [];
            $params = [];
            
            foreach ($filters as $column => $value) {
                $whereClauses[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            
            $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClauses);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $this->logger->error("Supabase delete failed", [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Delete failed: " . $e->getMessage());
        }
    }

    public function executeRawQuery(string $sql, array $params = []): array
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            $this->logger->error("Supabase raw query failed", [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Raw query failed: " . $e->getMessage());
        }
    }

    public function beginTransaction(): bool
    {
        if (!$this->initialized) {
            return false;
        }

        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        if (!$this->initialized) {
            return false;
        }

        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        if (!$this->initialized) {
            return false;
        }

        return $this->pdo->rollBack();
    }

    public function getTableInfo(string $table): array
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Supabase Service not initialized');
        }

        try {
            $sql = "
                SELECT 
                    column_name,
                    data_type,
                    is_nullable,
                    column_default,
                    character_maximum_length
                FROM information_schema.columns 
                WHERE table_name = :table_name
                ORDER BY ordinal_position
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['table_name' => $table]);
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            $this->logger->error("Failed to get table info", [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to get table info: " . $e->getMessage());
        }
    }

    public function getStats(): array
    {
        if (!$this->initialized) {
            return [];
        }

        try {
            $tables = $this->executeRawQuery("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
            ");

            $tableCount = count($tables);
            $connectionStatus = $this->pdo !== null;

            return [
                'initialized' => $this->initialized,
                'connected' => $connectionStatus,
                'tables_count' => $tableCount,
                'url' => $this->config['url'] ?? '',
                'database' => $this->config['database'] ?? '',
                'host' => $this->config['host'] ?? 'localhost',
                'port' => $this->config['port'] ?? 5432
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to get Supabase stats", ['error' => $e->getMessage()]);
            return [
                'initialized' => $this->initialized,
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->pdo = null;
        $this->initialized = false;
        $this->logger->info("Supabase Service cleaned up");
    }
}
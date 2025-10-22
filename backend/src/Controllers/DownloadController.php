<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;
use Prism\Backend\Services\HttpClientService;
use Prism\Backend\Services\WebSocketService;
use Ramsey\Uuid\Uuid;
use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\ChildProcess\Process;

class DownloadController
{
    private DatabaseService $database;
    private HttpClientService $httpClient;
    private WebSocketService $webSocket;
    private Logger $logger;
    private LoopInterface $loop;
    private string $downloadPath;
    private array $activeDownloads = [];
    private array $downloadQueue = [];
    private int $maxConcurrentDownloads = 3;
    private array $downloadProcesses = [];

    public function __construct(
        DatabaseService $database, 
        HttpClientService $httpClient, 
        WebSocketService $webSocket,
        Logger $logger,
        LoopInterface $loop
    ) {
        $this->database = $database;
        $this->httpClient = $httpClient;
        $this->webSocket = $webSocket;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->downloadPath = $_ENV['DOWNLOAD_PATH'] ?? sys_get_temp_dir() . '/prism_downloads';
        
        // Create download directory if it doesn't exist
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0755, true);
        }

        // Start download queue processor
        $this->startQueueProcessor();
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $status = $queryParams['status'] ?? null;
            $limit = (int) ($queryParams['limit'] ?? 50);
            $offset = (int) ($queryParams['offset'] ?? 0);

            $sql = 'SELECT * FROM downloads';
            $params = [];

            if ($status) {
                $sql .= ' WHERE status = ?';
                $params[] = $status;
            }

            $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;

            $downloads = $this->database->query($sql, $params);

            $response->getBody()->write(json_encode($downloads));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch downloads']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['url'])) {
            $response->getBody()->write(json_encode(['error' => 'URL is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $id = Uuid::uuid4()->toString();
            $url = $data['url'];
            $filename = $data['filename'] ?? basename(parse_url($url, PHP_URL_PATH)) ?: 'download';

            // Get file info from URL
            $fileInfo = $this->getFileInfo($url);
            if ($fileInfo) {
                $filename = $fileInfo['filename'] ?? $filename;
                $fileSize = $fileInfo['size'] ?? null;
            } else {
                $fileSize = null;
            }

            // Ensure unique filename
            $filePath = $this->getUniqueFilePath($this->downloadPath, $filename);

            $this->database->execute(
                'INSERT INTO downloads (id, filename, url, file_path, file_size, status) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $filename, $url, $filePath, $fileSize, 'pending']
            );

            // Start download in background
            $this->startDownload($id, $url, $filePath);

            $download = [
                'id' => $id,
                'filename' => $filename,
                'url' => $url,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'downloaded_size' => 0,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $response->getBody()->write(json_encode($download));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to create download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $downloadId = $args['id'] ?? '';

        try {
            $downloads = $this->database->query(
                'SELECT * FROM downloads WHERE id = ?',
                [$downloadId]
            );

            if (empty($downloads)) {
                $response->getBody()->write(json_encode(['error' => 'Download not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($downloads[0]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function pause(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $downloadId = $args['id'] ?? '';

        try {
            $this->database->execute(
                'UPDATE downloads SET status = ? WHERE id = ? AND status = ?',
                ['paused', $downloadId, 'downloading']
            );

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to pause download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function resume(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $downloadId = $args['id'] ?? '';

        try {
            $downloads = $this->database->query(
                'SELECT * FROM downloads WHERE id = ?',
                [$downloadId]
            );

            if (empty($downloads)) {
                $response->getBody()->write(json_encode(['error' => 'Download not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $download = $downloads[0];
            
            if ($download['status'] !== 'paused') {
                $response->getBody()->write(json_encode(['error' => 'Download is not paused']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $this->database->execute(
                'UPDATE downloads SET status = ? WHERE id = ?',
                ['downloading', $downloadId]
            );

            // Resume download
            $this->startDownload($downloadId, $download['url'], $download['file_path']);

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to resume download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function cancel(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $downloadId = $args['id'] ?? '';

        try {
            $this->database->execute(
                'UPDATE downloads SET status = ?, error_message = ? WHERE id = ?',
                ['cancelled', 'Download cancelled by user', $downloadId]
            );

            // Clean up partial file
            $downloads = $this->database->query(
                'SELECT file_path FROM downloads WHERE id = ?',
                [$downloadId]
            );

            if (!empty($downloads) && file_exists($downloads[0]['file_path'])) {
                unlink($downloads[0]['file_path']);
            }

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to cancel download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $downloadId = $args['id'] ?? '';

        try {
            // Get file path before deletion
            $downloads = $this->database->query(
                'SELECT file_path FROM downloads WHERE id = ?',
                [$downloadId]
            );

            $this->database->execute(
                'DELETE FROM downloads WHERE id = ?',
                [$downloadId]
            );

            // Delete file if it exists
            if (!empty($downloads) && file_exists($downloads[0]['file_path'])) {
                unlink($downloads[0]['file_path']);
            }

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to delete download']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function getFileInfo(string $url): ?array
    {
        try {
            $response = $this->httpClient->head($url);
            $headers = $response['headers'] ?? [];

            $filename = null;
            if (isset($headers['content-disposition'])) {
                preg_match('/filename="?([^"]+)"?/', $headers['content-disposition'], $matches);
                if (isset($matches[1])) {
                    $filename = $matches[1];
                }
            }

            return [
                'filename' => $filename,
                'size' => isset($headers['content-length']) ? (int) $headers['content-length'] : null,
                'type' => $headers['content-type'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get file info for URL: ' . $url, ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function getUniqueFilePath(string $directory, string $filename): string
    {
        $filePath = $directory . '/' . $filename;
        $counter = 1;

        while (file_exists($filePath)) {
            $pathInfo = pathinfo($filename);
            $name = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $filePath = $directory . '/' . $name . '_' . $counter . $extension;
            $counter++;
        }

        return $filePath;
    }

    private function startDownload(string $id, string $url, string $filePath): void
    {
        // Add to download queue
        $this->downloadQueue[] = [
            'id' => $id,
            'url' => $url,
            'file_path' => $filePath,
            'priority' => 'normal',
            'created_at' => microtime(true)
        ];

        $this->logger->info('Download queued', ['id' => $id, 'url' => $url, 'file_path' => $filePath]);
        
        // Broadcast queue update
        $this->webSocket->broadcastDownloadUpdate($id, [
            'status' => 'queued',
            'queue_position' => count($this->downloadQueue)
        ]);
    }

    private function startQueueProcessor(): void
    {
        // Process queue every second
        $this->loop->addPeriodicTimer(1.0, function() {
            $this->processDownloadQueue();
        });

        // Update progress every 2 seconds
        $this->loop->addPeriodicTimer(2.0, function() {
            $this->updateDownloadProgress();
        });
    }

    private function processDownloadQueue(): void
    {
        // Check if we can start more downloads
        if (count($this->activeDownloads) >= $this->maxConcurrentDownloads) {
            return;
        }

        if (empty($this->downloadQueue)) {
            return;
        }

        // Get next download from queue
        $download = array_shift($this->downloadQueue);
        
        // Start the download
        $this->startConcurrentDownload($download);
    }

    private function startConcurrentDownload(array $download): void
    {
        $id = $download['id'];
        $url = $download['url'];
        $filePath = $download['file_path'];

        try {
            // Update database status
            $this->database->execute(
                'UPDATE downloads SET status = ?, started_at = ? WHERE id = ?',
                ['downloading', date('Y-m-d H:i:s'), $id]
            );

            // Add to active downloads
            $this->activeDownloads[$id] = [
                'id' => $id,
                'url' => $url,
                'file_path' => $filePath,
                'started_at' => microtime(true),
                'downloaded_bytes' => 0,
                'total_bytes' => 0,
                'speed' => 0,
                'eta' => 0
            ];

            // Start download process
            $this->downloadProcesses[$id] = $this->createDownloadProcess($id, $url, $filePath);

            $this->logger->info('Download started', ['id' => $id, 'url' => $url]);
            
            // Broadcast download start
            $this->webSocket->broadcastDownloadUpdate($id, [
                'status' => 'downloading',
                'started_at' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to start download', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            $this->database->execute(
                'UPDATE downloads SET status = ?, error_message = ? WHERE id = ?',
                ['failed', $e->getMessage(), $id]
            );

            $this->webSocket->broadcastDownloadUpdate($id, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createDownloadProcess(string $id, string $url, string $filePath): Process
    {
        // Create a PHP script to handle the download
        $script = $this->createDownloadScript($id, $url, $filePath);
        
        $process = new Process("php {$script}");
        
        $process->on('exit', function($exitCode) use ($id) {
            $this->handleDownloadComplete($id, $exitCode);
        });

        $process->on('data', function($data) use ($id) {
            $this->handleDownloadProgress($id, $data);
        });

        $process->start($this->loop);
        
        return $process;
    }

    private function createDownloadScript(string $id, string $url, string $filePath): string
    {
        $scriptPath = sys_get_temp_dir() . "/download_{$id}.php";
        
        $script = "<?php
require_once '{$_SERVER['DOCUMENT_ROOT']}/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

\$id = '{$id}';
\$url = '{$url}';
\$filePath = '{$filePath}';

try {
    \$client = new Client([
        'timeout' => 300,
        'connect_timeout' => 30,
        'stream' => true
    ]);

    \$response = \$client->get(\$url);
    \$totalBytes = \$response->getHeader('Content-Length')[0] ?? 0;
    \$downloadedBytes = 0;

    \$fileHandle = fopen(\$filePath, 'w');
    \$stream = \$response->getBody();

    while (!\$stream->eof()) {
        \$chunk = \$stream->read(8192);
        \$downloadedBytes += strlen(\$chunk);
        fwrite(\$fileHandle, \$chunk);
        
        // Output progress
        echo json_encode([
            'id' => \$id,
            'downloaded_bytes' => \$downloadedBytes,
            'total_bytes' => \$totalBytes,
            'progress' => \$totalBytes > 0 ? (\$downloadedBytes / \$totalBytes) * 100 : 0
        ]) . PHP_EOL;
    }

    fclose(\$fileHandle);
    echo json_encode(['id' => \$id, 'status' => 'completed']) . PHP_EOL;

} catch (Exception \$e) {
    echo json_encode(['id' => \$id, 'status' => 'error', 'message' => \$e->getMessage()]) . PHP_EOL;
    exit(1);
}
";

        file_put_contents($scriptPath, $script);
        return $scriptPath;
    }

    private function handleDownloadProgress(string $id, string $data): void
    {
        $lines = explode("\n", trim($data));
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $progress = json_decode($line, true);
            if (!$progress || !isset($progress['id'])) continue;
            
            if ($progress['id'] === $id && isset($this->activeDownloads[$id])) {
                $this->activeDownloads[$id]['downloaded_bytes'] = $progress['downloaded_bytes'];
                $this->activeDownloads[$id]['total_bytes'] = $progress['total_bytes'];
                
                // Calculate speed and ETA
                $elapsed = microtime(true) - $this->activeDownloads[$id]['started_at'];
                if ($elapsed > 0) {
                    $this->activeDownloads[$id]['speed'] = $progress['downloaded_bytes'] / $elapsed;
                    
                    if ($this->activeDownloads[$id]['speed'] > 0 && $progress['total_bytes'] > 0) {
                        $remainingBytes = $progress['total_bytes'] - $progress['downloaded_bytes'];
                        $this->activeDownloads[$id]['eta'] = $remainingBytes / $this->activeDownloads[$id]['speed'];
                    }
                }

                // Update database
                $this->database->execute(
                    'UPDATE downloads SET downloaded_size = ? WHERE id = ?',
                    [$progress['downloaded_bytes'], $id]
                );

                // Broadcast progress update
                $this->webSocket->broadcastDownloadUpdate($id, [
                    'status' => 'downloading',
                    'progress' => $progress['progress'],
                    'downloaded_bytes' => $progress['downloaded_bytes'],
                    'total_bytes' => $progress['total_bytes'],
                    'speed' => $this->activeDownloads[$id]['speed'],
                    'eta' => $this->activeDownloads[$id]['eta']
                ]);
            }
        }
    }

    private function handleDownloadComplete(string $id, int $exitCode): void
    {
        if (!isset($this->activeDownloads[$id])) {
            return;
        }

        $download = $this->activeDownloads[$id];
        
        if ($exitCode === 0) {
            // Download completed successfully
            $this->database->execute(
                'UPDATE downloads SET status = ?, completed_at = ? WHERE id = ?',
                ['completed', date('Y-m-d H:i:s'), $id]
            );

            $this->webSocket->broadcastDownloadUpdate($id, [
                'status' => 'completed',
                'completed_at' => date('c'),
                'file_path' => $download['file_path']
            ]);

            $this->logger->info('Download completed', ['id' => $id]);
        } else {
            // Download failed
            $this->database->execute(
                'UPDATE downloads SET status = ?, error_message = ? WHERE id = ?',
                ['failed', 'Download process exited with error', $id]
            );

            $this->webSocket->broadcastDownloadUpdate($id, [
                'status' => 'failed',
                'error' => 'Download process failed'
            ]);

            $this->logger->error('Download failed', ['id' => $id, 'exit_code' => $exitCode]);
        }

        // Clean up
        unset($this->activeDownloads[$id]);
        unset($this->downloadProcesses[$id]);
        
        // Clean up temporary script
        $scriptPath = sys_get_temp_dir() . "/download_{$id}.php";
        if (file_exists($scriptPath)) {
            unlink($scriptPath);
        }
    }

    private function updateDownloadProgress(): void
    {
        foreach ($this->activeDownloads as $id => $download) {
            // This method can be used for additional progress tracking
            // For now, the progress is handled by the download processes
        }
    }

    public function getDownloadStats(): array
    {
        return [
            'active_downloads' => count($this->activeDownloads),
            'queued_downloads' => count($this->downloadQueue),
            'max_concurrent' => $this->maxConcurrentDownloads,
            'downloads' => array_values($this->activeDownloads)
        ];
    }

    public function setMaxConcurrentDownloads(int $max): void
    {
        $this->maxConcurrentDownloads = max(1, min($max, 10)); // Limit between 1 and 10
        $this->logger->info('Max concurrent downloads updated', ['max' => $this->maxConcurrentDownloads]);
    }

    public function clearCompletedDownloads(): int
    {
        $cleared = 0;
        
        try {
            $completed = $this->database->query(
                'SELECT id, file_path FROM downloads WHERE status = ? AND completed_at < ?',
                ['completed', date('Y-m-d H:i:s', time() - 86400)] // Older than 24 hours
            );

            foreach ($completed as $download) {
                // Delete file if it exists
                if (file_exists($download['file_path'])) {
                    unlink($download['file_path']);
                }

                // Delete database record
                $this->database->execute('DELETE FROM downloads WHERE id = ?', [$download['id']]);
                $cleared++;
            }

            $this->logger->info('Cleared completed downloads', ['count' => $cleared]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear completed downloads', ['error' => $e->getMessage()]);
        }

        return $cleared;
    }
}

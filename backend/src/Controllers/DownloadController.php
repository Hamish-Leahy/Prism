<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;
use Prism\Backend\Services\HttpClientService;
use Ramsey\Uuid\Uuid;
use Monolog\Logger;

class DownloadController
{
    private DatabaseService $database;
    private HttpClientService $httpClient;
    private Logger $logger;
    private string $downloadPath;

    public function __construct(DatabaseService $database, HttpClientService $httpClient, Logger $logger)
    {
        $this->database = $database;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->downloadPath = $_ENV['DOWNLOAD_PATH'] ?? sys_get_temp_dir() . '/prism_downloads';
        
        // Create download directory if it doesn't exist
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0755, true);
        }
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
        // This would typically be handled by a background job queue
        // For now, we'll simulate the download process
        $this->database->execute(
            'UPDATE downloads SET status = ? WHERE id = ?',
            ['downloading', $id]
        );

        // In a real implementation, this would be handled by a background process
        // that downloads the file and updates the progress
        $this->logger->info('Starting download', ['id' => $id, 'url' => $url, 'file_path' => $filePath]);
    }
}

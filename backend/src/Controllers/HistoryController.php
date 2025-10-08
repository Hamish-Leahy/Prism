<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;
use Ramsey\Uuid\Uuid;

class HistoryController
{
    private DatabaseService $database;

    public function __construct(DatabaseService $database)
    {
        $this->database = $database;
    }

    public function list(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = (int) ($queryParams['limit'] ?? 100);
        $offset = (int) ($queryParams['offset'] ?? 0);

        try {
            $history = $this->database->query(
                'SELECT * FROM history ORDER BY visited_at DESC LIMIT ? OFFSET ?',
                [$limit, $offset]
            );

            $response->getBody()->write(json_encode($history));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch history']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function add(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['title']) || !isset($data['url'])) {
            $response->getBody()->write(json_encode(['error' => 'Title and URL are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $title = $data['title'];
            $url = $data['url'];

            // Check if URL already exists in history
            $existing = $this->database->query(
                'SELECT id, visit_count FROM history WHERE url = ? ORDER BY visited_at DESC LIMIT 1',
                [$url]
            );

            if (!empty($existing)) {
                // Update existing entry
                $id = $existing[0]['id'];
                $visitCount = $existing[0]['visit_count'] + 1;
                
                $this->database->execute(
                    'UPDATE history SET title = ?, visit_count = ?, visited_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$title, $visitCount, $id]
                );
            } else {
                // Create new entry
                $id = Uuid::uuid4()->toString();
                
                $this->database->execute(
                    'INSERT INTO history (id, title, url, visit_count) VALUES (?, ?, ?, 1)',
                    [$id, $title, $url]
                );
            }

            $historyEntry = [
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'visited_at' => date('Y-m-d H:i:s'),
                'visit_count' => $visitCount ?? 1
            ];

            $response->getBody()->write(json_encode($historyEntry));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to add history entry']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $historyId = $args['id'] ?? '';

        try {
            $this->database->execute(
                'DELETE FROM history WHERE id = ?',
                [$historyId]
            );

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to delete history entry']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function clear(Request $request, Response $response): Response
    {
        try {
            $this->database->execute('DELETE FROM history');

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to clear history']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}

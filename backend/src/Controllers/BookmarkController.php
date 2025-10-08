<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;
use Ramsey\Uuid\Uuid;

class BookmarkController
{
    private DatabaseService $database;

    public function __construct(DatabaseService $database)
    {
        $this->database = $database;
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $bookmarks = $this->database->query(
                'SELECT * FROM bookmarks ORDER BY created_at DESC'
            );

            $response->getBody()->write(json_encode($bookmarks));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch bookmarks']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['title']) || !isset($data['url'])) {
            $response->getBody()->write(json_encode(['error' => 'Title and URL are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $id = Uuid::uuid4()->toString();
            $title = $data['title'];
            $url = $data['url'];
            $favicon = $data['favicon'] ?? null;

            $this->database->execute(
                'INSERT INTO bookmarks (id, title, url, favicon) VALUES (?, ?, ?, ?)',
                [$id, $title, $url, $favicon]
            );

            $bookmark = [
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'favicon' => $favicon,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $response->getBody()->write(json_encode($bookmark));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to create bookmark']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $bookmarkId = $args['id'] ?? '';

        try {
            $bookmarks = $this->database->query(
                'SELECT * FROM bookmarks WHERE id = ?',
                [$bookmarkId]
            );

            if (empty($bookmarks)) {
                $response->getBody()->write(json_encode(['error' => 'Bookmark not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($bookmarks[0]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch bookmark']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $bookmarkId = $args['id'] ?? '';
        $data = json_decode($request->getBody()->getContents(), true);

        try {
            $updates = [];
            $params = [$bookmarkId];

            if (isset($data['title'])) {
                $updates[] = 'title = ?';
                $params[] = $data['title'];
            }

            if (isset($data['url'])) {
                $updates[] = 'url = ?';
                $params[] = $data['url'];
            }

            if (isset($data['favicon'])) {
                $updates[] = 'favicon = ?';
                $params[] = $data['favicon'];
            }

            if (empty($updates)) {
                $response->getBody()->write(json_encode(['error' => 'No updates provided']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $updates[] = 'updated_at = CURRENT_TIMESTAMP';
            $sql = 'UPDATE bookmarks SET ' . implode(', ', $updates) . ' WHERE id = ?';
            
            $this->database->execute($sql, $params);

            $bookmarks = $this->database->query(
                'SELECT * FROM bookmarks WHERE id = ?',
                [$bookmarkId]
            );

            $response->getBody()->write(json_encode($bookmarks[0]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to update bookmark']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $bookmarkId = $args['id'] ?? '';

        try {
            $this->database->execute(
                'DELETE FROM bookmarks WHERE id = ?',
                [$bookmarkId]
            );

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Failed to delete bookmark']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}

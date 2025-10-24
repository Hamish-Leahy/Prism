<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\PrismSearchEngine;

class SearchController
{
    private PrismSearchEngine $searchEngine;

    public function __construct(PrismSearchEngine $searchEngine)
    {
        $this->searchEngine = $searchEngine;
    }

    /**
     * Perform a search
     */
    public function search(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $query = $params['q'] ?? $params['query'] ?? '';

            if (empty($query)) {
                throw new \InvalidArgumentException('Search query is required');
            }

            $options = [
                'max_results' => (int)($params['max_results'] ?? 20),
                'include_web' => ($params['include_web'] ?? 'true') === 'true',
                'include_suggestions' => ($params['include_suggestions'] ?? 'true') === 'true'
            ];

            $result = $this->searchEngine->search($query, $options);
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Index a page
     */
    public function indexPage(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
            $url = $data['url'] ?? '';
            $title = $data['title'] ?? '';
            $content = $data['content'] ?? '';
            $metadata = $data['metadata'] ?? [];

            if (empty($url) || empty($title)) {
                throw new \InvalidArgumentException('URL and title are required');
            }

            $result = $this->searchEngine->indexPage($url, $title, $content, $metadata);
            
            $response->getBody()->write(json_encode([
                'success' => $result,
                'message' => $result ? 'Page indexed successfully' : 'Failed to index page'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get search statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        try {
            $stats = $this->searchEngine->getStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'stats' => $stats
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Clear search index
     */
    public function clearIndex(Request $request, Response $response): Response
    {
        try {
            $result = $this->searchEngine->clearIndex();
            
            $response->getBody()->write(json_encode([
                'success' => $result,
                'message' => 'Search index cleared'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}


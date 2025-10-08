<?php

namespace Prism\Backend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CorsMiddleware
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (!$this->config['enabled']) {
            return $handler->handle($request);
        }

        $response = $handler->handle($request);

        $origin = $request->getHeaderLine('Origin');
        if (in_array($origin, $this->config['origins']) || in_array('*', $this->config['origins'])) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->config['methods']));
        $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->config['headers']));
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(200);
        }

        return $response;
    }
}

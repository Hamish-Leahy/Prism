<?php

namespace Prism\Backend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Monolog\Logger;

class LoggingMiddleware
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $startTime = microtime(true);
        
        $this->logger->info('Request started', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'headers' => $request->getHeaders()
        ]);

        $response = $handler->handle($request);

        $duration = microtime(true) - $startTime;
        
        $this->logger->info('Request completed', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms'
        ]);

        return $response;
    }
}

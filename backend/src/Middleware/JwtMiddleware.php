<?php

namespace Prism\Backend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Prism\Backend\Services\AuthenticationService;

class JwtMiddleware
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Authorization header missing or invalid'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];
        $result = $this->authService->verifyToken($token);
        
        if (!$result['success']) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode($result));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Add user information to request attributes
        $request = $request->withAttribute('user', $result['user']);
        $request = $request->withAttribute('user_id', $result['user']['id']);

        return $handler->handle($request);
    }
}

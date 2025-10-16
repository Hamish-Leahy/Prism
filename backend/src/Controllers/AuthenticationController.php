<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\AuthenticationService;

class AuthenticationController
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     */
    public function register(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Username, email, and password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->register(
            $data['username'],
            $data['email'],
            $data['password']
        );

        $statusCode = $result['success'] ? 201 : 400;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Login user
     */
    public function login(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Username/email and password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->login(
            $data['username'],
            $data['password']
        );

        $statusCode = $result['success'] ? 200 : 401;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Verify JWT token
     */
    public function verify(Request $request, Response $response): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Authorization header missing or invalid'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];
        $result = $this->authService->verifyToken($token);

        $statusCode = $result['success'] ? 200 : 401;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['refresh_token'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Refresh token is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->refreshToken($data['refresh_token']);

        $statusCode = $result['success'] ? 200 : 401;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Logout user
     */
    public function logout(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['refresh_token'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Refresh token is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $success = $this->authService->logout($data['refresh_token']);
        
        $response->getBody()->write(json_encode([
            'success' => $success,
            'message' => $success ? 'Logged out successfully' : 'Logout failed'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request, Response $response): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Authorization header missing or invalid'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];
        $tokenResult = $this->authService->verifyToken($token);
        
        if (!$tokenResult['success']) {
            $response->getBody()->write(json_encode($tokenResult));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Current password and new password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->changePassword(
            $tokenResult['user']['id'],
            $data['current_password'],
            $data['new_password']
        );

        $statusCode = $result['success'] ? 200 : 400;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['email'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Email is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->requestPasswordReset($data['email']);

        $statusCode = $result['success'] ? 200 : 400;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['token']) || !isset($data['new_password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Token and new password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = $this->authService->resetPassword(
            $data['token'],
            $data['new_password']
        );

        $statusCode = $result['success'] ? 200 : 400;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request, Response $response): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Authorization header missing or invalid'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];
        $result = $this->authService->verifyToken($token);

        $statusCode = $result['success'] ? 200 : 401;
        $response->getBody()->write(json_encode($result));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }
}

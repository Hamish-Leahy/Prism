<?php

namespace Prism\Backend\Tests\Services;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\AuthenticationService;
use PDO;
use PDOException;

class AuthenticationServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthenticationService $authService;
    private string $testDbPath;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->testDbPath = ':memory:';
        $this->pdo = new PDO('sqlite:' . $this->testDbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables
        $this->createTestTables();
        
        $this->authService = new AuthenticationService(
            $this->pdo,
            'test-secret-key',
            3600, // 1 hour
            604800 // 7 days
        );
    }

    private function createTestTables(): void
    {
        // Users table
        $this->pdo->exec("
            CREATE TABLE users (
                id VARCHAR(36) PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_verified BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                verification_token VARCHAR(64),
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Refresh tokens table
        $this->pdo->exec("
            CREATE TABLE refresh_tokens (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Password resets table
        $this->pdo->exec("
            CREATE TABLE password_resets (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // User settings table
        $this->pdo->exec("
            CREATE TABLE user_settings (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                setting_key VARCHAR(255) NOT NULL,
                setting_value TEXT,
                category VARCHAR(100) DEFAULT 'general',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_setting (user_id, setting_key)
            )
        ");
    }

    public function testUserRegistration(): void
    {
        $result = $this->authService->register(
            'testuser',
            'test@example.com',
            'password123'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('verification_token', $result);
    }

    public function testUserRegistrationWithInvalidEmail(): void
    {
        $result = $this->authService->register(
            'testuser',
            'invalid-email',
            'password123'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid email format', $result['error']);
    }

    public function testUserRegistrationWithShortPassword(): void
    {
        $result = $this->authService->register(
            'testuser',
            'test@example.com',
            '123'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Password must be at least 8 characters long', $result['error']);
    }

    public function testUserRegistrationWithDuplicateUsername(): void
    {
        // Register first user
        $this->authService->register('testuser', 'test1@example.com', 'password123');
        
        // Try to register with same username
        $result = $this->authService->register('testuser', 'test2@example.com', 'password123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Username or email already exists', $result['error']);
    }

    public function testUserLogin(): void
    {
        // First register a user
        $registerResult = $this->authService->register(
            'testuser',
            'test@example.com',
            'password123'
        );
        
        $this->assertTrue($registerResult['success']);
        
        // Manually verify the user (in real app, this would be done via email)
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        // Now try to login
        $result = $this->authService->login('testuser', 'password123');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('tokens', $result);
        $this->assertEquals('testuser', $result['user']['username']);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    public function testUserLoginWithInvalidCredentials(): void
    {
        $result = $this->authService->login('nonexistent', 'wrongpassword');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['error']);
    }

    public function testTokenVerification(): void
    {
        // Register and login to get a token
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        $loginResult = $this->authService->login('testuser', 'password123');
        $accessToken = $loginResult['tokens']['access_token'];
        
        // Verify the token
        $result = $this->authService->verifyToken($accessToken);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('testuser', $result['user']['username']);
    }

    public function testTokenVerificationWithInvalidToken(): void
    {
        $result = $this->authService->verifyToken('invalid-token');
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testTokenRefresh(): void
    {
        // Register and login to get tokens
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        $loginResult = $this->authService->login('testuser', 'password123');
        $refreshToken = $loginResult['tokens']['refresh_token'];
        
        // Refresh the token
        $result = $this->authService->refreshToken($refreshToken);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tokens', $result);
        $this->assertArrayHasKey('access_token', $result['tokens']);
        $this->assertArrayHasKey('refresh_token', $result['tokens']);
    }

    public function testTokenRefreshWithInvalidToken(): void
    {
        $result = $this->authService->refreshToken('invalid-refresh-token');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired refresh token', $result['error']);
    }

    public function testPasswordChange(): void
    {
        // Register and login
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        $loginResult = $this->authService->login('testuser', 'password123');
        $userId = $loginResult['user']['id'];
        
        // Change password
        $result = $this->authService->changePassword($userId, 'password123', 'newpassword123');
        
        $this->assertTrue($result['success']);
        
        // Try to login with old password (should fail)
        $loginResult = $this->authService->login('testuser', 'password123');
        $this->assertFalse($loginResult['success']);
        
        // Try to login with new password (should succeed)
        $loginResult = $this->authService->login('testuser', 'newpassword123');
        $this->assertTrue($loginResult['success']);
    }

    public function testPasswordChangeWithWrongCurrentPassword(): void
    {
        // Register and login
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        $loginResult = $this->authService->login('testuser', 'password123');
        $userId = $loginResult['user']['id'];
        
        // Try to change password with wrong current password
        $result = $this->authService->changePassword($userId, 'wrongpassword', 'newpassword123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Current password is incorrect', $result['error']);
    }

    public function testPasswordResetRequest(): void
    {
        // Register a user
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        // Request password reset
        $result = $this->authService->requestPasswordReset('test@example.com');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('reset_token', $result);
    }

    public function testPasswordResetWithToken(): void
    {
        // Register a user
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        // Request password reset
        $resetResult = $this->authService->requestPasswordReset('test@example.com');
        $resetToken = $resetResult['reset_token'];
        
        // Reset password with token
        $result = $this->authService->resetPassword($resetToken, 'newpassword123');
        
        $this->assertTrue($result['success']);
        
        // Try to login with old password (should fail)
        $loginResult = $this->authService->login('testuser', 'password123');
        $this->assertFalse($loginResult['success']);
        
        // Try to login with new password (should succeed)
        $loginResult = $this->authService->login('testuser', 'newpassword123');
        $this->assertTrue($loginResult['success']);
    }

    public function testPasswordResetWithInvalidToken(): void
    {
        $result = $this->authService->resetPassword('invalid-token', 'newpassword123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired reset token', $result['error']);
    }

    public function testLogout(): void
    {
        // Register and login to get refresh token
        $registerResult = $this->authService->register('testuser', 'test@example.com', 'password123');
        $this->pdo->exec("UPDATE users SET is_verified = 1 WHERE id = '{$registerResult['user_id']}'");
        
        $loginResult = $this->authService->login('testuser', 'password123');
        $refreshToken = $loginResult['tokens']['refresh_token'];
        
        // Logout
        $result = $this->authService->logout($refreshToken);
        
        $this->assertTrue($result);
        
        // Try to refresh token (should fail)
        $refreshResult = $this->authService->refreshToken($refreshToken);
        $this->assertFalse($refreshResult['success']);
    }

    protected function tearDown(): void
    {
        // Clean up
        $this->pdo = null;
    }
}

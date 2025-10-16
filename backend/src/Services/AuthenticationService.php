<?php

namespace Prism\Backend\Services;

use PDO;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Ramsey\Uuid\Uuid;

class AuthenticationService
{
    private PDO $pdo;
    private string $jwtSecret;
    private int $jwtExpiration;
    private int $refreshTokenExpiration;

    public function __construct(PDO $pdo, string $jwtSecret, int $jwtExpiration = 3600, int $refreshTokenExpiration = 604800)
    {
        $this->pdo = $pdo;
        $this->jwtSecret = $jwtSecret;
        $this->jwtExpiration = $jwtExpiration;
        $this->refreshTokenExpiration = $refreshTokenExpiration;
    }

    /**
     * Register a new user
     */
    public function register(string $username, string $email, string $password): array
    {
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'All fields are required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        // Check if user already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username or email already exists'];
        }

        try {
            $userId = Uuid::uuid4()->toString();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = bin2hex(random_bytes(32));

            $stmt = $this->pdo->prepare("
                INSERT INTO users (id, username, email, password_hash, verification_token, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $username, $email, $passwordHash, $verificationToken]);

            // Create user settings
            $this->createDefaultUserSettings($userId);

            return [
                'success' => true,
                'user_id' => $userId,
                'verification_token' => $verificationToken
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     */
    public function login(string $usernameOrEmail, string $password): array
    {
        if (empty($usernameOrEmail) || empty($password)) {
            return ['success' => false, 'error' => 'Username/email and password are required'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, is_verified 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            if (!$user['is_verified']) {
                return ['success' => false, 'error' => 'Account not verified. Please check your email.'];
            }

            // Generate tokens
            $tokens = $this->generateTokens($user['id']);

            // Update last login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ],
                'tokens' => $tokens
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Login failed: ' . $e->getMessage()];
        }
    }

    /**
     * Verify JWT token
     */
    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Check if user still exists and is active
            $stmt = $this->pdo->prepare("SELECT id, username, email FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$decoded->user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'User not found or inactive'];
            }

            return [
                'success' => true,
                'user' => $user,
                'expires_at' => $decoded->exp
            ];
        } catch (ExpiredException $e) {
            return ['success' => false, 'error' => 'Token expired'];
        } catch (SignatureInvalidException $e) {
            return ['success' => false, 'error' => 'Invalid token signature'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Token verification failed'];
        }
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.username, u.email 
                FROM users u 
                JOIN refresh_tokens rt ON u.id = rt.user_id 
                WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$refreshToken]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'Invalid or expired refresh token'];
            }

            // Generate new tokens
            $tokens = $this->generateTokens($user['id']);

            // Delete old refresh token
            $stmt = $this->pdo->prepare("DELETE FROM refresh_tokens WHERE token = ?");
            $stmt->execute([$refreshToken]);

            return [
                'success' => true,
                'user' => $user,
                'tokens' => $tokens
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Token refresh failed: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user
     */
    public function logout(string $refreshToken): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM refresh_tokens WHERE token = ?");
            return $stmt->execute([$refreshToken]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Change password
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters long'];
        }

        try {
            // Verify current password
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newPasswordHash, $userId]);

            // Invalidate all refresh tokens
            $stmt = $this->pdo->prepare("DELETE FROM refresh_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Password change failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reset password request
     */
    public function requestPasswordReset(string $email): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal if email exists
                return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
            }

            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()
            ");
            $stmt->execute([$user['id'], $resetToken, $expiresAt, $resetToken, $expiresAt]);

            // TODO: Send email with reset link
            // For now, return the token (in production, this should be sent via email)
            return [
                'success' => true,
                'message' => 'Password reset link sent',
                'reset_token' => $resetToken // Remove this in production
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Password reset request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id FROM password_resets 
                WHERE token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if (!$reset) {
                return ['success' => false, 'error' => 'Invalid or expired reset token'];
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newPasswordHash, $reset['user_id']]);

            // Delete reset token
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            // Invalidate all refresh tokens
            $stmt = $this->pdo->prepare("DELETE FROM refresh_tokens WHERE user_id = ?");
            $stmt->execute([$reset['user_id']]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Password reset failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate JWT and refresh tokens
     */
    private function generateTokens(string $userId): array
    {
        $now = time();
        
        // Access token
        $accessTokenPayload = [
            'user_id' => $userId,
            'iat' => $now,
            'exp' => $now + $this->jwtExpiration,
            'type' => 'access'
        ];
        $accessToken = JWT::encode($accessTokenPayload, $this->jwtSecret, 'HS256');

        // Refresh token
        $refreshToken = bin2hex(random_bytes(32));
        $refreshExpiresAt = date('Y-m-d H:i:s', $now + $this->refreshTokenExpiration);

        // Store refresh token
        $stmt = $this->pdo->prepare("
            INSERT INTO refresh_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $refreshToken, $refreshExpiresAt]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->jwtExpiration
        ];
    }

    /**
     * Create default settings for new user
     */
    private function createDefaultUserSettings(string $userId): void
    {
        $defaultSettings = [
            ['browser.default_engine', 'prism', 'engine'],
            ['browser.homepage', 'about:blank', 'general'],
            ['privacy.block_trackers', 'true', 'privacy'],
            ['appearance.theme', 'dark', 'appearance']
        ];

        foreach ($defaultSettings as $setting) {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value, category, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $setting[0], $setting[1], $setting[2]]);
        }
    }
}

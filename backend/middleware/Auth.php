<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class Auth {
    private static ?array $currentUser = null;
    private static ?string $currentToken = null;

    /**
     * Authenticate request from Authorization: Bearer <token> header.
     * Dies with JSON error if not authenticated.
     */
    public static function authenticate(): array {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Unauthorized: No token provided', 401);
            exit;
        }

        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        $session = db()->fetchOne(
            "SELECT s.*, u.id as user_id, u.email, u.role, u.is_active
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.session_token_hash = ? AND s.is_active = 1 AND s.expires_at > ?",
            [$tokenHash, $now]
        );

        if (!$session) {
            Response::error('Unauthorized: Invalid or expired session', 401);
            exit;
        }

        if (!$session['is_active']) {
            Response::error('Unauthorized: Account is deactivated', 401);
            exit;
        }

        // Refresh session expiry on each request
        db()->execute(
            "UPDATE user_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE session_token_hash = ?",
            [SESSION_TIMEOUT, $tokenHash]
        );

        self::$currentUser = $session;
        self::$currentToken = $tokenHash;
        return $session;
    }

    /**
     * Require specific role(s). Call after authenticate().
     */
    public static function authorize(array $roles): array {
        $user = self::$currentUser ?? self::authenticate();
        if (!in_array($user['role'], $roles, true)) {
            Response::error('Forbidden: Insufficient permissions', 403);
            exit;
        }
        return $user;
    }

    /**
     * Create a new session for a user. Returns plain token.
     */
    public static function createSession(int $userId, string $ip, string $userAgent): string {
        $token = bin2hex(random_bytes(32)); // 64 char hex token
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

        db()->insert(
            "INSERT INTO user_sessions (user_id, session_token_hash, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $tokenHash, $ip, substr($userAgent, 0, 500), $expiresAt]
        );

        return $token;
    }

    /**
     * Invalidate the current session token.
     */
    public static function destroySession(string $tokenHash): void {
        db()->execute(
            "UPDATE user_sessions SET is_active = 0 WHERE session_token_hash = ?",
            [$tokenHash]
        );
    }

    /**
     * Get currently authenticated user (must call authenticate first).
     */
    public static function user(): ?array {
        return self::$currentUser;
    }

    public static function userId(): ?int {
        return self::$currentUser ? (int)self::$currentUser['user_id'] : null;
    }

    public static function currentToken(): ?string {
        return self::$currentToken;
    }

    private static function extractToken(): ?string {
        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
        }
        if (!empty($_REQUEST['token'])) {
            return trim($_REQUEST['token']);
        }
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/Bearer\s+(\S+)/', $header, $m)) {
            return $m[1];
        }
        return $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    }
}

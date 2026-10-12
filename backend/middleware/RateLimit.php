<?php
require_once __DIR__ . '/../config/database.php';

class RateLimit {
    /**
     * Check rate limit. Returns true if under limit, false if exceeded.
     * @param string $key Unique identifier (e.g., 'login_ip_192.168.1.1')
     * @param int $limit Max requests in window
     * @param int $windowSeconds Time window in seconds
     */
    public static function check(string $key, int $limit, int $windowSeconds): bool {
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

        $existing = db()->fetchOne(
            "SELECT id, request_count FROM rate_limit_log
             WHERE limit_key = ? AND window_start > ?
             ORDER BY window_start DESC LIMIT 1",
            [$key, $windowStart]
        );

        if (!$existing) {
            db()->insert(
                "INSERT INTO rate_limit_log (limit_key, request_count, window_start) VALUES (?, 1, NOW())",
                [$key]
            );
            return true;
        }

        if ($existing['request_count'] >= $limit) {
            return false;
        }

        db()->execute(
            "UPDATE rate_limit_log SET request_count = request_count + 1 WHERE id = ?",
            [$existing['id']]
        );
        return true;
    }

    public static function getClientIP(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

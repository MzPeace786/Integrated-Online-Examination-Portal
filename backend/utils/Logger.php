<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Logger {
    public static function audit(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'success',
        ?string $ip = null
    ): void {
        try {
            db()->insert(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $oldValues ? json_encode($oldValues) : null,
                    $newValues ? json_encode($newValues) : null,
                    $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    $status,
                ]
            );
        } catch (Throwable $e) {
            self::file('ERROR', 'Failed to write audit log: ' . $e->getMessage());
        }
    }

    public static function security(
        ?int $userId,
        string $eventType,
        array $details = [],
        ?string $ip = null
    ): void {
        try {
            db()->insert(
                "INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $userId,
                    $eventType,
                    $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    json_encode($details),
                ]
            );
        } catch (Throwable $e) {
            self::file('ERROR', 'Failed to write security log: ' . $e->getMessage());
        }
    }

    public static function file(string $level, string $message, array $context = []): void {
        $logFile = LOG_PATH . date('Y-m-d') . '.log';
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $line = sprintf("[%s] [%s] %s%s\n", date('Y-m-d H:i:s'), $level, $message, $contextStr);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void {
        self::file('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::file('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::file('ERROR', $message, $context);
    }
}

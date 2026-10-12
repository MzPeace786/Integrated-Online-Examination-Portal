<?php
class PasswordHelper {
    private const BCRYPT_COST = 12;

    public static function hash(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    public static function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function generateResetToken(): string {
        return bin2hex(random_bytes(32)); // 64 character secure token
    }

    public static function hashToken(string $token): string {
        return hash('sha256', $token);
    }

    public static function validateStrength(string $password): array {
        $errors = [];
        if (strlen($password) < 8)        $errors[] = 'At least 8 characters required';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'At least one uppercase letter required';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'At least one lowercase letter required';
        if (!preg_match('/\d/', $password))    $errors[] = 'At least one number required';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'At least one special character required';
        return $errors;
    }

    public static function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }
}

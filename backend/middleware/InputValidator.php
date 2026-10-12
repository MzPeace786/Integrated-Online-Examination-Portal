<?php
class InputValidator {

    public static function sanitizeString(string $input, int $maxLen = 1000): string {
        $input = strip_tags(trim($input));
        return mb_substr($input, 0, $maxLen);
    }

    public static function sanitizeHTML(string $input): string {
        // Allow basic formatting tags only
        return strip_tags($input, '<b><i><u><strong><em><br><p><ul><ol><li><h3><h4><h5><pre><code>');
    }

    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 255;
    }

    public static function validatePassword(string $password): array {
        $errors = [];
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain at least one lowercase letter';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password must contain at least one special character';
        return $errors;
    }

    public static function validateInt($val, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int {
        if (!is_numeric($val)) return null;
        $int = (int)$val;
        return ($int >= $min && $int <= $max) ? $int : null;
    }

    public static function validateFloat($val, float $min = 0.0, float $max = PHP_FLOAT_MAX): ?float {
        if (!is_numeric($val)) return null;
        $f = (float)$val;
        return ($f >= $min && $f <= $max) ? $f : null;
    }

    public static function validateEnum($val, array $allowed): bool {
        return in_array($val, $allowed, true);
    }

    public static function validateDatetime(string $val): bool {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $val);
        if (!$d) $d = DateTime::createFromFormat('Y-m-d\TH:i', $val);
        return $d !== false;
    }

    public static function required(array $data, array $fields): array {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    public static function getRequestBody(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $body = $_POST;
        }
        return $body;
    }
}

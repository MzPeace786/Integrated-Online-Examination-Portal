<?php
class Response {
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $code = 400, array $errors = []): void {
        http_response_code($code);
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if (!empty($errors)) $response['errors'] = $errors;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function paginated(array $data, int $total, int $page, int $perPage, string $message = 'Success'): void {
        http_response_code(200);
        echo json_encode([
            'success'     => true,
            'message'     => $message,
            'data'        => $data,
            'pagination'  => [
                'total'      => $total,
                'page'       => $page,
                'per_page'   => $perPage,
                'last_page'  => (int)ceil($total / $perPage),
                'from'       => (($page - 1) * $perPage) + 1,
                'to'         => min($page * $perPage, $total),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): void {
        self::success($data, $message, 201);
    }

    public static function noContent(): void {
        http_response_code(204);
        exit;
    }
}

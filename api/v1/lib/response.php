<?php

function api_json_response(int $statusCode, bool $success, string $code, string $message, array $data = []): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

function api_bad_request(string $message, string $code = 'bad_request'): void
{
    api_json_response(400, false, $code, $message);
}

function api_unauthorized(string $message = 'Unauthorized'): void
{
    api_json_response(401, false, 'unauthorized', $message);
}

function api_server_error(string $message = 'Server error'): void
{
    api_json_response(500, false, 'server_error', $message);
}

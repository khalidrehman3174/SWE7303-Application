<?php

// Ensure API responses stay JSON even if included files echo whitespace/warnings.
if (ob_get_level() === 0) {
    ob_start();
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/deposit_repo.php';
require_once __DIR__ . '/stripe_provider.php';
require_once __DIR__ . '/deposit_service.php';

$apiConfig = api_config();
if (!empty($apiConfig['api_allowed_origin'])) {
    header('Access-Control-Allow-Origin: ' . $apiConfig['api_allowed_origin']);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Idempotency-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_get_request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_get_authenticated_user_id(): int
{
    if (!isset($_SESSION['user_id'])) {
        api_unauthorized('Please log in');
    }

    return (int)$_SESSION['user_id'];
}

function api_get_idempotency_key(): string
{
    $header = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '';
    if ($header !== '') {
        return substr(trim($header), 0, 64);
    }

    return bin2hex(random_bytes(16));
}

deposit_repo_ensure_schema($dbc);

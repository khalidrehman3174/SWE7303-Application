<?php

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_bad_request('Only POST is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$body = api_get_request_json();
$method = isset($body['method']) ? (string)$body['method'] : '';
$amount = isset($body['amount']) ? (float)$body['amount'] : 0.0;
$idempotencyKey = api_get_idempotency_key();

$result = deposit_service_create($dbc, $userId, $method, $amount, $idempotencyKey);

if (!$result['ok']) {
    api_json_response(422, false, $result['code'], $result['message']);
}

api_json_response(200, true, 'deposit_created', 'Deposit created successfully', [
    'deposit' => $result['deposit'],
    'provider' => $result['provider'] ?? null,
    'idempotent' => $result['idempotent'] ?? false,
]);

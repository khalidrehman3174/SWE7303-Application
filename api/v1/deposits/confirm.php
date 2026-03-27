<?php

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_bad_request('Only POST is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$body = api_get_request_json();
$depositId = isset($body['deposit_id']) ? trim((string)$body['deposit_id']) : '';

if ($depositId === '') {
    api_bad_request('deposit_id is required', 'missing_deposit_id');
}

$result = deposit_service_confirm_card($dbc, $userId, $depositId);
if (!$result['ok']) {
    api_json_response(422, false, $result['code'], $result['message']);
}

api_json_response(200, true, 'deposit_confirmed', 'Deposit confirmed', [
    'deposit' => $result['deposit'],
    'already_completed' => $result['already_completed'] ?? false,
]);

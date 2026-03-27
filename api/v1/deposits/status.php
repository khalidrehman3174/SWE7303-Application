<?php

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_bad_request('Only GET is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$depositId = isset($_GET['deposit_id']) ? trim((string)$_GET['deposit_id']) : '';

if ($depositId === '') {
    api_bad_request('deposit_id is required', 'missing_deposit_id');
}

$result = deposit_service_get_status($dbc, $userId, $depositId);
if (!$result['ok']) {
    api_json_response(404, false, $result['code'], $result['message']);
}

api_json_response(200, true, 'deposit_status', 'Deposit status fetched', [
    'deposit' => $result['deposit'],
]);

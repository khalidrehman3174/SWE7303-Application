<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/crypto_withdrawal_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_bad_request('Only GET is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$reference = isset($_GET['reference']) ? (string)$_GET['reference'] : '';

$result = crypto_withdrawal_service_get_status($dbc, $userId, $reference);
if (!$result['ok']) {
    $statusCode = (string)$result['code'] === 'not_found' ? 404 : 422;
    api_json_response($statusCode, false, (string)$result['code'], (string)$result['message']);
}

api_json_response(200, true, 'withdrawal_status_loaded', 'Withdrawal status loaded', [
    'withdrawal' => $result['withdrawal'],
]);

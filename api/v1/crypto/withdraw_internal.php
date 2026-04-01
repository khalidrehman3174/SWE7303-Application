<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/crypto_withdrawal_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_bad_request('Only POST is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$body = api_get_request_json();

$asset = isset($body['asset']) ? (string)$body['asset'] : '';
$network = isset($body['network']) ? (string)$body['network'] : '';
$address = isset($body['address']) ? (string)$body['address'] : '';
$amount = isset($body['amount']) ? (float)$body['amount'] : 0.0;

$result = crypto_withdrawal_service_internal_debit($dbc, $userId, $asset, $network, $address, $amount);

if (!$result['ok']) {
    api_json_response(422, false, (string)$result['code'], (string)$result['message']);
}

api_json_response(200, true, 'crypto_withdrawal_submitted', 'Withdrawal submitted for processing', [
    'withdrawal' => $result['withdrawal'],
    'balance' => $result['balance'],
]);

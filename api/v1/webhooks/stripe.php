<?php

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_bad_request('Only POST is supported', 'invalid_method');
}

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
$config = api_config();

try {
    $event = stripe_provider_parse_webhook($payload, $signature, $config);
} catch (Throwable $e) {
    api_json_response(400, false, 'invalid_signature', 'Invalid webhook signature');
}

$eventType = $event->type ?? '';
$eventId = $event->id ?? '';
$intentId = $event->data->object->id ?? null;

if (empty($eventId)) {
    api_json_response(400, false, 'invalid_event', 'Missing event id');
}

$existing = deposit_repo_find_webhook_event($dbc, $eventId);
if ($existing) {
    api_json_response(200, true, 'already_processed', 'Event already processed');
}

$payloadJson = json_encode($event);
if ($payloadJson === false) {
    $payloadJson = '{}';
}

if (!deposit_repo_create_webhook_event($dbc, $eventId, (string)$eventType, $intentId, $payloadJson)) {
    api_json_response(422, false, 'event_store_failed', 'Could not store webhook event');
}

if ($eventType !== 'payment_intent.succeeded' || empty($intentId)) {
    deposit_repo_mark_webhook_processed($dbc, $eventId);
    api_json_response(200, true, 'ignored_event', 'Event ignored');
}

$deposit = deposit_repo_find_by_external_reference($dbc, $intentId);
if (!$deposit) {
    api_json_response(404, false, 'deposit_not_found', 'No deposit matches this event');
}

$result = deposit_service_settle_completed($dbc, (string)$deposit['deposit_id']);
if (!$result['ok']) {
    api_json_response(422, false, $result['code'], $result['message']);
}

deposit_repo_mark_webhook_processed($dbc, $eventId);

api_json_response(200, true, 'webhook_processed', 'Webhook processed', [
    'deposit' => $result['deposit'],
]);

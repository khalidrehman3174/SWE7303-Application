<?php

function deposit_service_create(mysqli $dbc, int $userId, string $method, float $amount, string $idempotencyKey): array
{
    $config = api_config();
    $method = strtolower(trim($method));

    if (!in_array($method, ['bank', 'card', 'apple'], true)) {
        return ['ok' => false, 'code' => 'invalid_method', 'message' => 'Invalid deposit method'];
    }

    if ($amount <= 0 || $amount > (float)$config['max_deposit_amount']) {
        return ['ok' => false, 'code' => 'invalid_amount', 'message' => 'Invalid deposit amount'];
    }

    $existing = deposit_repo_find_by_user_and_key($dbc, $userId, $idempotencyKey);
    if ($existing) {
        return ['ok' => true, 'deposit' => $existing, 'idempotent' => true];
    }

    $depositId = 'dep_' . bin2hex(random_bytes(12));
    $status = in_array($method, ['card', 'bank'], true) ? 'pending_provider' : 'completed';

    $row = [
        'deposit_id' => $depositId,
        'user_id' => $userId,
        'method' => $method,
        'currency' => $config['default_currency'],
        'amount' => $amount,
        'fee_amount' => 0.00,
        'net_amount' => $amount,
        'status' => $status,
        'provider' => 'sandbox',
        'external_reference' => null,
        'idempotency_key' => $idempotencyKey,
        'metadata' => ['sandbox' => true],
    ];

    if (!deposit_repo_create($dbc, $row)) {
        return ['ok' => false, 'code' => 'create_failed', 'message' => 'Could not create deposit'];
    }

    $created = deposit_repo_find_by_deposit_id($dbc, $depositId);
    if (!$created) {
        return ['ok' => false, 'code' => 'create_failed', 'message' => 'Deposit not found after create'];
    }

    if ($method === 'card') {
        $providerResult = stripe_provider_create_payment_intent($config, $depositId, $userId, $amount);
        if (!$providerResult['ok']) {
            deposit_repo_update_status($dbc, $depositId, 'failed');
            return ['ok' => false, 'code' => 'provider_error', 'message' => 'Card provider init failed'];
        }

        if (!empty($providerResult['external_reference'])) {
            deposit_repo_set_external_reference($dbc, $depositId, $providerResult['external_reference'], $providerResult['provider']);
        }

        $created = deposit_repo_find_by_deposit_id($dbc, $depositId);
        return [
            'ok' => true,
            'deposit' => $created,
            'provider' => [
                'mode' => $providerResult['mode'],
                'client_secret' => $providerResult['client_secret'],
                'external_reference' => $providerResult['external_reference'],
            ],
        ];
    }

    if ($method === 'apple') {
        $settled = deposit_service_settle_completed($dbc, $depositId);
        if (!$settled['ok']) {
            return $settled;
        }

        return ['ok' => true, 'deposit' => $settled['deposit'], 'provider' => ['mode' => 'sandbox']];
    }

    return ['ok' => true, 'deposit' => $created, 'provider' => ['mode' => 'sandbox']];
}

function deposit_service_get_status(mysqli $dbc, int $userId, string $depositId): array
{
    $deposit = deposit_repo_find_by_deposit_id($dbc, $depositId);
    if (!$deposit || (int)$deposit['user_id'] !== $userId) {
        return ['ok' => false, 'code' => 'not_found', 'message' => 'Deposit not found'];
    }

    return ['ok' => true, 'deposit' => $deposit];
}

function deposit_service_confirm_card(mysqli $dbc, int $userId, string $depositId): array
{
    $deposit = deposit_repo_find_by_deposit_id($dbc, $depositId);
    if (!$deposit || (int)$deposit['user_id'] !== $userId) {
        return ['ok' => false, 'code' => 'not_found', 'message' => 'Deposit not found'];
    }

    if ($deposit['method'] !== 'card') {
        return ['ok' => false, 'code' => 'invalid_method', 'message' => 'Only card deposits can be confirmed'];
    }

    if ($deposit['status'] === 'completed') {
        return ['ok' => true, 'deposit' => $deposit, 'already_completed' => true];
    }

    if (!in_array($deposit['status'], ['pending_provider', 'pending_webhook'], true)) {
        return ['ok' => false, 'code' => 'invalid_state', 'message' => 'Deposit is not confirmable'];
    }

    // Real Stripe card deposits must be settled by verified webhook only.
    if (($deposit['provider'] ?? '') === 'stripe' && !empty($deposit['external_reference'])) {
        return ['ok' => false, 'code' => 'webhook_required', 'message' => 'Awaiting Stripe webhook confirmation'];
    }

    return deposit_service_settle_completed($dbc, $depositId);
}

function deposit_service_retry(mysqli $dbc, int $userId, string $depositId): array
{
    $deposit = deposit_repo_find_by_deposit_id($dbc, $depositId);
    if (!$deposit || (int)$deposit['user_id'] !== $userId) {
        return ['ok' => false, 'code' => 'not_found', 'message' => 'Deposit not found'];
    }

    if (!in_array($deposit['status'], ['failed', 'expired'], true)) {
        return ['ok' => false, 'code' => 'invalid_state', 'message' => 'Only failed or expired deposits can be retried'];
    }

    if (!deposit_repo_update_status($dbc, $depositId, 'pending_provider')) {
        return ['ok' => false, 'code' => 'retry_failed', 'message' => 'Could not retry deposit'];
    }

    $updated = deposit_repo_find_by_deposit_id($dbc, $depositId);
    return ['ok' => true, 'deposit' => $updated];
}

function deposit_service_settle_completed(mysqli $dbc, string $depositId): array
{
    $deposit = deposit_repo_find_by_deposit_id($dbc, $depositId);
    if (!$deposit) {
        return ['ok' => false, 'code' => 'not_found', 'message' => 'Deposit not found'];
    }

    if ($deposit['status'] === 'completed') {
        return ['ok' => true, 'deposit' => $deposit, 'already_completed' => true];
    }

    mysqli_begin_transaction($dbc);

    try {
        $config = api_config();
        $walletSymbol = $config['default_wallet_symbol'];
        $userId = (int)$deposit['user_id'];
        $amount = (float)$deposit['net_amount'];

        $wallet = deposit_repo_find_wallet($dbc, $userId, $walletSymbol);
        if (!$wallet) {
            if (!deposit_repo_create_wallet($dbc, $userId, $walletSymbol)) {
                throw new RuntimeException('Could not create wallet');
            }
            $wallet = deposit_repo_find_wallet($dbc, $userId, $walletSymbol);
        }

        if (!$wallet || !deposit_repo_increment_wallet($dbc, (int)$wallet['id'], $amount)) {
            throw new RuntimeException('Could not credit wallet');
        }

        if (!deposit_repo_update_status($dbc, $depositId, 'completed', date('Y-m-d H:i:s'))) {
            throw new RuntimeException('Could not set deposit completed');
        }

        mysqli_commit($dbc);

        $updated = deposit_repo_find_by_deposit_id($dbc, $depositId);
        return ['ok' => true, 'deposit' => $updated];
    } catch (Throwable $e) {
        mysqli_rollback($dbc);
        return ['ok' => false, 'code' => 'settlement_failed', 'message' => 'Deposit settlement failed'];
    }
}

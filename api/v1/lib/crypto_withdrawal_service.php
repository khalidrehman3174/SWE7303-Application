<?php

function crypto_withdrawal_service_normalize_asset(string $asset): string
{
    $normalized = strtoupper(trim($asset));
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);
    if ($normalized === null) {
        return '';
    }

    return substr($normalized, 0, 10);
}

function crypto_withdrawal_service_tracking_txid(string $reference, string $symbol): string
{
    return hash('sha256', $reference . '|' . strtoupper($symbol));
}

function crypto_withdrawal_service_get_status(mysqli $dbc, int $userId, string $reference): array
{
    if ($userId <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_user',
            'message' => 'Invalid user.',
        ];
    }

    $ref = trim($reference);
    if ($ref === '' || strlen($ref) > 120) {
        return [
            'ok' => false,
            'code' => 'invalid_reference',
            'message' => 'Invalid withdrawal reference.',
        ];
    }

    $stmt = mysqli_prepare(
        $dbc,
        'SELECT id, amount, currency, status, method, reference, completed_at
         FROM withdrawals
         WHERE user_id = ? AND reference = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    if (!$stmt) {
        return [
            'ok' => false,
            'code' => 'lookup_failed',
            'message' => 'Could not load withdrawal status.',
        ];
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $ref);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return [
            'ok' => false,
            'code' => 'not_found',
            'message' => 'Withdrawal request was not found.',
        ];
    }

    $asset = strtoupper((string)($row['currency'] ?? ''));
    $network = 'processing';
    $referenceValue = (string)($row['reference'] ?? $ref);

    return [
        'ok' => true,
        'code' => 'withdrawal_status_loaded',
        'message' => 'Withdrawal status loaded.',
        'withdrawal' => [
            'id' => (int)($row['id'] ?? 0),
            'reference' => $referenceValue,
            'asset' => $asset,
            'amount' => number_format((float)($row['amount'] ?? 0), 8, '.', ''),
            'amount_display' => rtrim(rtrim(number_format((float)($row['amount'] ?? 0), 8, '.', ''), '0'), '.'),
            'status' => (string)($row['status'] ?? 'processing'),
            'method' => (string)($row['method'] ?? 'crypto_internal_debit'),
            'completed_at' => (string)($row['completed_at'] ?? ''),
            'network' => $network,
            'tracking_txid' => crypto_withdrawal_service_tracking_txid($referenceValue, $asset),
            'eta' => '5-20 minutes',
        ],
    ];
}

function crypto_withdrawal_service_internal_debit(
    mysqli $dbc,
    int $userId,
    string $asset,
    string $network,
    string $destinationAddress,
    float $amountRaw
): array {
    if ($userId <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_user',
            'message' => 'Invalid user for withdrawal.',
        ];
    }

    $symbol = crypto_withdrawal_service_normalize_asset($asset);
    if ($symbol === '' || $symbol === 'GBP') {
        return [
            'ok' => false,
            'code' => 'invalid_asset',
            'message' => 'Select a valid crypto asset to withdraw.',
        ];
    }

    $network = strtolower(trim($network));
    $network = preg_replace('/[^a-z0-9_\-]/', '', $network) ?: '';
    if ($network === '') {
        return [
            'ok' => false,
            'code' => 'invalid_network',
            'message' => 'Select a valid network for withdrawal.',
        ];
    }

    $address = trim($destinationAddress);
    if ($address === '' || strlen($address) < 12 || strlen($address) > 190) {
        return [
            'ok' => false,
            'code' => 'invalid_address',
            'message' => 'Enter a valid destination wallet address.',
        ];
    }

    $amount = round((float)$amountRaw, 8);
    if ($amount <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_amount',
            'message' => 'Enter a valid amount greater than zero.',
        ];
    }

    if ($amount > 100000000) {
        return [
            'ok' => false,
            'code' => 'amount_too_large',
            'message' => 'Amount exceeds allowed internal debit limit.',
        ];
    }

    $shortAddress = strlen($address) > 18
        ? (substr($address, 0, 10) . '...' . substr($address, -6))
        : $address;
    $description = sprintf('Withdrawal request to %s via %s is processing', $shortAddress, strtoupper($network));
    $reference = 'CW-' . $userId . '-' . strtoupper($symbol) . '-' . bin2hex(random_bytes(6));
    $trackingTxid = crypto_withdrawal_service_tracking_txid($reference, $symbol);

    mysqli_begin_transaction($dbc);

    try {
        $walletStmt = mysqli_prepare(
            $dbc,
            'SELECT id, balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1 FOR UPDATE'
        );
        if (!$walletStmt) {
            throw new RuntimeException('wallet_lookup_prepare_failed');
        }

        mysqli_stmt_bind_param($walletStmt, 'is', $userId, $symbol);
        mysqli_stmt_execute($walletStmt);
        $walletResult = mysqli_stmt_get_result($walletStmt);
        $walletRow = $walletResult ? mysqli_fetch_assoc($walletResult) : null;
        mysqli_stmt_close($walletStmt);

        if (!$walletRow) {
            mysqli_rollback($dbc);
            return [
                'ok' => false,
                'code' => 'wallet_not_found',
                'message' => 'No wallet found for this asset.',
            ];
        }

        $walletId = (int)($walletRow['id'] ?? 0);
        $walletBalance = (float)($walletRow['balance'] ?? 0.0);
        if ($walletId <= 0 || $walletBalance < $amount) {
            mysqli_rollback($dbc);
            return [
                'ok' => false,
                'code' => 'insufficient_balance',
                'message' => 'Insufficient balance for this withdrawal.',
            ];
        }

        $debitStmt = mysqli_prepare($dbc, 'UPDATE wallets SET balance = balance - ? WHERE id = ? LIMIT 1');
        if (!$debitStmt) {
            throw new RuntimeException('wallet_debit_prepare_failed');
        }

        mysqli_stmt_bind_param($debitStmt, 'di', $amount, $walletId);
        mysqli_stmt_execute($debitStmt);
        mysqli_stmt_close($debitStmt);

        $txType = 'crypto_withdrawal_internal';
        $status = 'processing';
        $txAmount = 0 - $amount;
        $txStmt = mysqli_prepare(
            $dbc,
            'INSERT INTO transactions (user_id, type, symbol, amount, status, description) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$txStmt) {
            throw new RuntimeException('transaction_insert_prepare_failed');
        }

        mysqli_stmt_bind_param($txStmt, 'issdss', $userId, $txType, $symbol, $txAmount, $status, $description);
        mysqli_stmt_execute($txStmt);
        mysqli_stmt_close($txStmt);

        $completedAt = null;
        $method = 'crypto_internal_debit';
        $withdrawStmt = mysqli_prepare(
            $dbc,
            'INSERT INTO withdrawals (user_id, amount, currency, status, method, reference, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$withdrawStmt) {
            throw new RuntimeException('withdrawal_insert_prepare_failed');
        }

        mysqli_stmt_bind_param($withdrawStmt, 'idsssss', $userId, $amount, $symbol, $status, $method, $reference, $completedAt);
        mysqli_stmt_execute($withdrawStmt);
        mysqli_stmt_close($withdrawStmt);

        $balanceStmt = mysqli_prepare($dbc, 'SELECT balance FROM wallets WHERE id = ? LIMIT 1');
        if (!$balanceStmt) {
            throw new RuntimeException('wallet_balance_reload_prepare_failed');
        }

        mysqli_stmt_bind_param($balanceStmt, 'i', $walletId);
        mysqli_stmt_execute($balanceStmt);
        $balanceResult = mysqli_stmt_get_result($balanceStmt);
        $balanceRow = $balanceResult ? mysqli_fetch_assoc($balanceResult) : null;
        mysqli_stmt_close($balanceStmt);

        $newBalance = (float)($balanceRow['balance'] ?? ($walletBalance - $amount));

        mysqli_commit($dbc);

        return [
            'ok' => true,
            'code' => 'crypto_withdrawal_submitted',
            'message' => 'Withdrawal submitted and queued for processing.',
            'withdrawal' => [
                'reference' => $reference,
                'asset' => $symbol,
                'network' => $network,
                'address' => $address,
                'amount' => number_format($amount, 8, '.', ''),
                'amount_display' => rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.'),
                'status' => $status,
                'submitted_at' => date('c'),
                'tracking_txid' => $trackingTxid,
                'eta' => '5-20 minutes',
            ],
            'balance' => [
                'asset' => $symbol,
                'amount' => $newBalance,
                'formatted' => number_format($newBalance, 8, '.', ''),
            ],
        ];
    } catch (Throwable $e) {
        mysqli_rollback($dbc);
        return [
            'ok' => false,
            'code' => 'withdrawal_failed',
            'message' => 'Could not process internal withdrawal debit. Please try again.',
        ];
    }
}

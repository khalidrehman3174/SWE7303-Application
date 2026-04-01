<?php

function finpay_balance_table_exists(mysqli $dbc, string $table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }

    $result = mysqli_query($dbc, "SHOW TABLES LIKE '{$safeTable}'");
    return $result && mysqli_num_rows($result) > 0;
}

function finpay_balance_table_columns(mysqli $dbc, string $table): array
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return [];
    }

    $columns = [];
    $result = mysqli_query($dbc, "SHOW COLUMNS FROM {$safeTable}");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = (string)($row['Field'] ?? '');
        }
    }

    return $columns;
}

function finpay_balance_first_existing_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function finpay_balance_sum_completed_gbp_for_table(mysqli $dbc, int $userId, string $table, array $columnMap): float
{
    if (!finpay_balance_table_exists($dbc, $table)) {
        return 0.0;
    }

    $columns = finpay_balance_table_columns($dbc, $table);
    if (empty($columns)) {
        return 0.0;
    }

    $userCol = finpay_balance_first_existing_column($columns, $columnMap['user']);
    $statusCol = finpay_balance_first_existing_column($columns, $columnMap['status']);
    $currencyCol = finpay_balance_first_existing_column($columns, $columnMap['currency']);
    $amountCol = finpay_balance_first_existing_column($columns, $columnMap['amount']);
    $methodCol = finpay_balance_first_existing_column($columns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);

    if ($userCol === null || $statusCol === null || $currencyCol === null || $amountCol === null) {
        return 0.0;
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $swapExclusionSql = '';
    if ($methodCol !== null) {
        $swapExclusionSql = "
              AND LOWER(CAST({$methodCol} AS CHAR)) NOT IN ('swap','internal_swap','swap_internal','swap_in','swap_out','conversion','asset_swap')";
    }

    $sql = "SELECT COALESCE(SUM({$amountCol}), 0) AS total
            FROM {$safeTable}
            WHERE {$userCol} = ?
              AND LOWER({$statusCol}) = 'completed'
              AND UPPER({$currencyCol}) = 'GBP'" . $swapExclusionSql;

    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (float)($row['total'] ?? 0.0);
}

function finpay_balance_sum_contact_payments_delta(mysqli $dbc, int $userId): float
{
    if (!finpay_balance_table_exists($dbc, 'payment_contact_transactions')) {
        return 0.0;
    }

    $sent = 0.0;
    $received = 0.0;

    $sentStmt = mysqli_prepare(
        $dbc,
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM payment_contact_transactions
         WHERE user_id = ? AND direction = 'sent'"
    );
    if ($sentStmt) {
        mysqli_stmt_bind_param($sentStmt, 'i', $userId);
        mysqli_stmt_execute($sentStmt);
        $sentResult = mysqli_stmt_get_result($sentStmt);
        $sentRow = $sentResult ? mysqli_fetch_assoc($sentResult) : null;
        mysqli_stmt_close($sentStmt);
        $sent = (float)($sentRow['total'] ?? 0.0);
    }

    $receivedStmt = mysqli_prepare(
        $dbc,
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM payment_contact_transactions
         WHERE user_id = ? AND direction = 'received'"
    );
    if ($receivedStmt) {
        mysqli_stmt_bind_param($receivedStmt, 'i', $userId);
        mysqli_stmt_execute($receivedStmt);
        $receivedResult = mysqli_stmt_get_result($receivedStmt);
        $receivedRow = $receivedResult ? mysqli_fetch_assoc($receivedResult) : null;
        mysqli_stmt_close($receivedStmt);
        $received = (float)($receivedRow['total'] ?? 0.0);
    }

    return $sent - $received;
}

function finpay_balance_sum_contact_payment_withdrawals(mysqli $dbc, int $userId): float
{
    if (!finpay_balance_table_exists($dbc, 'withdrawals')) {
        return 0.0;
    }

    $columns = finpay_balance_table_columns($dbc, 'withdrawals');
    if (empty($columns)) {
        return 0.0;
    }

    $userCol = finpay_balance_first_existing_column($columns, ['user_id']);
    $statusCol = finpay_balance_first_existing_column($columns, ['status', 'state']);
    $currencyCol = finpay_balance_first_existing_column($columns, ['currency', 'fiat_currency', 'asset']);
    $amountCol = finpay_balance_first_existing_column($columns, ['net_amount', 'amount', 'withdrawal_amount']);
    $methodCol = finpay_balance_first_existing_column($columns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);

    if ($userCol === null || $statusCol === null || $currencyCol === null || $amountCol === null || $methodCol === null) {
        return 0.0;
    }

    $sql = "SELECT COALESCE(SUM({$amountCol}), 0) AS total
            FROM withdrawals
            WHERE {$userCol} = ?
              AND LOWER({$statusCol}) = 'completed'
              AND UPPER({$currencyCol}) = 'GBP'
              AND LOWER(CAST({$methodCol} AS CHAR)) = 'contact_payment'";

    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (float)($row['total'] ?? 0.0);
}

function finpay_balance_format_payload(float $amount, string $source, ?array $components = null): array
{
    $amount = (float)$amount;
    $absFormatted = number_format(abs($amount), 2, '.', ',');
    $parts = explode('.', $absFormatted);
    $major = $parts[0] ?? '0';
    $minor = $parts[1] ?? '00';
    $sign = $amount < 0 ? '-' : '';

    $payload = [
        'amount' => $amount,
        'sign' => $sign,
        'major' => $major,
        'minor' => $minor,
        'formatted' => $sign . $major . '.' . $minor,
        'currency' => 'GBP',
        'source' => $source,
    ];

    if ($components !== null) {
        $payload['components'] = $components;
    }

    return $payload;
}

function finpay_get_available_balance_gbp(mysqli $dbc, int $userId): array
{
    if ($userId <= 0) {
        return finpay_balance_format_payload(0.0, 'none');
    }

    $walletAmount = null;
    if (finpay_balance_table_exists($dbc, 'wallets')) {
        $stmt = mysqli_prepare($dbc, 'SELECT balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1');
        if ($stmt) {
            $symbol = 'GBP';
            mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($row && isset($row['balance'])) {
                $walletAmount = (float)$row['balance'];
            }
        }
    }

    $deposits = finpay_balance_sum_completed_gbp_for_table($dbc, $userId, 'deposits', [
        'user' => ['user_id'],
        'status' => ['status'],
        'currency' => ['currency'],
        'amount' => ['net_amount', 'amount'],
    ]);

    $withdrawals = 0.0;
    foreach (['withdrawals', 'fiat_withdrawals', 'withdrawal_requests'] as $withdrawalTable) {
        $tableTotal = finpay_balance_sum_completed_gbp_for_table($dbc, $userId, $withdrawalTable, [
            'user' => ['user_id'],
            'status' => ['status', 'state'],
            'currency' => ['currency', 'fiat_currency', 'asset'],
            'amount' => ['net_amount', 'amount', 'withdrawal_amount'],
        ]);
        $withdrawals += abs($tableTotal);
    }

    $contactPaymentDelta = finpay_balance_sum_contact_payments_delta($dbc, $userId);
    $contactPaymentWithdrawals = finpay_balance_sum_contact_payment_withdrawals($dbc, $userId);
    $contactAdjustment = max(0.0, $contactPaymentDelta - $contactPaymentWithdrawals);

    $ledgerAmount = $deposits - $withdrawals - $contactAdjustment;

    if ($walletAmount !== null) {
        $resolvedAmount = max($walletAmount, $ledgerAmount);
        $source = (abs($walletAmount - $ledgerAmount) < 0.00001) ? 'wallet' : 'wallet_reconciled';
        return finpay_balance_format_payload($resolvedAmount, $source, [
            'wallet' => $walletAmount,
            'ledger' => $ledgerAmount,
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'contact_adjustment' => $contactAdjustment,
        ]);
    }

    return finpay_balance_format_payload($ledgerAmount, 'ledger', [
        'deposits' => $deposits,
        'withdrawals' => $withdrawals,
        'contact_adjustment' => $contactAdjustment,
    ]);
}

<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../../../includes/available_balance.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_bad_request('Only POST is supported', 'invalid_method');
}

function swap_normalize_symbol(string $symbol): string
{
    $normalized = strtoupper(trim($symbol));
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);
    if ($normalized === null) {
        return '';
    }

    return substr($normalized, 0, 10);
}

function swap_wallet_balance_sum(mysqli $dbc, int $userId, string $symbol): float
{
    $stmt = mysqli_prepare(
        $dbc,
        'SELECT COALESCE(SUM(balance), 0) AS total_balance FROM wallets WHERE user_id = ? AND UPPER(TRIM(symbol)) = ?'
    );
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (float)($row['total_balance'] ?? 0.0);
}

function swap_wallet_get_for_update(mysqli $dbc, int $userId, string $symbol, bool $createIfMissing, float $initialBalance = 0.0): ?array
{
    $query = 'SELECT id, balance FROM wallets WHERE user_id = ? AND UPPER(TRIM(symbol)) = ? ORDER BY id ASC LIMIT 1 FOR UPDATE';
    $stmt = mysqli_prepare($dbc, $query);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($row) {
        return [
            'id' => (int)($row['id'] ?? 0),
            'balance' => (float)($row['balance'] ?? 0.0),
        ];
    }

    if (!$createIfMissing) {
        return null;
    }

    $insertStmt = mysqli_prepare($dbc, 'INSERT INTO wallets (user_id, symbol, balance) VALUES (?, ?, ?)');
    if (!$insertStmt) {
        return null;
    }

    $startBalance = max(0.0, (float)$initialBalance);
    mysqli_stmt_bind_param($insertStmt, 'isd', $userId, $symbol, $startBalance);
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    $reloadStmt = mysqli_prepare($dbc, $query);
    if (!$reloadStmt) {
        return null;
    }

    mysqli_stmt_bind_param($reloadStmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($reloadStmt);
    $reloadResult = mysqli_stmt_get_result($reloadStmt);
    $reloadRow = $reloadResult ? mysqli_fetch_assoc($reloadResult) : null;
    mysqli_stmt_close($reloadStmt);

    if (!$reloadRow) {
        return null;
    }

    return [
        'id' => (int)($reloadRow['id'] ?? 0),
        'balance' => (float)($reloadRow['balance'] ?? 0.0),
    ];
}

$userId = api_get_authenticated_user_id();
$body = api_get_request_json();

$payCurrency = swap_normalize_symbol((string)($body['payCurrency'] ?? ''));
$receiveCurrency = swap_normalize_symbol((string)($body['receiveCurrency'] ?? ''));
$payAmount = round((float)($body['payAmount'] ?? 0.0), 8);
$receiveAmount = round((float)($body['receiveAmount'] ?? 0.0), 8);

if ($payAmount <= 0.0 || $receiveAmount <= 0.0) {
    api_json_response(422, false, 'invalid_amount', 'Invalid swap amounts provided.');
}

if ($payCurrency === '' || $receiveCurrency === '' || $payCurrency === $receiveCurrency) {
    api_json_response(422, false, 'invalid_pair', 'Invalid swap currency pair.');
}

$allowedAssets = ['BTC', 'ETH'];
$pairIsValid =
    (($payCurrency === 'GBP' && in_array($receiveCurrency, $allowedAssets, true)) ||
     ($receiveCurrency === 'GBP' && in_array($payCurrency, $allowedAssets, true)));

if (!$pairIsValid) {
    api_json_response(422, false, 'unsupported_pair', 'Only GBP to BTC/ETH swaps are currently supported.');
}

$availablePay = 0.0;
if ($payCurrency === 'GBP') {
    $availablePayload = finpay_get_available_balance_gbp($dbc, $userId);
    $availablePay = (float)($availablePayload['amount'] ?? 0.0);
} else {
    $availablePay = swap_wallet_balance_sum($dbc, $userId, $payCurrency);
}

if ($payAmount > $availablePay + 0.00000001) {
    api_json_response(422, false, 'insufficient_balance', 'Insufficient ' . $payCurrency . ' balance for this swap.');
}

$reference = 'SWP-' . $userId . '-' . bin2hex(random_bytes(5));
$executedAt = date('c');

mysqli_begin_transaction($dbc);

try {
    $payWallet = null;
    if ($payCurrency === 'GBP') {
        $payWallet = swap_wallet_get_for_update($dbc, $userId, 'GBP', true, $availablePay);
    } else {
        $payWallet = swap_wallet_get_for_update($dbc, $userId, $payCurrency, false);
    }

    if (!$payWallet || (int)$payWallet['id'] <= 0) {
        throw new RuntimeException('pay_wallet_missing');
    }

    $payWalletBalance = (float)$payWallet['balance'];
    if ($payWalletBalance + 0.00000001 < $payAmount) {
        throw new RuntimeException('pay_wallet_insufficient');
    }

    $receiveWallet = null;
    if ($receiveCurrency === 'GBP') {
        $gbpAvailable = finpay_get_available_balance_gbp($dbc, $userId);
        $receiveWallet = swap_wallet_get_for_update($dbc, $userId, 'GBP', true, (float)($gbpAvailable['amount'] ?? 0.0));
    } else {
        $receiveWallet = swap_wallet_get_for_update($dbc, $userId, $receiveCurrency, true, 0.0);
    }

    if (!$receiveWallet || (int)$receiveWallet['id'] <= 0) {
        throw new RuntimeException('receive_wallet_missing');
    }

    $debitStmt = mysqli_prepare($dbc, 'UPDATE wallets SET balance = balance - ? WHERE id = ? LIMIT 1');
    if (!$debitStmt) {
        throw new RuntimeException('debit_prepare_failed');
    }
    $payWalletId = (int)$payWallet['id'];
    mysqli_stmt_bind_param($debitStmt, 'di', $payAmount, $payWalletId);
    mysqli_stmt_execute($debitStmt);
    mysqli_stmt_close($debitStmt);

    $creditStmt = mysqli_prepare($dbc, 'UPDATE wallets SET balance = balance + ? WHERE id = ? LIMIT 1');
    if (!$creditStmt) {
        throw new RuntimeException('credit_prepare_failed');
    }
    $receiveWalletId = (int)$receiveWallet['id'];
    mysqli_stmt_bind_param($creditStmt, 'di', $receiveAmount, $receiveWalletId);
    mysqli_stmt_execute($creditStmt);
    mysqli_stmt_close($creditStmt);

    $description = 'Swap ' . $payAmount . ' ' . $payCurrency . ' to ' . $receiveAmount . ' ' . $receiveCurrency;
    $status = 'completed';

    $txDebitType = 'swap_debit';
    $txDebitAmount = 0 - $payAmount;
    $txDebitStmt = mysqli_prepare(
        $dbc,
        'INSERT INTO transactions (user_id, type, symbol, amount, status, description) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if ($txDebitStmt) {
        mysqli_stmt_bind_param($txDebitStmt, 'issdss', $userId, $txDebitType, $payCurrency, $txDebitAmount, $status, $description);
        mysqli_stmt_execute($txDebitStmt);
        mysqli_stmt_close($txDebitStmt);
    }

    $txCreditType = 'swap_credit';
    $txCreditStmt = mysqli_prepare(
        $dbc,
        'INSERT INTO transactions (user_id, type, symbol, amount, status, description) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if ($txCreditStmt) {
        mysqli_stmt_bind_param($txCreditStmt, 'issdss', $userId, $txCreditType, $receiveCurrency, $receiveAmount, $status, $description);
        mysqli_stmt_execute($txCreditStmt);
        mysqli_stmt_close($txCreditStmt);
    }

    mysqli_commit($dbc);
} catch (Throwable $e) {
    mysqli_rollback($dbc);

    $code = ($e->getMessage() === 'pay_wallet_insufficient') ? 'insufficient_balance' : 'swap_failed';
    $message = ($code === 'insufficient_balance')
        ? 'Insufficient ' . $payCurrency . ' balance for this swap.'
        : 'Swap could not be completed. Please try again.';

    api_json_response(422, false, $code, $message);
}

$gbpPayload = finpay_get_available_balance_gbp($dbc, $userId);
$gbpAmount = (float)($gbpPayload['amount'] ?? 0.0);
$btcAmount = swap_wallet_balance_sum($dbc, $userId, 'BTC');
$ethAmount = swap_wallet_balance_sum($dbc, $userId, 'ETH');

api_json_response(200, true, 'swap_completed', 'Swap completed successfully.', [
    'swap' => [
        'reference' => $reference,
        'executed_at' => $executedAt,
        'pay_currency' => $payCurrency,
        'pay_amount' => $payAmount,
        'receive_currency' => $receiveCurrency,
        'receive_amount' => $receiveAmount,
    ],
    'balances' => [
        'GBP' => $gbpAmount,
        'BTC' => $btcAmount,
        'ETH' => $ethAmount,
    ],
]);

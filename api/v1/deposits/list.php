<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../../../includes/available_balance.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_bad_request('Only GET is supported', 'invalid_method');
}

$userId = api_get_authenticated_user_id();
$balancePayload = finpay_get_available_balance_gbp($dbc, $userId);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 500) {
    $limit = 500;
}

function list_first_existing_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function list_mask_account_number(string $accountNumber): string
{
    $digits = preg_replace('/[^0-9]/', '', $accountNumber);
    if ($digits === '') {
        return 'account hidden';
    }

    return '****' . substr($digits, -4);
}

function list_clamp_contact_name(string $name, int $max = 48): string
{
    $clean = trim($name);
    if ($clean === '') {
        return 'Saved contact';
    }

    if (mb_strlen($clean) <= $max) {
        return $clean;
    }

    return rtrim(mb_substr($clean, 0, $max - 1)) . '...';
}

function list_resolve_contact_payment_details(mysqli $dbc, int $userId, ?string $reference): array
{
    static $cache = [];

    $ref = trim((string)$reference);
    if ($ref === '') {
        return [];
    }

    if (array_key_exists($ref, $cache)) {
        return $cache[$ref];
    }

    if (!preg_match('/^CP-(\d+)-(\d+)-(\d+)$/', $ref, $matches)) {
        $cache[$ref] = [];
        return [];
    }

    $refUserId = (int)$matches[1];
    $contactId = (int)$matches[2];
    if ($refUserId !== $userId || $contactId <= 0 || !finpay_balance_table_exists($dbc, 'payment_contacts')) {
        $cache[$ref] = [];
        return [];
    }

    $stmt = mysqli_prepare($dbc, 'SELECT recipient_name, account_number FROM payment_contacts WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        $cache[$ref] = [];
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ii', $contactId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        $cache[$ref] = [];
        return [];
    }

    $name = list_clamp_contact_name((string)($row['recipient_name'] ?? ''), 48);

    $masked = list_mask_account_number((string)($row['account_number'] ?? ''));
    $cache[$ref] = [
        'display_label' => 'Payment to ' . $name,
        'display_method' => $name . ' (' . $masked . ')',
    ];

    return $cache[$ref];
}

function list_fetch_table_columns(mysqli $dbc, string $table): array
{
    if (!finpay_balance_table_exists($dbc, $table)) {
        return [];
    }

    return finpay_balance_table_columns($dbc, $table);
}

function list_build_activity_row(
    array $row,
    string $activityType,
    string $idCol,
    string $amountCol,
    string $currencyCol,
    string $statusCol,
    string $methodCol,
    string $createdCol,
    string $completedCol,
    string $defaultLabel
): array {
    return [
        'activity_id' => (string)($row[$idCol] ?? 'n/a'),
        'activity_type' => $activityType,
        'method' => (string)($row[$methodCol] ?? $defaultLabel),
        'method_raw' => (string)($row[$methodCol] ?? $defaultLabel),
        'currency' => strtoupper((string)($row[$currencyCol] ?? 'GBP')),
        'net_amount' => (float)($row[$amountCol] ?? 0.0),
        'status' => (string)($row[$statusCol] ?? 'pending'),
        'created_at' => (string)($row[$createdCol] ?? ''),
        'completed_at' => (string)($row[$completedCol] ?? ''),
    ];
}

function list_fetch_deposit_activities(mysqli $dbc, int $userId, int $limit): array
{
    $columns = list_fetch_table_columns($dbc, 'deposits');
    if (empty($columns)) {
        return [];
    }

    $idCol = list_first_existing_column($columns, ['deposit_id', 'public_id', 'id']);
    $userCol = list_first_existing_column($columns, ['user_id']);
    $amountCol = list_first_existing_column($columns, ['net_amount', 'amount']);
    $currencyCol = list_first_existing_column($columns, ['currency']);
    $statusCol = list_first_existing_column($columns, ['status']);
    $methodCol = list_first_existing_column($columns, ['method']);
    $createdCol = list_first_existing_column($columns, ['created_at']);
    $completedCol = list_first_existing_column($columns, ['completed_at', 'settled_at', 'created_at']);

    if ($idCol === null || $userCol === null || $amountCol === null || $currencyCol === null || $statusCol === null || $methodCol === null || $createdCol === null || $completedCol === null) {
        return [];
    }

    $sql = "SELECT * FROM deposits WHERE {$userCol} = ? ORDER BY {$createdCol} DESC LIMIT ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ii', $userId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = list_build_activity_row(
                $row,
                'deposit',
                $idCol,
                $amountCol,
                $currencyCol,
                $statusCol,
                $methodCol,
                $createdCol,
                $completedCol,
                'deposit'
            );
        }
    }

    mysqli_stmt_close($stmt);
    return $items;
}

function list_fetch_withdrawal_activities(mysqli $dbc, int $userId, int $limitPerTable): array
{
    $items = [];
    $tables = ['withdrawals', 'fiat_withdrawals', 'withdrawal_requests'];

    foreach ($tables as $table) {
        $columns = list_fetch_table_columns($dbc, $table);
        if (empty($columns)) {
            continue;
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $idCol = list_first_existing_column($columns, ['withdrawal_id', 'public_id', 'id']);
        $userCol = list_first_existing_column($columns, ['user_id']);
        $amountCol = list_first_existing_column($columns, ['net_amount', 'amount', 'withdrawal_amount']);
        $currencyCol = list_first_existing_column($columns, ['currency', 'fiat_currency', 'asset']);
        $statusCol = list_first_existing_column($columns, ['status', 'state']);
        $methodCol = list_first_existing_column($columns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);
        $referenceCol = list_first_existing_column($columns, ['reference', 'payment_reference', 'note', 'narration']);
        $createdCol = list_first_existing_column($columns, ['created_at', 'requested_at', 'initiated_at', 'created_on']);
        $completedCol = list_first_existing_column($columns, ['completed_at', 'processed_at', 'settled_at', 'updated_at', 'approved_at', 'created_at']);

        if ($safeTable === '' || $idCol === null || $userCol === null || $amountCol === null || $currencyCol === null || $statusCol === null || $methodCol === null || $createdCol === null || $completedCol === null) {
            continue;
        }

        $sql = "SELECT * FROM {$safeTable} WHERE {$userCol} = ? ORDER BY {$createdCol} DESC LIMIT ?";
        $stmt = mysqli_prepare($dbc, $sql);
        if (!$stmt) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $userId, $limitPerTable);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $item = list_build_activity_row(
                    $row,
                    'withdrawal',
                    $idCol,
                    $amountCol,
                    $currencyCol,
                    $statusCol,
                    $methodCol,
                    $createdCol,
                    $completedCol,
                    $safeTable
                );

                if ($referenceCol !== null) {
                    $item['reference'] = (string)($row[$referenceCol] ?? '');
                }

                if (strtolower((string)$item['method_raw']) === 'contact_payment') {
                    $details = list_resolve_contact_payment_details($dbc, $userId, (string)($item['reference'] ?? ''));
                    if (!empty($details['display_label'])) {
                        $item['display_label'] = (string)$details['display_label'];
                    }
                    if (!empty($details['display_method'])) {
                        $item['display_method'] = (string)$details['display_method'];
                    }
                }

                $items[] = $item;
            }
        }

        mysqli_stmt_close($stmt);
    }

    return $items;
}

function list_fetch_contact_payment_activities(mysqli $dbc, int $userId, int $limit): array
{
    $txColumns = list_fetch_table_columns($dbc, 'payment_contact_transactions');
    if (empty($txColumns)) {
        return [];
    }

    $txIdCol = list_first_existing_column($txColumns, ['id']);
    $txUserCol = list_first_existing_column($txColumns, ['user_id']);
    $txContactCol = list_first_existing_column($txColumns, ['contact_id']);
    $txDirectionCol = list_first_existing_column($txColumns, ['direction']);
    $txAmountCol = list_first_existing_column($txColumns, ['amount']);
    $txNoteCol = list_first_existing_column($txColumns, ['note']);
    $txCreatedCol = list_first_existing_column($txColumns, ['created_at']);

    if ($txIdCol === null || $txUserCol === null || $txContactCol === null || $txDirectionCol === null || $txAmountCol === null || $txCreatedCol === null) {
        return [];
    }

    $withdrawalRefMap = [];
    $wColumns = list_fetch_table_columns($dbc, 'withdrawals');
    if (!empty($wColumns)) {
        $wUserCol = list_first_existing_column($wColumns, ['user_id']);
        $wMethodCol = list_first_existing_column($wColumns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);
        $wCurrencyCol = list_first_existing_column($wColumns, ['currency', 'fiat_currency', 'asset']);
        $wStatusCol = list_first_existing_column($wColumns, ['status', 'state']);
        $wReferenceCol = list_first_existing_column($wColumns, ['reference', 'payment_reference', 'note', 'narration']);

        if ($wUserCol !== null && $wMethodCol !== null && $wCurrencyCol !== null && $wStatusCol !== null && $wReferenceCol !== null) {
            $wSql = "SELECT {$wReferenceCol} AS reference
                     FROM withdrawals
                     WHERE {$wUserCol} = ?
                       AND LOWER(CAST({$wMethodCol} AS CHAR)) = 'contact_payment'
                       AND UPPER({$wCurrencyCol}) = 'GBP'
                       AND LOWER({$wStatusCol}) = 'completed'";
            $wStmt = mysqli_prepare($dbc, $wSql);
            if ($wStmt) {
                mysqli_stmt_bind_param($wStmt, 'i', $userId);
                mysqli_stmt_execute($wStmt);
                $wResult = mysqli_stmt_get_result($wStmt);
                if ($wResult) {
                    while ($wRow = mysqli_fetch_assoc($wResult)) {
                        $ref = trim((string)($wRow['reference'] ?? ''));
                        if ($ref !== '') {
                            $withdrawalRefMap[$ref] = true;
                        }
                    }
                }
                mysqli_stmt_close($wStmt);
            }
        }
    }

    $sql = "SELECT tx.*, pc.recipient_name, pc.account_number
            FROM payment_contact_transactions tx
            LEFT JOIN payment_contacts pc
              ON pc.id = tx.{$txContactCol}
             AND pc.user_id = tx.{$txUserCol}
            WHERE tx.{$txUserCol} = ?
            ORDER BY tx.{$txCreatedCol} DESC
            LIMIT ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ii', $userId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $txId = (int)($row[$txIdCol] ?? 0);
            if ($txId <= 0) {
                continue;
            }

            $contactId = (int)($row[$txContactCol] ?? 0);
            $reference = 'CP-' . $userId . '-' . $contactId . '-' . $txId;
            if (isset($withdrawalRefMap[$reference])) {
                continue;
            }

            $direction = strtolower(trim((string)($row[$txDirectionCol] ?? 'sent')));
            $isSent = $direction !== 'received';
            $amount = abs((float)($row[$txAmountCol] ?? 0.0));
            if ($amount <= 0) {
                continue;
            }

            $name = list_clamp_contact_name((string)($row['recipient_name'] ?? ''), 48);
            $masked = list_mask_account_number((string)($row['account_number'] ?? ''));
            $displayLabel = $isSent ? ('Payment to ' . $name) : ('Payment from ' . $name);
            $displayMethod = $name . ' (' . $masked . ')';

            if ($txNoteCol !== null) {
                $note = trim((string)($row[$txNoteCol] ?? ''));
                if ($note !== '') {
                    $displayMethod .= ' • ' . $note;
                }
            }

            $items[] = [
                'activity_id' => 'cp_tx_' . $txId,
                'activity_type' => $isSent ? 'withdrawal' : 'deposit',
                'method' => $isSent ? 'contact_payment' : 'contact_payment_received',
                'method_raw' => $isSent ? 'contact_payment' : 'contact_payment_received',
                'currency' => 'GBP',
                'net_amount' => $amount,
                'status' => 'completed',
                'created_at' => (string)($row[$txCreatedCol] ?? ''),
                'completed_at' => (string)($row[$txCreatedCol] ?? ''),
                'reference' => $reference,
                'display_label' => $displayLabel,
                'display_method' => $displayMethod,
            ];
        }
    }

    mysqli_stmt_close($stmt);
    return $items;
}

function list_activity_time_label(?string $createdAt): string
{
    if (empty($createdAt)) {
        return 'Recently';
    }

    $createdTs = strtotime($createdAt);
    if ($createdTs === false) {
        return 'Recently';
    }

    $diff = time() - $createdTs;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 172800) {
        return 'Yesterday';
    }

    return date('j M', $createdTs);
}

function list_activity_datetime_label(?string $timestamp): string
{
    if (empty($timestamp)) {
        return 'N/A';
    }

    $ts = strtotime($timestamp);
    if ($ts === false) {
        return 'N/A';
    }

    return date('d M Y, H:i', $ts);
}

function list_activity_meta(string $activityType, string $method, string $status): array
{
    $safeType = strtolower($activityType);
    $safeMethod = strtolower($method);
    $safeStatus = strtolower($status);

    if ($safeType === 'withdrawal') {
        $map = [
            'bank' => ['icon_class' => 'fas fa-money-check-alt', 'bg' => 'rgba(239, 68, 68, 0.10)', 'color' => '#ef4444', 'label' => 'Bank Withdrawal'],
            'contact_payment' => ['icon_class' => 'fas fa-paper-plane', 'bg' => 'rgba(239, 68, 68, 0.10)', 'color' => '#ef4444', 'label' => 'Contact Payment'],
            'card' => ['icon_class' => 'fas fa-credit-card', 'bg' => 'rgba(239, 68, 68, 0.10)', 'color' => '#ef4444', 'label' => 'Card Withdrawal'],
        ];

        $meta = $map[$safeMethod] ?? ['icon_class' => 'fas fa-arrow-up', 'bg' => 'rgba(239, 68, 68, 0.10)', 'color' => '#ef4444', 'label' => 'Withdrawal'];
    } else {
        $map = [
            'bank' => ['icon_class' => 'fas fa-university', 'bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => 'Bank Deposit'],
            'card' => ['icon_class' => 'fas fa-credit-card', 'bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => 'Card Deposit'],
            'apple' => ['icon_class' => 'fab fa-apple', 'bg' => 'rgba(17, 24, 39, 0.10)', 'color' => 'var(--text-primary)', 'label' => 'Apple Pay Deposit'],
            'contact_payment_received' => ['icon_class' => 'fas fa-arrow-down', 'bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => 'Contact Payment Received'],
        ];

        $meta = $map[$safeMethod] ?? ['icon_class' => 'fas fa-arrow-down', 'bg' => 'var(--icon-bg-default)', 'color' => 'var(--text-primary)', 'label' => 'Deposit'];
    }

    if ($safeStatus === 'completed') {
        $meta['sub'] = 'Completed';
    } elseif ($safeStatus === 'pending_provider' || $safeStatus === 'pending_webhook') {
        $meta['sub'] = 'Pending';
    } elseif ($safeStatus === 'failed') {
        $meta['sub'] = 'Failed';
    } elseif ($safeStatus === 'reversed') {
        $meta['sub'] = 'Reversed';
    } else {
        $meta['sub'] = ucfirst($safeStatus ?: 'Initiated');
    }

    return $meta;
}

$activities = array_merge(
    list_fetch_deposit_activities($dbc, $userId, $limit),
    list_fetch_withdrawal_activities($dbc, $userId, $limit),
    list_fetch_contact_payment_activities($dbc, $userId, $limit)
);

usort($activities, function (array $a, array $b): int {
    $aTs = strtotime((string)($a['created_at'] ?? '')) ?: 0;
    $bTs = strtotime((string)($b['created_at'] ?? '')) ?: 0;
    return $bTs <=> $aTs;
});

$activities = array_slice($activities, 0, $limit);

$payload = [];
foreach ($activities as $activity) {
    $activityTypeRaw = strtolower((string)($activity['activity_type'] ?? 'deposit'));
    $methodRaw = (string)($activity['method_raw'] ?? $activity['method'] ?? '');
    $meta = list_activity_meta($activityTypeRaw, $methodRaw, (string)($activity['status'] ?? ''));
    $displayLabel = (string)($activity['display_label'] ?? $meta['label']);
    $displayMethod = (string)($activity['display_method'] ?? $methodRaw);
    $payload[] = [
        'activity_id' => (string)($activity['activity_id'] ?? 'n/a'),
        'activity_type' => ucfirst($activityTypeRaw),
        'label' => $displayLabel,
        'status_raw' => (string)($activity['status'] ?? 'unknown'),
        'status_sub' => (string)$meta['sub'],
        'method' => $displayMethod !== '' ? $displayMethod : 'n/a',
        'icon_class' => (string)($meta['icon_class'] ?? 'fas fa-arrow-down'),
        'amount' => number_format((float)($activity['net_amount'] ?? 0), 2),
        'flow' => $activityTypeRaw === 'withdrawal' ? 'out' : 'in',
        'currency' => strtoupper((string)($activity['currency'] ?? 'GBP')),
        'time_label' => list_activity_time_label($activity['created_at'] ?? null),
        'created_label' => list_activity_datetime_label($activity['created_at'] ?? null),
        'completed_label' => list_activity_datetime_label($activity['completed_at'] ?? null),
    ];
}

api_json_response(200, true, 'wallet_activity_list', 'Activity fetched', [
    'all' => $payload,
    'recent' => array_slice($payload, 0, 3),
    'balance' => [
        'amount' => (float)($balancePayload['amount'] ?? 0.0),
        'sign' => (string)($balancePayload['sign'] ?? ''),
        'major' => (string)($balancePayload['major'] ?? '0'),
        'minor' => (string)($balancePayload['minor'] ?? '00'),
        'formatted' => (string)($balancePayload['formatted'] ?? '0.00'),
        'currency' => (string)($balancePayload['currency'] ?? 'GBP'),
        'source' => (string)($balancePayload['source'] ?? 'none'),
    ],
]);

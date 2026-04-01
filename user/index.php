<?php
$pageTitle = 'FinPay Pro - Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../api/v1/lib/config.php';
require_once __DIR__ . '/../includes/available_balance.php';
$apiConfig = api_config();

$recentActivities = [];
$allActivities = [];

function dashboard_first_existing_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function dashboard_mask_account_number(string $accountNumber): string
{
    $digits = preg_replace('/[^0-9]/', '', $accountNumber);
    if ($digits === '') {
        return 'account hidden';
    }

    return '****' . substr($digits, -4);
}

function dashboard_clamp_contact_name(string $name, int $max = 48): string
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

function dashboard_resolve_contact_payment_details(mysqli $dbc, int $userId, ?string $reference): array
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

    $name = dashboard_clamp_contact_name((string)($row['recipient_name'] ?? ''), 48);

    $masked = dashboard_mask_account_number((string)($row['account_number'] ?? ''));
    $cache[$ref] = [
        'display_label' => 'Payment to ' . $name,
        'display_method' => $name . ' (' . $masked . ')',
    ];

    return $cache[$ref];
}

function dashboard_build_activity_row(
    array $row,
    string $activityType,
    string $idCol,
    string $amountCol,
    string $currencyCol,
    string $statusCol,
    string $methodCol,
    string $createdCol,
    string $completedCol,
    ?string $providerCol,
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
        'provider' => $providerCol !== null ? (string)($row[$providerCol] ?? $defaultLabel) : $defaultLabel,
        'created_at' => (string)($row[$createdCol] ?? ''),
        'completed_at' => (string)($row[$completedCol] ?? ''),
    ];
}

function dashboard_fetch_deposit_activities(mysqli $dbc, int $userId, int $limit): array
{
    if (!finpay_balance_table_exists($dbc, 'deposits')) {
        return [];
    }

    $columns = finpay_balance_table_columns($dbc, 'deposits');
    if (empty($columns)) {
        return [];
    }

    $idCol = dashboard_first_existing_column($columns, ['deposit_id', 'public_id', 'id']);
    $userCol = dashboard_first_existing_column($columns, ['user_id']);
    $amountCol = dashboard_first_existing_column($columns, ['net_amount', 'amount']);
    $currencyCol = dashboard_first_existing_column($columns, ['currency']);
    $statusCol = dashboard_first_existing_column($columns, ['status']);
    $methodCol = dashboard_first_existing_column($columns, ['method']);
    $providerCol = dashboard_first_existing_column($columns, ['provider']);
    $createdCol = dashboard_first_existing_column($columns, ['created_at']);
    $completedCol = dashboard_first_existing_column($columns, ['completed_at', 'settled_at', 'created_at']);

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
            $items[] = dashboard_build_activity_row(
                $row,
                'deposit',
                $idCol,
                $amountCol,
                $currencyCol,
                $statusCol,
                $methodCol,
                $createdCol,
                $completedCol,
                $providerCol,
                'deposit'
            );
        }
    }

    mysqli_stmt_close($stmt);
    return $items;
}

function dashboard_fetch_withdrawal_activities(mysqli $dbc, int $userId, int $limitPerTable): array
{
    $items = [];
    $tables = ['withdrawals', 'fiat_withdrawals', 'withdrawal_requests'];

    foreach ($tables as $table) {
        if (!finpay_balance_table_exists($dbc, $table)) {
            continue;
        }

        $columns = finpay_balance_table_columns($dbc, $table);
        if (empty($columns)) {
            continue;
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $idCol = dashboard_first_existing_column($columns, ['withdrawal_id', 'public_id', 'id']);
        $userCol = dashboard_first_existing_column($columns, ['user_id']);
        $amountCol = dashboard_first_existing_column($columns, ['net_amount', 'amount', 'withdrawal_amount']);
        $currencyCol = dashboard_first_existing_column($columns, ['currency', 'fiat_currency', 'asset']);
        $statusCol = dashboard_first_existing_column($columns, ['status', 'state']);
        $methodCol = dashboard_first_existing_column($columns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);
        $referenceCol = dashboard_first_existing_column($columns, ['reference', 'payment_reference', 'note', 'narration']);
        $createdCol = dashboard_first_existing_column($columns, ['created_at', 'requested_at', 'initiated_at', 'created_on']);
        $completedCol = dashboard_first_existing_column($columns, ['completed_at', 'processed_at', 'settled_at', 'updated_at', 'approved_at', 'created_at']);

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
                $item = dashboard_build_activity_row(
                    $row,
                    'withdrawal',
                    $idCol,
                    $amountCol,
                    $currencyCol,
                    $statusCol,
                    $methodCol,
                    $createdCol,
                    $completedCol,
                    null,
                    $safeTable
                );

                if ($referenceCol !== null) {
                    $item['reference'] = (string)($row[$referenceCol] ?? '');
                }

                if (strtolower((string)$item['method_raw']) === 'contact_payment') {
                    $details = dashboard_resolve_contact_payment_details($dbc, $userId, (string)($item['reference'] ?? ''));
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

function dashboard_fetch_contact_payment_activities(mysqli $dbc, int $userId, int $limit): array
{
    if (!finpay_balance_table_exists($dbc, 'payment_contact_transactions')) {
        return [];
    }

    $txColumns = finpay_balance_table_columns($dbc, 'payment_contact_transactions');
    if (empty($txColumns)) {
        return [];
    }

    $txIdCol = dashboard_first_existing_column($txColumns, ['id']);
    $txUserCol = dashboard_first_existing_column($txColumns, ['user_id']);
    $txContactCol = dashboard_first_existing_column($txColumns, ['contact_id']);
    $txDirectionCol = dashboard_first_existing_column($txColumns, ['direction']);
    $txAmountCol = dashboard_first_existing_column($txColumns, ['amount']);
    $txNoteCol = dashboard_first_existing_column($txColumns, ['note']);
    $txCreatedCol = dashboard_first_existing_column($txColumns, ['created_at']);

    if ($txIdCol === null || $txUserCol === null || $txContactCol === null || $txDirectionCol === null || $txAmountCol === null || $txCreatedCol === null) {
        return [];
    }

    $withdrawalRefMap = [];
    if (finpay_balance_table_exists($dbc, 'withdrawals')) {
        $wColumns = finpay_balance_table_columns($dbc, 'withdrawals');
        $wUserCol = dashboard_first_existing_column($wColumns, ['user_id']);
        $wMethodCol = dashboard_first_existing_column($wColumns, ['method', 'type', 'transaction_type', 'category', 'source', 'reason']);
        $wCurrencyCol = dashboard_first_existing_column($wColumns, ['currency', 'fiat_currency', 'asset']);
        $wStatusCol = dashboard_first_existing_column($wColumns, ['status', 'state']);
        $wReferenceCol = dashboard_first_existing_column($wColumns, ['reference', 'payment_reference', 'note', 'narration']);

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
            $name = dashboard_clamp_contact_name((string)($row['recipient_name'] ?? ''), 48);
            $masked = dashboard_mask_account_number((string)($row['account_number'] ?? ''));
            $displayLabel = $isSent ? ('Payment to ' . $name) : ('Payment from ' . $name);
            $displayMethod = $name . ' (' . $masked . ')';

            if ($txNoteCol !== null) {
                $note = trim((string)($row[$txNoteCol] ?? ''));
                if ($note !== '') {
                    $displayMethod .= ' • ' . $note;
                }
            }

            $amount = abs((float)($row[$txAmountCol] ?? 0.0));
            if ($amount <= 0) {
                continue;
            }

            $items[] = [
                'activity_id' => 'cp_tx_' . $txId,
                'activity_type' => $isSent ? 'withdrawal' : 'deposit',
                'method' => $isSent ? 'contact_payment' : 'contact_payment_received',
                'method_raw' => $isSent ? 'contact_payment' : 'contact_payment_received',
                'currency' => 'GBP',
                'net_amount' => $amount,
                'status' => 'completed',
                'provider' => 'contact_payment',
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

function dashboard_is_completed_status(string $status): bool
{
    $normalized = strtolower(trim($status));
    return in_array($normalized, ['completed', 'complete', 'settled', 'succeeded', 'success', 'approved'], true);
}

function dashboard_compute_analytics(array $activities): array
{
    $analytics = [
        'week' => ['deposits' => 0.0, 'withdrawals' => 0.0, 'net' => 0.0],
        'month' => ['deposits' => 0.0, 'withdrawals' => 0.0, 'net' => 0.0],
        'counts' => ['deposits' => 0, 'withdrawals' => 0, 'pending' => 0, 'failed' => 0],
        'daily' => [],
        'dailyMaxAbs' => 0.0,
    ];

    for ($i = 6; $i >= 0; $i--) {
        $key = date('Y-m-d', strtotime('-' . $i . ' days'));
        $analytics['daily'][$key] = 0.0;
    }

    $now = time();
    $weekAgo = strtotime('-7 days', $now);
    $monthAgo = strtotime('-30 days', $now);

    foreach ($activities as $activity) {
        $status = (string)($activity['status'] ?? '');
        $createdAt = strtotime((string)($activity['created_at'] ?? ''));
        $currency = strtoupper((string)($activity['currency'] ?? 'GBP'));
        $type = strtolower((string)($activity['activity_type'] ?? ''));
        $amount = (float)($activity['net_amount'] ?? 0.0);

        if ($createdAt === false) {
            continue;
        }

        if (!dashboard_is_completed_status($status)) {
            $statusLower = strtolower($status);
            if (str_contains($statusLower, 'pending')) {
                $analytics['counts']['pending'] += 1;
            } elseif (str_contains($statusLower, 'fail') || str_contains($statusLower, 'reverse')) {
                $analytics['counts']['failed'] += 1;
            }
            continue;
        }

        if ($currency !== 'GBP') {
            continue;
        }

        if ($type === 'deposit') {
            $analytics['counts']['deposits'] += 1;
        } elseif ($type === 'withdrawal') {
            $analytics['counts']['withdrawals'] += 1;
        }

        if ($createdAt >= $weekAgo) {
            if ($type === 'deposit') {
                $analytics['week']['deposits'] += $amount;
            } elseif ($type === 'withdrawal') {
                $analytics['week']['withdrawals'] += abs($amount);
            }
        }

        if ($createdAt >= $monthAgo) {
            if ($type === 'deposit') {
                $analytics['month']['deposits'] += $amount;
            } elseif ($type === 'withdrawal') {
                $analytics['month']['withdrawals'] += abs($amount);
            }
        }

        $dayKey = date('Y-m-d', $createdAt);
        if (array_key_exists($dayKey, $analytics['daily'])) {
            $analytics['daily'][$dayKey] += ($type === 'withdrawal' ? -abs($amount) : abs($amount));
        }
    }

    $analytics['week']['net'] = $analytics['week']['deposits'] - $analytics['week']['withdrawals'];
    $analytics['month']['net'] = $analytics['month']['deposits'] - $analytics['month']['withdrawals'];

    foreach ($analytics['daily'] as $dailyValue) {
        $analytics['dailyMaxAbs'] = max($analytics['dailyMaxAbs'], abs($dailyValue));
    }

    return $analytics;
}

if (isset($dbc, $_SESSION['user_id'])) {
    $safeUserId = (int)$_SESSION['user_id'];
    $depositActivities = dashboard_fetch_deposit_activities($dbc, $safeUserId, 500);
    $withdrawalActivities = dashboard_fetch_withdrawal_activities($dbc, $safeUserId, 500);
    $contactPaymentActivities = dashboard_fetch_contact_payment_activities($dbc, $safeUserId, 500);

    $allActivities = array_merge($depositActivities, $withdrawalActivities, $contactPaymentActivities);
    usort($allActivities, function (array $a, array $b): int {
        $aTs = strtotime((string)($a['created_at'] ?? '')) ?: 0;
        $bTs = strtotime((string)($b['created_at'] ?? '')) ?: 0;
        return $bTs <=> $aTs;
    });

    $allActivities = array_slice($allActivities, 0, 500);
    $recentActivities = array_slice($allActivities, 0, 3);
}

$analytics = dashboard_compute_analytics($allActivities);

function dashboard_activity_time_label(?string $createdAt): string
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

function dashboard_activity_meta(string $activityType, string $method, string $status): array
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

function dashboard_activity_datetime_label(?string $timestamp): string
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

$allActivitiesPayload = [];
if (!empty($allActivities)) {
    foreach ($allActivities as $activity) {
        $activityTypeRaw = strtolower((string)($activity['activity_type'] ?? 'deposit'));
        $flow = $activityTypeRaw === 'withdrawal' ? 'out' : 'in';
        $methodRaw = (string)($activity['method_raw'] ?? $activity['method'] ?? '');
        $meta = dashboard_activity_meta($activityTypeRaw, $methodRaw, (string)($activity['status'] ?? ''));
        $displayLabel = (string)($activity['display_label'] ?? $meta['label']);
        $displayMethod = (string)($activity['display_method'] ?? $methodRaw);
        $allActivitiesPayload[] = [
            'activity_id' => (string)($activity['activity_id'] ?? 'n/a'),
            'activity_type' => ucfirst($activityTypeRaw),
            'label' => $displayLabel,
            'status_raw' => (string)($activity['status'] ?? 'unknown'),
            'status_sub' => (string)$meta['sub'],
            'method' => $displayMethod !== '' ? $displayMethod : 'n/a',
            'icon_class' => (string)($meta['icon_class'] ?? 'fas fa-arrow-down'),
            'amount' => number_format((float)($activity['net_amount'] ?? 0), 2),
            'flow' => $flow,
            'currency' => strtoupper((string)($activity['currency'] ?? 'GBP')),
            'time_label' => dashboard_activity_time_label($activity['created_at'] ?? null),
            'created_label' => dashboard_activity_datetime_label($activity['created_at'] ?? null),
            'completed_label' => dashboard_activity_datetime_label($activity['completed_at'] ?? null),
        ];
    }
}

$fiatBalancePayload = finpay_balance_format_payload(0.0, 'none');
if (isset($dbc, $_SESSION['user_id'])) {
    $fiatBalancePayload = finpay_get_available_balance_gbp($dbc, (int)$_SESSION['user_id']);
}

$fiatAreaBalance = (float)($fiatBalancePayload['amount'] ?? 0.0);
$fiatBalanceSign = (string)($fiatBalancePayload['sign'] ?? '');
$fiatBalanceMajor = (string)($fiatBalancePayload['major'] ?? '0');
$fiatBalanceMinor = (string)($fiatBalancePayload['minor'] ?? '00');

$weekDeposits = (float)($analytics['week']['deposits'] ?? 0.0);
$weekWithdrawals = (float)($analytics['week']['withdrawals'] ?? 0.0);
$weekNet = (float)($analytics['week']['net'] ?? 0.0);
$monthNet = (float)($analytics['month']['net'] ?? 0.0);
$monthDeposits = (float)($analytics['month']['deposits'] ?? 0.0);
$monthWithdrawals = (float)($analytics['month']['withdrawals'] ?? 0.0);
$analyticsPendingCount = (int)($analytics['counts']['pending'] ?? 0);
$analyticsDepositCount = (int)($analytics['counts']['deposits'] ?? 0);
$analyticsWithdrawalCount = (int)($analytics['counts']['withdrawals'] ?? 0);

$weeklyReference = max(1.0, $weekDeposits + $weekWithdrawals);
$weeklyChangePercent = ($weekNet / $weeklyReference) * 100;
$weekNetSign = $weekNet < 0 ? '-' : '+';
$weekNetClass = $weekNet < 0 ? 'text-danger' : 'text-success';
$weeklyChangeClass = $weeklyChangePercent < 0 ? 'text-danger' : 'text-success';

$dailyBars = [];
$dailyMaxAbs = max(1.0, (float)($analytics['dailyMaxAbs'] ?? 0.0));
foreach (($analytics['daily'] ?? []) as $dayKey => $dayValue) {
    $normalized = (abs((float)$dayValue) / $dailyMaxAbs) * 75 + 20;
    $dailyBars[] = [
        'label' => date('D', strtotime((string)$dayKey)),
        'height' => round($normalized, 2),
        'positive' => ((float)$dayValue) >= 0,
    ];
}

require_once 'templates/head.php';
?>

<body>

    <!-- Desktop Sidebar -->
    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">
        
        <!-- Mobile Header -->
        <header class="mobile-header">
            <div class="profile-btn">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=00d26a&color=fff&bold=true" style="width: 100%; border-radius: 12px;">
            </div>
            <div style="font-weight: 700; letter-spacing: 1px;">FINPAY</div>
            <a href="cards.php" class="profile-btn" style="color: var(--text-primary); text-decoration: none;">
                <i class="fas fa-credit-card"></i>
            </a>
        </header>

        <!-- Main Layout Grid -->
        <div class="content-grid">
            
            <!-- Left Panel: Core Portfolio -->
            <div class="panel-left">
                
                <div class="balance-hero">
                    <div class="balance-label">Total Account Balance</div>
                    <div class="balance-amount">
                        <span id="totalBalanceSign"><?php echo htmlspecialchars($fiatBalanceSign, ENT_QUOTES, 'UTF-8'); ?></span><span class="balance-currency">£</span><span id="totalBalanceMajor"><?php echo htmlspecialchars($fiatBalanceMajor, ENT_QUOTES, 'UTF-8'); ?></span><span style="color: var(--text-secondary); font-size: 3rem;">.<span id="totalBalanceMinor"><?php echo htmlspecialchars($fiatBalanceMinor, ENT_QUOTES, 'UTF-8'); ?></span></span>
                    </div>
                    
                    <div class="action-grid">
                        <button class="btn-pro btn-pro-primary" data-bs-toggle="offcanvas" data-bs-target="#addMoneyModal"><i class="fas fa-plus"></i> Add Money</button>
                        <button class="btn-pro btn-pro-secondary" data-bs-toggle="offcanvas" data-bs-target="#accountDetailsModal"><i class="fas fa-info-circle"></i> Details</button>
                    </div>
                </div>

                <div class="px-3 px-lg-0 mt-2">
                    <h3 class="section-heading">My Assets <a href="#" style="font-size: 0.9rem; color: var(--accent); text-decoration: none;">Manage</a></h3>
                    
                    <div class="list-pro">
                        <!-- Fiat -->
                        <div class="asset-row">
                            <div class="asset-icon icon-gbp"><i class="fas fa-pound-sign"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">British Pound</div>
                                <div class="asset-sub">Primary Account</div>
                            </div>
                            <div class="asset-value">
                                <div id="primaryAccountBalance" class="asset-price"><?php echo htmlspecialchars($fiatBalanceSign, ENT_QUOTES, 'UTF-8'); ?>£<?php echo htmlspecialchars($fiatBalanceMajor, ENT_QUOTES, 'UTF-8'); ?>.<?php echo htmlspecialchars($fiatBalanceMinor, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        
                        <!-- Crypto -->
                        <div class="asset-row">
                            <div class="asset-icon icon-btc"><i class="fab fa-bitcoin"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">Bitcoin</div>
                                <div class="asset-sub">0.1250 BTC</div>
                            </div>
                            <div class="asset-value">
                                <div class="asset-price">£8,240.50</div>
                                <div class="asset-change text-success">+2.4%</div>
                            </div>
                        </div>

                        <!-- Vault -->
                        <div class="asset-row">
                            <div class="asset-icon icon-vault"><i class="fas fa-layer-group"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">Yield Vault</div>
                                <div class="asset-sub">Earning 5.2% APY</div>
                            </div>
                            <div class="asset-value">
                                <div class="asset-price">£0.00</div>
                                <div class="asset-change" style="color: var(--text-secondary);">Tap to fund</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Panel: Cards & Activity -->
            <div class="panel-right px-3 px-lg-0 mt-4 mt-lg-5">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Analytics</h3>
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">Net Flow <i class="fas fa-chevron-right ms-1"></i></div>
                </div>
                
                <div class="glass-panel text-center" style="padding: 2.5rem 1rem; margin-bottom: 2rem; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">
                    <div style="position: relative; height: 120px; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 10px; opacity: 0.8;">
                        <?php foreach ($dailyBars as $bar): ?>
                            <div style="width: 10%; background: <?php echo $bar['positive'] ? 'var(--accent)' : '#ef4444'; ?>; height: <?php echo htmlspecialchars((string)$bar['height'], ENT_QUOTES, 'UTF-8'); ?>%; border-radius: 6px; <?php echo $bar['positive'] ? 'box-shadow: 0 0 10px var(--accent-glow);' : 'opacity: 0.85;'; ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4">
                        <div style="font-size: 0.95rem; color: var(--text-secondary); font-weight: 500;">7-Day Net Flow (GBP)</div>
                        <div id="analyticsSummaryValue" style="font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-top: 5px;"><?php echo htmlspecialchars($weekNetSign, ENT_QUOTES, 'UTF-8'); ?> £<?php echo htmlspecialchars(number_format(abs($weekNet), 2), ENT_QUOTES, 'UTF-8'); ?> <span id="analyticsSummaryPercent" class="<?php echo htmlspecialchars($weeklyChangeClass, ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 1rem;">(<?php echo htmlspecialchars(number_format($weeklyChangePercent, 1), ENT_QUOTES, 'UTF-8'); ?>%)</span></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Activity</h3>
                    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#allActivityModal" style="font-size: 0.85rem; color: var(--accent); font-weight: 600; text-decoration: none; background: transparent; border: none; padding: 0;">See All <i class="fas fa-chevron-right ms-1" style="font-size: 0.75rem;"></i></button>
                </div>
                <div id="recentActivityList" class="list-pro">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <?php
                                $activityTypeRaw = strtolower((string)($activity['activity_type'] ?? 'deposit'));
                                $methodRaw = (string)($activity['method_raw'] ?? $activity['method'] ?? '');
                                $meta = dashboard_activity_meta($activityTypeRaw, $methodRaw, (string)($activity['status'] ?? ''));
                                $displayLabel = (string)($activity['display_label'] ?? $meta['label']);
                                $displayMethod = (string)($activity['display_method'] ?? $methodRaw);
                                $currency = strtoupper((string)($activity['currency'] ?? 'GBP'));
                                $amount = number_format((float)($activity['net_amount'] ?? 0), 2);
                                $isOutflow = $activityTypeRaw === 'withdrawal';
                                $timeLabel = dashboard_activity_time_label($activity['created_at'] ?? null);
                                $statusSub = $meta['sub'];
                                $activityType = ucfirst((string)($activity['activity_type'] ?? 'Activity'));
                                $createdLabel = dashboard_activity_datetime_label($activity['created_at'] ?? null);
                                $completedLabel = dashboard_activity_datetime_label($activity['completed_at'] ?? null);
                            ?>
                               <div class="asset-row" style="padding: 0.75rem 1rem;" data-bs-toggle="offcanvas" data-bs-target="#activityDetailsModal"
                                 data-activity-type="<?php echo htmlspecialchars($activityType, ENT_QUOTES); ?>"
                                                                 data-activity-label="<?php echo htmlspecialchars($displayLabel, ENT_QUOTES); ?>"
                                 data-activity-status="<?php echo htmlspecialchars((string)($activity['status'] ?? 'unknown'), ENT_QUOTES); ?>"
                                                                 data-activity-method="<?php echo htmlspecialchars($displayMethod !== '' ? $displayMethod : 'n/a', ENT_QUOTES); ?>"
                                   data-activity-icon="<?php echo htmlspecialchars($meta['icon_class'], ENT_QUOTES); ?>"
                                 data-activity-amount="<?php echo htmlspecialchars($amount, ENT_QUOTES); ?>"
                                 data-activity-flow="<?php echo $isOutflow ? 'out' : 'in'; ?>"
                                 data-activity-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES); ?>"
                                 data-activity-created="<?php echo htmlspecialchars($createdLabel, ENT_QUOTES); ?>"
                                 data-activity-completed="<?php echo htmlspecialchars($completedLabel, ENT_QUOTES); ?>"
                                 data-activity-id="<?php echo htmlspecialchars((string)($activity['activity_id'] ?? 'n/a'), ENT_QUOTES); ?>">
                                <div class="asset-icon" style="background: <?php echo htmlspecialchars($meta['bg'], ENT_QUOTES); ?>; color: <?php echo htmlspecialchars($meta['color'], ENT_QUOTES); ?>; width: 40px; height: 40px; font-size: 1.1rem;"><i class="<?php echo htmlspecialchars($meta['icon_class'], ENT_QUOTES); ?>"></i></div>
                                <div class="asset-info">
                                    <div class="asset-name" style="font-size: 0.95rem;"><?php echo htmlspecialchars($displayLabel, ENT_QUOTES); ?></div>
                                    <div class="asset-sub"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES); ?> • <?php echo htmlspecialchars($statusSub, ENT_QUOTES); ?></div>
                                </div>
                                <div class="asset-value">
                                    <div class="asset-price <?php echo $isOutflow ? 'text-danger' : 'text-success'; ?>" style="font-size: 0.95rem;"><?php echo $isOutflow ? '-' : '+'; ?> <?php echo htmlspecialchars($currency, ENT_QUOTES); ?> <?php echo htmlspecialchars($amount, ENT_QUOTES); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="asset-row" style="padding: 0.85rem 1rem; cursor: default;">
                            <div class="asset-icon" style="background: var(--icon-bg-default); width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-clock"></i></div>
                            <div class="asset-info">
                                <div class="asset-name" style="font-size: 0.95rem;">No recent activity yet</div>
                                <div class="asset-sub">Your latest platform activity will appear here.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

    <!-- Mobile Bottom Nav -->
    <?php require_once 'templates/bottom_nav.php'; ?>

    <!-- Account Details Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="accountDetailsModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">GBP Account</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-building text-accent me-1"></i> Local Details</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-right: 15px;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Bank Name</div>
                        <div style="font-weight: 700; font-size: 1.2rem; color: var(--text-primary);">FinPay Bank UK</div>
                    </div>
                </div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Account Holder</div>
                        <div style="font-weight: 700; font-size: 1.15rem; font-family: 'Outfit'; color: var(--text-primary);">John Doe</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Sort Code</div>
                        <div style="font-weight: 700; font-size: 1.35rem; font-family: 'Outfit', monospace; letter-spacing: 2px; color: var(--text-primary);">04-00-04</div>
                    </div>
                    <button class="btn btn-sm shadow-sm" style="background: var(--bg-surface); border: 1px solid var(--border-light); color: var(--text-primary); border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-copy"></i></button>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Account Number</div>
                        <div style="font-weight: 700; font-size: 1.35rem; font-family: 'Outfit', monospace; letter-spacing: 2px; color: var(--text-primary);">12345678</div>
                    </div>
                    <button class="btn btn-sm shadow-sm" style="background: var(--bg-surface); border: 1px solid var(--border-light); color: var(--text-primary); border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-copy"></i></button>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <div style="background: rgba(16, 185, 129, 0.1); border-radius: 16px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <i class="fas fa-shield-check mt-1" style="color: #10b981; font-size: 1.1rem;"></i>
                    <div>
                        <div style="font-weight: 700; color: #10b981; font-size: 0.95rem;">Verified Primary Account</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">Only share these native FinPay details with trusted routing parties.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Analytics Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="analyticsModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Portfolio Analytics</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-chart-line text-accent me-1"></i> Flow Snapshot</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="swap-input-box mb-4 text-center" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 2rem 1.5rem; transition: border-color 0.2s;">
                <div style="font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">7-Day Net Flow (GBP)</div>
                <div id="analyticsModalNet" style="font-weight: 700; font-size: 2.5rem; font-family: 'Outfit'; color: var(--text-primary);"><?php echo htmlspecialchars($weekNetSign, ENT_QUOTES, 'UTF-8'); ?> £<?php echo htmlspecialchars(number_format(abs($weekNet), 2), ENT_QUOTES, 'UTF-8'); ?></div>
                <div id="analyticsModalPercent" style="font-size: 1.1rem; font-weight: 600; margin-top: 5px;" class="<?php echo htmlspecialchars($weeklyChangeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(number_format($weeklyChangePercent, 1), ENT_QUOTES, 'UTF-8'); ?>%</div>
                
                <div style="position: relative; height: 100px; width: 100%; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; margin-top: 2rem; opacity: 0.9;">
                    <?php foreach ($dailyBars as $bar): ?>
                        <div style="width: 14%; background: <?php echo $bar['positive'] ? 'var(--accent)' : '#ef4444'; ?>; height: <?php echo htmlspecialchars((string)$bar['height'], ENT_QUOTES, 'UTF-8'); ?>%; border-radius: 6px; <?php echo $bar['positive'] ? 'box-shadow: 0 0 10px var(--accent-glow);' : 'opacity: 0.9;'; ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h3 class="section-heading mb-0" style="font-size: 1.1rem;">Movement Breakdown</h3>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fas fa-pie-chart text-secondary"></i></div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem;">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #10b981; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">Deposits (30d)</div>
                    </div>
                    <div id="analyticsDeposits30d" style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">£<?php echo htmlspecialchars(number_format($monthDeposits, 2), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">Withdrawals (30d)</div>
                    </div>
                    <div id="analyticsWithdrawals30d" style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">£<?php echo htmlspecialchars(number_format($monthWithdrawals, 2), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">Pending Transfers</div>
                    </div>
                    <div id="analyticsPendingCount" style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);"><?php echo htmlspecialchars((string)$analyticsPendingCount, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="progress mt-4" style="height: 12px; border-radius: 100px; background: rgba(255,255,255,0.05);">
                    <?php
                        $flowTotal = max(1.0, $monthDeposits + $monthWithdrawals);
                        $depWidth = ($monthDeposits / $flowTotal) * 100;
                        $wdrWidth = ($monthWithdrawals / $flowTotal) * 100;
                    ?>
                    <div id="analyticsDepProgress" class="progress-bar" role="progressbar" style="width: <?php echo htmlspecialchars(number_format($depWidth, 2), ENT_QUOTES, 'UTF-8'); ?>%; background: #10b981; border-radius: 100px;"></div>
                    <div id="analyticsWdrProgress" class="progress-bar" role="progressbar" style="width: <?php echo htmlspecialchars(number_format($wdrWidth, 2), ENT_QUOTES, 'UTF-8'); ?>%; background: #ef4444; border-radius: 100px;"></div>
                </div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.25rem 1.5rem;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="font-weight: 600; color: var(--text-secondary);">Completed Deposits</div>
                    <div id="analyticsDepositCount" style="font-weight: 700; font-family: 'Outfit'; color: var(--text-primary);"><?php echo htmlspecialchars((string)$analyticsDepositCount, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div style="font-weight: 600; color: var(--text-secondary);">Completed Withdrawals</div>
                    <div id="analyticsWithdrawalCount" style="font-weight: 700; font-family: 'Outfit'; color: var(--text-primary);"><?php echo htmlspecialchars((string)$analyticsWithdrawalCount, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid var(--border-light);">
                    <div style="font-weight: 600; color: var(--text-secondary);">30-Day Net</div>
                    <div id="analyticsMonthNet" class="<?php echo $monthNet < 0 ? 'text-danger' : 'text-success'; ?>" style="font-weight: 700; font-family: 'Outfit';"><?php echo $monthNet < 0 ? '-' : '+'; ?>£<?php echo htmlspecialchars(number_format(abs($monthNet), 2), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button class="btn-pro btn-pro-secondary w-100" data-bs-dismiss="offcanvas" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.05rem;">Close Analytics</button>
            </div>

        </div>
    </div>

    <!-- Deposit Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="addMoneyModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Deposit Funds</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-shield-alt text-success me-1"></i> Secure Top-up</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="mb-4 text-center">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Amount to Deposit</div>
                <div class="d-flex align-items-center justify-content-center" style="font-size: 3rem; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                    <span style="font-size: 2rem; margin-right: 5px; color: var(--text-secondary);">£</span>
                    <input type="number" id="depositAmount" value="100" style="background: transparent; border: none; color: var(--text-primary); width: 140px; text-align: center; outline: none;" oninput="updateDepositButton()">
                </div>
            </div>

            <div class="mb-3 px-2">
                <h3 class="section-heading mb-1" style="font-size: 1rem;">Select Method</h3>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'bank')" style="background: rgba(0, 210, 106, 0.05); border: 1px solid var(--accent); border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary);">Instant Bank Transfer</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Powered by Open Banking <span class="text-accent fw-bold ms-1" style="font-size: 0.75rem;"><i class="fas fa-bolt"></i> Instant & Free</span></div>
                    </div>
                    <div class="payment-check-icon"><i class="fas fa-check-circle text-accent" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'card')" style="background: var(--bg-surface-light); border: 1px solid transparent; border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Debit or Credit Card</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Visa, Mastercard, Maestro <span class="ms-1 px-1 rounded text-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.7rem;">1% Fee</span></div>
                    </div>
                    <div class="payment-check-icon"><i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'apple')" style="background: var(--bg-surface-light); border: 1px solid transparent; border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: var(--text-primary); color: var(--bg-body); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fab fa-apple"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Apple Pay</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Instant wallet deposit</div>
                    </div>
                    <div class="payment-check-icon"><i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button id="depositContinueBtn" class="btn-pro btn-pro-primary w-100" onclick="proceedDeposit()" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 25px rgba(239, 184, 12, 0.25);">Continue to Deposit</button>
                <div id="depositFeedback" class="text-center mt-2" style="font-size: 0.82rem; color: var(--text-secondary);"></div>
                <div class="text-center mt-3">
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0; opacity: 0.8;"><i class="fas fa-lock text-success me-1"></i> Funds protected by FinPay Shield.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Card Deposit Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="cardDepositModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Card Top-up</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Powered by Stripe</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="mb-4 text-center">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Amount to Deposit</div>
                <div class="d-flex align-items-center justify-content-center" style="font-size: 3rem; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                    <span style="font-size: 2rem; margin-right: 5px; color: var(--text-secondary);">£</span>
                    <span id="cardDepositAmountDisplay">100</span>
                </div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 20px; padding: 1.5rem;">
                <div class="mb-2">
                    <label style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 5px;">Card Details</label>
                    <div id="cardElement" style="background: var(--bg-body); border: 1px solid var(--border-light); border-radius: 12px; padding: 14px 15px;"></div>
                </div>
                <div id="cardElementError" style="font-size: 0.8rem; color: #ef4444; min-height: 18px;"></div>
                <div class="mt-3" style="font-size: 0.78rem; color: var(--text-secondary);">
                    Secure sandbox payment powered by Stripe Elements.
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button id="cardPayBtn" class="btn-pro btn-pro-primary w-100" onclick="submitCardDeposit()" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 25px rgba(239, 184, 12, 0.25);">Pay Securely</button>
                <div id="cardDepositFeedback" class="text-center mt-2" style="font-size: 0.82rem; color: var(--text-secondary);"></div>
            </div>

        </div>
    </div>

    <!-- Apple Pay Modal -->
    <div class="modal fade" id="applePayModal" tabindex="-1" aria-hidden="true" style="z-index: 10600;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 24px; color: var(--text-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.1); padding: 2rem;">
                <div class="text-center">
                    <div style="font-size: 4rem; color: var(--text-primary); margin-bottom: 1rem;"><i class="fab fa-apple"></i></div>
                    <h4 style="font-family: 'Outfit', sans-serif; font-weight: 700; margin-bottom: 0.5rem;">Apple Pay</h4>
                    <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 2rem;">Double click side button to pay <br><span style="font-weight: 700; color: var(--text-primary); font-size: 1.2rem;">£<span id="applePayAmountDisplay">100</span></span></p>
                    <button class="btn btn-dark w-100 py-3" data-bs-dismiss="modal" style="border-radius: 100px; font-weight: 600;">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Activities Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="allActivityModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">All Activity</div>
                <div id="allActivityCount" style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Most recent first</div>
            </div>
        </div>

        <div id="allActivityBody" class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            <div id="allActivityList" class="list-pro"></div>
            <div id="allActivityLoading" class="text-center mt-3" style="font-size: 0.85rem; color: var(--text-secondary); display: none;">
                Loading more activity...
            </div>
            <div id="allActivityEnd" class="text-center mt-3" style="font-size: 0.85rem; color: var(--text-secondary); display: none;">
                You have reached the end of your activity feed.
            </div>
        </div>
    </div>

    <!-- Activity Details Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="activityDetailsModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Activity Details</div>
                <div id="activityDetailsType" style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Activity</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            <div id="activityDetailsHeroCard" class="swap-input-box mb-4" style="background: linear-gradient(180deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05)); border: 1px solid var(--border-light); border-radius: 24px; padding: 1.3rem 1.15rem;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center">
                        <div id="activityDetailsIconWrap" style="width: 48px; height: 48px; border-radius: 14px; background: var(--icon-bg-default); color: var(--text-primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-right: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.08);">
                            <i id="activityDetailsIcon" class="fas fa-list"></i>
                        </div>
                        <div>
                            <div id="activityDetailsLabel" style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary); line-height: 1.15;">-</div>
                            <div style="font-size: 0.76rem; color: var(--text-secondary); font-weight: 600; letter-spacing: 0.3px; margin-top: 4px; text-transform: uppercase;">Transaction</div>
                        </div>
                    </div>
                    <span id="activityDetailsStatus" style="font-size: 0.74rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border-light); background: rgba(255,255,255,0.5); color: var(--text-secondary);">-</span>
                </div>

                <div id="activityDetailsAmount" style="font-size: 2.1rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-primary); line-height: 1;">-</div>
                <div id="activityDetailsAmountSub" style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Wallet movement</div>
            </div>

            <div id="activityDetailsMetaCard" class="swap-input-box mb-3" style="background: var(--bg-surface-light); border: 1px solid var(--border-light); border-radius: 20px; padding: 0.5rem 1rem;">
                <div class="d-flex justify-content-between py-3 border-bottom" style="border-color: var(--asset-border) !important;">
                    <span style="color: var(--text-secondary); font-size: 0.86rem;">Method</span>
                    <strong id="activityDetailsMethod" style="font-size: 0.9rem;">-</strong>
                </div>
                <div class="d-flex justify-content-between py-3 border-bottom" style="border-color: var(--asset-border) !important;">
                    <span style="color: var(--text-secondary); font-size: 0.86rem;">Created</span>
                    <strong id="activityDetailsCreated" style="font-size: 0.9rem; text-align: right; margin-left: 10px;">-</strong>
                </div>
                <div class="d-flex justify-content-between py-3">
                    <span style="color: var(--text-secondary); font-size: 0.86rem;">Completed</span>
                    <strong id="activityDetailsCompleted" style="font-size: 0.9rem; text-align: right; margin-left: 10px;">-</strong>
                </div>
            </div>

            <div id="activityDetailsAuditNote" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start;">
                <i class="fas fa-shield-check" style="color: #10b981; margin-top: 2px;"></i>
                <div id="activityDetailsAuditText" style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.45;">
                    Activity entries are ordered by latest updates and represent immutable audit trail events.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>

    <script>
        const STRIPE_PUBLISHABLE_KEY = '<?php echo htmlspecialchars($apiConfig['stripe_publishable_key'] ?? '', ENT_QUOTES); ?>';
        const API_BASE_URL = window.FINPAY_API_BASE_URL || '../api/v1';
        let ALL_ACTIVITIES = <?php echo json_encode($allActivitiesPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let selectedMethod = 'bank';
        let activeCardDepositId = null;
        let activeCardClientSecret = null;
        let activeCardProviderMode = 'mock';
        let stripe = null;
        let stripeElements = null;
        let cardElement = null;
        let renderedActivityCount = 0;
        let isAppendingActivities = false;
        let activityPollTimer = null;
        let lastActivitySignature = '';
        let lastActivityStatusMap = {};

        const globalNotify = (window.finpayNotify && typeof window.finpayNotify === 'function')
            ? window.finpayNotify
            : null;

        const INITIAL_ACTIVITY_BATCH = 15;
        const NEXT_ACTIVITY_BATCH = 6;

        function setFeedback(id, message, isError = false) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = message;
            el.style.color = isError ? '#ef4444' : 'var(--text-secondary)';
        }

        function notifyActivity(message, type = 'info', title = 'Activity', duration = 3200) {
            if (globalNotify) {
                globalNotify(message, {
                    type,
                    title,
                    duration,
                });
            }

            window.dispatchEvent(new CustomEvent('finpay:activity', {
                detail: {
                    kind: type,
                    title,
                    message,
                    important: true,
                }
            }));
        }

        async function apiCall(path, method = 'GET', body = null) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (body !== null) {
                options.body = JSON.stringify(body);
            }

            const response = await fetch(`${API_BASE_URL}${path}`, options);
            const raw = await response.text();
            let payload = null;

            try {
                payload = JSON.parse(raw);
            } catch (e) {
                const brief = raw.length > 240 ? `${raw.slice(0, 240)}...` : raw;
                throw new Error(`API returned non-JSON response. ${brief}`);
            }

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Request failed');
            }
            return payload;
        }

        function initializeStripeElements() {
            if (!STRIPE_PUBLISHABLE_KEY || typeof window.Stripe === 'undefined') {
                return false;
            }

            if (!stripe) {
                stripe = window.Stripe(STRIPE_PUBLISHABLE_KEY);
            }

            if (!stripe) {
                return false;
            }

            if (!stripeElements) {
                stripeElements = stripe.elements();
            }

            if (!cardElement) {
                cardElement = stripeElements.create('card', {
                    style: {
                        base: {
                            color: '#111827',
                            fontFamily: 'Outfit, sans-serif',
                            fontSize: '16px',
                            '::placeholder': { color: '#9ca3af' }
                        },
                        invalid: {
                            color: '#ef4444'
                        }
                    }
                });

                cardElement.mount('#cardElement');
                cardElement.on('change', function(event) {
                    const errEl = document.getElementById('cardElementError');
                    errEl.textContent = event.error ? event.error.message : '';
                });
            }

            return true;
        }

        async function pollDepositStatus(depositId, maxAttempts = 20, intervalMs = 1500) {
            for (let i = 0; i < maxAttempts; i += 1) {
                const result = await apiCall(`/deposits/status.php?deposit_id=${encodeURIComponent(depositId)}`, 'GET');
                const status = (result.data.deposit.status || '').toLowerCase();

                if (status === 'completed') {
                    return result.data.deposit;
                }

                if (status === 'failed' || status === 'expired' || status === 'reversed') {
                    throw new Error(`Deposit ended with status: ${status}`);
                }

                await new Promise(resolve => setTimeout(resolve, intervalMs));
            }

            throw new Error('Timed out waiting for deposit settlement.');
        }

        function selectPaymentMethod(element, method) {
            selectedMethod = method;
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.style.background = 'var(--bg-surface-light)';
                card.style.border = '1px solid transparent';
                card.querySelector('.payment-check-icon').innerHTML = '<i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i>';
            });
            element.style.background = 'rgba(0, 210, 106, 0.05)';
            element.style.border = '1px solid var(--accent)';
            element.querySelector('.payment-check-icon').innerHTML = '<i class="fas fa-check-circle text-accent" style="font-size: 1.25rem;"></i>';
        }

        function updateDepositButton() {
            const amount = parseFloat(document.getElementById('depositAmount').value || '0');
            const btn = document.getElementById('depositContinueBtn');
            if (btn) {
                btn.disabled = amount <= 0;
                btn.style.opacity = amount <= 0 ? '0.65' : '1';
            }
        }

        async function proceedDeposit() {
            const amount = parseFloat(document.getElementById('depositAmount').value || '0');
            if (!amount || amount <= 0) {
                setFeedback('depositFeedback', 'Enter a valid amount.', true);
                notifyActivity('Enter a valid deposit amount.', 'warning', 'Deposit Validation');
                return;
            }

            setFeedback('depositFeedback', 'Creating deposit...');

            try {
                const result = await apiCall('/deposits/create.php', 'POST', {
                    amount,
                    method: selectedMethod
                });

                const deposit = result.data.deposit;

                const addMoneyModalEl = document.getElementById('addMoneyModal');
                const addMoneyModal = bootstrap.Offcanvas.getInstance(addMoneyModalEl);
                if (addMoneyModal) addMoneyModal.hide();

                setTimeout(() => {
                    if (selectedMethod === 'bank') {
                        setFeedback('depositFeedback', `Deposit created: ${deposit.deposit_id}. Use your bank details to complete transfer.`);
                        notifyActivity(`Bank deposit ${deposit.deposit_id} created. Complete transfer with your bank details.`, 'info', 'Deposit Created');
                        const modal = new bootstrap.Offcanvas(document.getElementById('accountDetailsModal'));
                        modal.show();
                    } else if (selectedMethod === 'card') {
                        activeCardDepositId = deposit.deposit_id;
                        activeCardClientSecret = result.data.provider ? result.data.provider.client_secret : null;
                        activeCardProviderMode = result.data.provider ? (result.data.provider.mode || 'mock') : 'mock';
                        document.getElementById('cardDepositAmountDisplay').innerText = amount;
                        if (activeCardProviderMode === 'stripe') {
                            const initialized = initializeStripeElements();
                            if (!initialized) {
                                throw new Error('Stripe publishable key is missing or invalid.');
                            }
                            setFeedback('cardDepositFeedback', `Deposit ${deposit.deposit_id} is ready. Confirm card payment.`);
                            notifyActivity(`Card deposit ${deposit.deposit_id} is ready for secure confirmation.`, 'info', 'Card Deposit Ready');
                        } else {
                            setFeedback('cardDepositFeedback', `Mock mode: Deposit ${deposit.deposit_id} is ready. Click Pay Securely to complete sandbox settlement.`);
                            notifyActivity(`Sandbox card deposit ${deposit.deposit_id} is ready to confirm.`, 'info', 'Card Deposit Ready');
                        }
                        const modal = new bootstrap.Offcanvas(document.getElementById('cardDepositModal'));
                        modal.show();
                    } else if (selectedMethod === 'apple') {
                        document.getElementById('applePayAmountDisplay').innerText = amount;
                        setFeedback('depositFeedback', `Deposit completed: ${deposit.deposit_id}`);
                        notifyActivity(`Apple Pay deposit ${deposit.deposit_id} completed successfully.`, 'success', 'Deposit Complete');
                        const modal = new bootstrap.Modal(document.getElementById('applePayModal'));
                        modal.show();
                    }
                }, 300);
            } catch (err) {
                setFeedback('depositFeedback', err.message || 'Deposit failed.', true);
                notifyActivity(err.message || 'Deposit failed.', 'error', 'Deposit Failed', 4200);
            }
        }

        async function submitCardDeposit() {
            if (!activeCardDepositId) {
                setFeedback('cardDepositFeedback', 'No active card deposit found.', true);
                notifyActivity('No active card deposit found. Start a new card deposit first.', 'warning', 'Card Deposit');
                return;
            }

            const payBtn = document.getElementById('cardPayBtn');
            payBtn.disabled = true;
            payBtn.textContent = 'Processing...';
            setFeedback('cardDepositFeedback', 'Confirming card deposit...');

            try {
                if (activeCardProviderMode === 'stripe') {
                    if (!stripe || !cardElement || !activeCardClientSecret) {
                        throw new Error('Stripe payment is not initialized.');
                    }

                    const confirmation = await stripe.confirmCardPayment(activeCardClientSecret, {
                        payment_method: {
                            card: cardElement
                        }
                    });

                    if (confirmation.error) {
                        throw new Error(confirmation.error.message || 'Card confirmation failed.');
                    }

                    setFeedback('cardDepositFeedback', 'Payment confirmed. Waiting for webhook settlement...');
                    await pollDepositStatus(activeCardDepositId);
                } else {
                    await apiCall('/deposits/confirm.php', 'POST', {
                        deposit_id: activeCardDepositId
                    });
                }

                setFeedback('cardDepositFeedback', `Deposit ${activeCardDepositId} completed.`);
                notifyActivity(`Card deposit ${activeCardDepositId} completed successfully.`, 'success', 'Deposit Complete');

                const cardModalEl = document.getElementById('cardDepositModal');
                const cardModal = bootstrap.Offcanvas.getInstance(cardModalEl);
                if (cardModal) {
                    setTimeout(() => cardModal.hide(), 500);
                }
            } catch (err) {
                setFeedback('cardDepositFeedback', err.message || 'Could not confirm card payment.', true);
                notifyActivity(err.message || 'Could not confirm card payment.', 'error', 'Card Deposit Failed', 4200);
            } finally {
                payBtn.disabled = false;
                payBtn.textContent = 'Pay Securely';
            }
        }

        function normalizeStatusLabel(rawStatus) {
            const value = String(rawStatus || '').toLowerCase();
            if (value.includes('complete')) return 'Completed';
            if (value.includes('fail')) return 'Failed';
            if (value.includes('reverse')) return 'Reversed';
            if (value.includes('pending')) return 'Pending';
            return 'Pending';
        }

        function getStatusColor(rawStatus) {
            const value = String(rawStatus || '').toLowerCase();
            if (value.includes('complete')) return '#10b981';
            if (value.includes('fail') || value.includes('reverse')) return '#ef4444';
            if (value.includes('pending')) return '#f59e0b';
            return 'var(--text-secondary)';
        }

        function getIconTone(iconClass) {
            if (iconClass.includes('fa-university')) {
                return { bg: 'rgba(59, 130, 246, 0.12)', color: '#3b82f6' };
            }
            if (iconClass.includes('fa-paper-plane') || iconClass.includes('fa-money-check') || iconClass.includes('fa-arrow-up')) {
                return { bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444' };
            }
            if (iconClass.includes('fa-credit-card')) {
                return { bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981' };
            }
            if (iconClass.includes('fa-apple')) {
                return { bg: 'rgba(17, 24, 39, 0.10)', color: 'var(--text-primary)' };
            }
            return { bg: 'var(--icon-bg-default)', color: 'var(--text-primary)' };
        }

        function createAllActivityRow(activity) {
            const row = document.createElement('div');
            row.className = 'asset-row';
            row.style.padding = '0.75rem 1rem';
            row.dataset.bsToggle = 'offcanvas';
            row.dataset.bsTarget = '#activityDetailsModal';
            row.dataset.activityType = activity.activity_type || 'Activity';
            row.dataset.activityLabel = activity.label || 'Activity';
            row.dataset.activityStatus = activity.status_raw || 'pending';
            row.dataset.activityMethod = activity.method || 'n/a';
            row.dataset.activityIcon = activity.icon_class || 'fas fa-arrow-down';
            row.dataset.activityAmount = activity.amount || '0.00';
            row.dataset.activityFlow = activity.flow || ((String(activity.activity_type || '').toLowerCase() === 'withdrawal') ? 'out' : 'in');
            row.dataset.activityCurrency = activity.currency || 'GBP';
            row.dataset.activityCreated = activity.created_label || 'N/A';
            row.dataset.activityCompleted = activity.completed_label || 'N/A';
            row.dataset.activityId = activity.activity_id || 'n/a';

            const iconTone = getIconTone(row.dataset.activityIcon);
            const statusColor = getStatusColor(row.dataset.activityStatus);
            const statusLabel = normalizeStatusLabel(row.dataset.activityStatus);
            const isOutflow = String(row.dataset.activityFlow || '').toLowerCase() === 'out';
            const amountPrefix = isOutflow ? '-' : '+';
            const amountClass = isOutflow ? 'text-danger' : 'text-success';

            row.innerHTML = `
                <div class="asset-icon" style="background: ${iconTone.bg}; color: ${iconTone.color}; width: 40px; height: 40px; font-size: 1.1rem;"><i class="${row.dataset.activityIcon}"></i></div>
                <div class="asset-info">
                    <div class="asset-name" style="font-size: 0.95rem;">${row.dataset.activityLabel}</div>
                    <div class="asset-sub">${activity.time_label || 'Recently'} • <span style="color: ${statusColor}; font-weight: 600;">${statusLabel}</span></div>
                </div>
                <div class="asset-value">
                    <div class="asset-price ${amountClass}" style="font-size: 0.95rem;">${amountPrefix} ${row.dataset.activityCurrency} ${row.dataset.activityAmount}</div>
                </div>
            `;

            return row;
        }

        function createRecentActivityRow(activity) {
            const row = document.createElement('div');
            row.className = 'asset-row';
            row.style.padding = '0.75rem 1rem';
            row.dataset.bsToggle = 'offcanvas';
            row.dataset.bsTarget = '#activityDetailsModal';
            row.dataset.activityType = activity.activity_type || 'Activity';
            row.dataset.activityLabel = activity.label || 'Activity';
            row.dataset.activityStatus = activity.status_raw || 'pending';
            row.dataset.activityMethod = activity.method || 'n/a';
            row.dataset.activityIcon = activity.icon_class || 'fas fa-arrow-down';
            row.dataset.activityAmount = activity.amount || '0.00';
            row.dataset.activityFlow = activity.flow || ((String(activity.activity_type || '').toLowerCase() === 'withdrawal') ? 'out' : 'in');
            row.dataset.activityCurrency = activity.currency || 'GBP';
            row.dataset.activityCreated = activity.created_label || 'N/A';
            row.dataset.activityCompleted = activity.completed_label || 'N/A';
            row.dataset.activityId = activity.activity_id || 'n/a';

            const iconTone = getIconTone(row.dataset.activityIcon);
            const isOutflow = String(row.dataset.activityFlow || '').toLowerCase() === 'out';
            const amountPrefix = isOutflow ? '-' : '+';
            const amountClass = isOutflow ? 'text-danger' : 'text-success';

            row.innerHTML = `
                <div class="asset-icon" style="background: ${iconTone.bg}; color: ${iconTone.color}; width: 40px; height: 40px; font-size: 1.1rem;"><i class="${row.dataset.activityIcon}"></i></div>
                <div class="asset-info">
                    <div class="asset-name" style="font-size: 0.95rem;">${row.dataset.activityLabel}</div>
                    <div class="asset-sub">${activity.time_label || 'Recently'} • ${activity.status_sub || normalizeStatusLabel(row.dataset.activityStatus)}</div>
                </div>
                <div class="asset-value">
                    <div class="asset-price ${amountClass}" style="font-size: 0.95rem;">${amountPrefix} ${row.dataset.activityCurrency} ${row.dataset.activityAmount}</div>
                </div>
            `;

            return row;
        }

        function renderRecentActivityList(items) {
            const listEl = document.getElementById('recentActivityList');
            if (!listEl) {
                return;
            }

            const activities = Array.isArray(items) ? items : [];
            listEl.innerHTML = '';

            if (activities.length === 0) {
                listEl.innerHTML = `
                    <div class="asset-row" style="padding: 0.85rem 1rem; cursor: default;">
                        <div class="asset-icon" style="background: var(--icon-bg-default); width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-clock"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 0.95rem;">No recent activity yet</div>
                            <div class="asset-sub">Your latest platform activity will appear here.</div>
                        </div>
                    </div>
                `;
                return;
            }

            activities.forEach((item) => {
                listEl.appendChild(createRecentActivityRow(item));
            });
        }

        function buildActivitySignature(items) {
            if (!Array.isArray(items) || items.length === 0) {
                return 'empty';
            }

            return items.slice(0, 25).map((item) => {
                return [
                    item.activity_id || 'n/a',
                    item.status_raw || 'unknown',
                    item.amount || '0.00',
                    item.completed_label || 'N/A',
                ].join('|');
            }).join('~');
        }

        function computeAnalyticsFromActivities(items) {
            const data = {
                weekDeposits: 0,
                weekWithdrawals: 0,
                monthDeposits: 0,
                monthWithdrawals: 0,
                monthNet: 0,
                pendingCount: 0,
                completedDeposits: 0,
                completedWithdrawals: 0,
                weekNet: 0,
                weekPct: 0
            };

            const now = Date.now();
            const weekAgo = now - (7 * 24 * 60 * 60 * 1000);
            const monthAgo = now - (30 * 24 * 60 * 60 * 1000);

            (Array.isArray(items) ? items : []).forEach((item) => {
                const rawStatus = String(item.status_raw || '').toLowerCase();
                const isCompleted = rawStatus.includes('complete') || rawStatus.includes('settled') || rawStatus.includes('success') || rawStatus.includes('approved');
                const isPending = rawStatus.includes('pending');
                const isWithdrawal = String(item.flow || '').toLowerCase() === 'out' || String(item.activity_type || '').toLowerCase() === 'withdrawal';
                const currency = String(item.currency || '').toUpperCase();
                const amount = Math.abs(parseFloat(item.amount || '0') || 0);

                if (isPending) {
                    data.pendingCount += 1;
                }

                if (!isCompleted || currency !== 'GBP') {
                    return;
                }

                if (isWithdrawal) {
                    data.completedWithdrawals += 1;
                } else {
                    data.completedDeposits += 1;
                }

                const createdMs = Date.parse(item.created_label || '') || Date.now();

                if (createdMs >= weekAgo) {
                    if (isWithdrawal) {
                        data.weekWithdrawals += amount;
                    } else {
                        data.weekDeposits += amount;
                    }
                }

                if (createdMs >= monthAgo) {
                    if (isWithdrawal) {
                        data.monthWithdrawals += amount;
                    } else {
                        data.monthDeposits += amount;
                    }
                }
            });

            data.weekNet = data.weekDeposits - data.weekWithdrawals;
            data.monthNet = data.monthDeposits - data.monthWithdrawals;
            const ref = Math.max(1, data.weekDeposits + data.weekWithdrawals);
            data.weekPct = (data.weekNet / ref) * 100;
            return data;
        }

        function applyRealtimeAnalytics(analytics) {
            if (!analytics || typeof analytics !== 'object') {
                return;
            }

            const weekNetSign = analytics.weekNet < 0 ? '-' : '+';
            const weekNetClass = analytics.weekNet < 0 ? 'text-danger' : 'text-success';
            const monthNetSign = analytics.monthNet < 0 ? '-' : '+';

            const summaryEl = document.getElementById('analyticsSummaryValue');
            const modalNetEl = document.getElementById('analyticsModalNet');
            const modalPctEl = document.getElementById('analyticsModalPercent');
            const dep30El = document.getElementById('analyticsDeposits30d');
            const wdr30El = document.getElementById('analyticsWithdrawals30d');
            const pendingEl = document.getElementById('analyticsPendingCount');
            const depCountEl = document.getElementById('analyticsDepositCount');
            const wdrCountEl = document.getElementById('analyticsWithdrawalCount');
            const monthNetEl = document.getElementById('analyticsMonthNet');
            const depProgressEl = document.getElementById('analyticsDepProgress');
            const wdrProgressEl = document.getElementById('analyticsWdrProgress');

            const weekNetText = `${weekNetSign} £${Math.abs(analytics.weekNet).toFixed(2)}`;
            const pctText = `(${analytics.weekPct.toFixed(1)}%)`;

            if (summaryEl) {
                summaryEl.innerHTML = `${weekNetText} <span class="${weekNetClass}" style="font-size: 1rem;">${pctText}</span>`;
            }
            if (modalNetEl) {
                modalNetEl.textContent = weekNetText;
            }
            if (modalPctEl) {
                modalPctEl.textContent = `${analytics.weekPct.toFixed(1)}%`;
                modalPctEl.className = weekNetClass;
            }
            if (dep30El) dep30El.textContent = `£${analytics.monthDeposits.toFixed(2)}`;
            if (wdr30El) wdr30El.textContent = `£${analytics.monthWithdrawals.toFixed(2)}`;
            if (pendingEl) pendingEl.textContent = String(analytics.pendingCount || 0);
            if (depCountEl) depCountEl.textContent = String(analytics.completedDeposits || 0);
            if (wdrCountEl) wdrCountEl.textContent = String(analytics.completedWithdrawals || 0);
            if (monthNetEl) {
                monthNetEl.textContent = `${monthNetSign}£${Math.abs(analytics.monthNet).toFixed(2)}`;
                monthNetEl.className = analytics.monthNet < 0 ? 'text-danger' : 'text-success';
            }

            const totalFlow = Math.max(1, analytics.monthDeposits + analytics.monthWithdrawals);
            if (depProgressEl) depProgressEl.style.width = `${(analytics.monthDeposits / totalFlow) * 100}%`;
            if (wdrProgressEl) wdrProgressEl.style.width = `${(analytics.monthWithdrawals / totalFlow) * 100}%`;
        }

        function emitImportantActivityUpdates(nextAll) {
            if (!Array.isArray(nextAll)) {
                return;
            }

            const nextMap = {};
            nextAll.forEach((item) => {
                const id = String(item.activity_id || '');
                const status = String(item.status_raw || '').toLowerCase();
                if (id !== '') {
                    nextMap[id] = status;
                }
            });

            const isFirstSync = Object.keys(lastActivityStatusMap).length === 0;
            if (isFirstSync) {
                lastActivityStatusMap = nextMap;
                return;
            }

            nextAll.forEach((item) => {
                const id = String(item.activity_id || '');
                if (id === '') {
                    return;
                }

                const current = String(item.status_raw || '').toLowerCase();
                const previous = String(lastActivityStatusMap[id] || '');
                if (previous === '' || previous === current) {
                    return;
                }

                const label = String(item.label || item.activity_type || 'Activity');
                const amount = String(item.amount || '0.00');
                const currency = String(item.currency || 'GBP');

                if (current.includes('complete') || current.includes('settled') || current.includes('success') || current.includes('approved')) {
                    notifyActivity(label + ' settled: ' + currency + ' ' + amount, 'success', 'Activity Settled');
                } else if (current.includes('fail') || current.includes('reverse') || current.includes('expired')) {
                    notifyActivity(label + ' ended with status ' + current + '.', 'error', 'Activity Update', 4200);
                }
            });

            lastActivityStatusMap = nextMap;
        }

            function applyRealtimeBalance(balance) {
                if (!balance || typeof balance !== 'object') {
                    return;
                }

                const sign = String(balance.sign || '');
                const major = String(balance.major || '0');
                const minor = String(balance.minor || '00');

                const totalSignEl = document.getElementById('totalBalanceSign');
                const totalMajorEl = document.getElementById('totalBalanceMajor');
                const totalMinorEl = document.getElementById('totalBalanceMinor');
                const primaryAccountEl = document.getElementById('primaryAccountBalance');

                if (totalSignEl) totalSignEl.textContent = sign;
                if (totalMajorEl) totalMajorEl.textContent = major;
                if (totalMinorEl) totalMinorEl.textContent = minor;
                if (primaryAccountEl) primaryAccountEl.textContent = sign + '£' + major + '.' + minor;
            }

        async function refreshActivityRealtime() {
            try {
                const response = await apiCall('/deposits/list.php?limit=500', 'GET');
                const nextAll = response.data && Array.isArray(response.data.all) ? response.data.all : [];
                const nextRecent = response.data && Array.isArray(response.data.recent) ? response.data.recent : [];
                const nextBalance = response.data && response.data.balance ? response.data.balance : null;
                const signature = buildActivitySignature(nextAll) + '|' + (nextBalance && nextBalance.formatted ? nextBalance.formatted : '0.00');

                if (signature === lastActivitySignature) {
                    return;
                }

                emitImportantActivityUpdates(nextAll);

                lastActivitySignature = signature;
                ALL_ACTIVITIES = nextAll;
                renderRecentActivityList(nextRecent);
                applyRealtimeBalance(nextBalance);
                applyRealtimeAnalytics(computeAnalyticsFromActivities(nextAll));

                const allActivityModalEl = document.getElementById('allActivityModal');
                if (allActivityModalEl && allActivityModalEl.classList.contains('show')) {
                    resetAllActivityFeed();
                }
            } catch (error) {
                // Keep UI stable on temporary network/API errors.
            }
        }

        function startActivityRealtimePolling() {
            refreshActivityRealtime();
            if (activityPollTimer) {
                clearInterval(activityPollTimer);
            }

            activityPollTimer = setInterval(function () {
                if (document.visibilityState === 'hidden') {
                    return;
                }
                refreshActivityRealtime();
            }, 6000);
        }

        function appendMoreActivities(batchSize) {
            if (isAppendingActivities) {
                return;
            }

            const listEl = document.getElementById('allActivityList');
            const loadingEl = document.getElementById('allActivityLoading');
            const endEl = document.getElementById('allActivityEnd');

            if (!listEl || !loadingEl || !endEl) {
                return;
            }

            if (renderedActivityCount >= ALL_ACTIVITIES.length) {
                loadingEl.style.display = 'none';
                endEl.style.display = ALL_ACTIVITIES.length > 0 ? 'block' : 'none';
                return;
            }

            isAppendingActivities = true;
            loadingEl.style.display = 'block';

            const nextCount = Math.min(batchSize, ALL_ACTIVITIES.length - renderedActivityCount);
            for (let i = 0; i < nextCount; i += 1) {
                const item = ALL_ACTIVITIES[renderedActivityCount + i];
                listEl.appendChild(createAllActivityRow(item));
            }

            renderedActivityCount += nextCount;
            loadingEl.style.display = 'none';
            endEl.style.display = renderedActivityCount >= ALL_ACTIVITIES.length && ALL_ACTIVITIES.length > 0 ? 'block' : 'none';
            isAppendingActivities = false;
        }

        function resetAllActivityFeed() {
            const listEl = document.getElementById('allActivityList');
            const loadingEl = document.getElementById('allActivityLoading');
            const endEl = document.getElementById('allActivityEnd');
            const countEl = document.getElementById('allActivityCount');

            if (!listEl || !loadingEl || !endEl || !countEl) {
                return;
            }

            listEl.innerHTML = '';
            renderedActivityCount = 0;
            loadingEl.style.display = 'none';
            endEl.style.display = 'none';

            if (ALL_ACTIVITIES.length === 0) {
                listEl.innerHTML = `
                    <div class="asset-row" style="padding: 0.95rem 1rem; cursor: default;">
                        <div class="asset-icon" style="background: var(--icon-bg-default); width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-clock"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 0.95rem;">No recent activity</div>
                            <div class="asset-sub">Activity will show here once transactions are created.</div>
                        </div>
                    </div>
                `;
                countEl.textContent = 'Most recent first';
                return;
            }

            countEl.textContent = `${ALL_ACTIVITIES.length} total • Most recent first`;
            appendMoreActivities(INITIAL_ACTIVITY_BATCH);
        }

        const allActivityModal = document.getElementById('allActivityModal');
        const allActivityBody = document.getElementById('allActivityBody');

        if (allActivityModal) {
            allActivityModal.addEventListener('show.bs.offcanvas', function() {
                resetAllActivityFeed();
            });
        }

        if (allActivityBody) {
            allActivityBody.addEventListener('scroll', function() {
                const nearBottom = allActivityBody.scrollTop + allActivityBody.clientHeight >= allActivityBody.scrollHeight - 40;
                if (nearBottom && renderedActivityCount < ALL_ACTIVITIES.length) {
                    appendMoreActivities(NEXT_ACTIVITY_BATCH);
                }
            });
        }

        const activityDetailsModal = document.getElementById('activityDetailsModal');
        if (activityDetailsModal) {
            function setActivityDetailsLoading(isLoading) {
                const fields = [
                    'activityDetailsLabel',
                    'activityDetailsStatus',
                    'activityDetailsAmount',
                    'activityDetailsAmountSub',
                    'activityDetailsMethod',
                    'activityDetailsCreated',
                    'activityDetailsCompleted',
                    'activityDetailsAuditText',
                ];

                fields.forEach((id) => {
                    const el = document.getElementById(id);
                    if (!el) {
                        return;
                    }

                    if (isLoading) {
                        el.dataset.previousText = el.textContent || '';
                        el.textContent = ' '; 
                        el.style.borderRadius = '8px';
                        el.style.background = 'linear-gradient(90deg, rgba(148,163,184,0.08) 0%, rgba(148,163,184,0.22) 50%, rgba(148,163,184,0.08) 100%)';
                        el.style.backgroundSize = '220% 100%';
                        el.style.animation = 'activityShimmer 1s ease-in-out infinite';
                        el.style.color = 'transparent';
                    } else {
                        el.style.background = 'transparent';
                        el.style.backgroundSize = '';
                        el.style.animation = '';
                        el.style.color = '';
                    }
                });

                const styleId = 'activityShimmerStyle';
                if (isLoading && !document.getElementById(styleId)) {
                    const style = document.createElement('style');
                    style.id = styleId;
                    style.textContent = '@keyframes activityShimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }';
                    document.head.appendChild(style);
                }
            }

            function playActivityDetailsEntrance() {
                const sequence = [
                    { id: 'activityDetailsHeroCard', delay: 0 },
                    { id: 'activityDetailsMetaCard', delay: 80 },
                    { id: 'activityDetailsAuditNote', delay: 160 },
                ];

                sequence.forEach(({ id, delay }) => {
                    const el = document.getElementById(id);
                    if (!el) {
                        return;
                    }

                    el.style.transition = 'none';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(10px)';

                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            el.style.transition = 'opacity 320ms ease, transform 360ms cubic-bezier(0.2, 0.8, 0.2, 1)';
                            el.style.opacity = '1';
                            el.style.transform = 'translateY(0)';
                        }, delay);
                    });
                });
            }

            activityDetailsModal.addEventListener('show.bs.offcanvas', function(event) {
                const trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                setActivityDetailsLoading(true);

                setTimeout(() => {
                    setActivityDetailsLoading(false);

                    document.getElementById('activityDetailsType').textContent = trigger.getAttribute('data-activity-type') || 'Activity';
                    document.getElementById('activityDetailsLabel').textContent = trigger.getAttribute('data-activity-label') || 'N/A';
                    const rawStatus = (trigger.getAttribute('data-activity-status') || 'N/A').toLowerCase();
                    const statusEl = document.getElementById('activityDetailsStatus');
                    let statusLabel = 'Pending';

                    if (rawStatus.includes('complete')) {
                        statusLabel = 'Completed';
                    } else if (rawStatus.includes('fail')) {
                        statusLabel = 'Failed';
                    } else if (rawStatus.includes('reverse')) {
                        statusLabel = 'Reversed';
                    } else if (rawStatus.includes('pending')) {
                        // Keep all pending variants under one clean status label.
                        statusLabel = 'Pending';
                    }

                    statusEl.textContent = statusLabel;
                    let amountTone = '#f59e0b';
                    let amountSubText = 'Awaiting settlement';
                    let auditText = 'This activity is currently processing. Final wallet balance updates are applied once settlement completes.';

                    if (rawStatus.includes('complete')) {
                        statusEl.style.color = '#10b981';
                        statusEl.style.background = 'rgba(16, 185, 129, 0.12)';
                        statusEl.style.borderColor = 'rgba(16, 185, 129, 0.35)';
                        amountTone = '#10b981';
                        amountSubText = 'Successfully settled';
                        auditText = 'This activity is finalized and reflected in your wallet balance and records.';
                    } else if (rawStatus.includes('fail') || rawStatus.includes('reverse')) {
                        statusEl.style.color = '#ef4444';
                        statusEl.style.background = 'rgba(239, 68, 68, 0.12)';
                        statusEl.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                        amountTone = '#ef4444';
                        amountSubText = 'Action required';
                        auditText = 'This activity did not complete successfully. You can retry from the relevant funding flow.';
                    } else if (rawStatus.includes('pending')) {
                        statusEl.style.color = '#f59e0b';
                        statusEl.style.background = 'rgba(245, 158, 11, 0.14)';
                        statusEl.style.borderColor = 'rgba(245, 158, 11, 0.35)';
                        amountTone = '#f59e0b';
                        amountSubText = 'Awaiting settlement';
                        auditText = 'This activity is pending confirmation and may take a short while to complete.';
                    } else {
                        statusEl.style.color = 'var(--text-secondary)';
                        statusEl.style.background = 'rgba(107, 114, 128, 0.12)';
                        statusEl.style.borderColor = 'var(--border-light)';
                        amountSubText = 'In progress';
                        auditText = 'Status updates are in progress. Refresh shortly to see the latest state.';
                    }

                    document.getElementById('activityDetailsMethod').textContent = trigger.getAttribute('data-activity-method') || 'N/A';

                    const amount = trigger.getAttribute('data-activity-amount') || '0.00';
                    const flow = String(trigger.getAttribute('data-activity-flow') || '').toLowerCase();
                    const isOutflow = flow === 'out' || String(trigger.getAttribute('data-activity-type') || '').toLowerCase() === 'withdrawal';
                    const currency = trigger.getAttribute('data-activity-currency') || 'GBP';
                    const amountEl = document.getElementById('activityDetailsAmount');
                    amountEl.textContent = `${isOutflow ? '-' : '+'} ${currency} ${amount}`;
                    amountEl.style.color = amountTone;
                    const amountSubEl = document.getElementById('activityDetailsAmountSub');
                    if (amountSubEl) {
                        amountSubEl.textContent = amountSubText;
                    }

                    const iconWrap = document.getElementById('activityDetailsIconWrap');
                    const icon = document.getElementById('activityDetailsIcon');
                    const iconClass = trigger.getAttribute('data-activity-icon') || 'fas fa-list';
                    icon.className = iconClass;

                    if (iconClass.includes('fa-university')) {
                        iconWrap.style.background = 'rgba(59, 130, 246, 0.12)';
                        iconWrap.style.color = '#3b82f6';
                    } else if (iconClass.includes('fa-credit-card')) {
                        iconWrap.style.background = 'rgba(16, 185, 129, 0.12)';
                        iconWrap.style.color = '#10b981';
                    } else if (iconClass.includes('fa-apple')) {
                        iconWrap.style.background = 'rgba(17, 24, 39, 0.10)';
                        iconWrap.style.color = 'var(--text-primary)';
                    } else {
                        iconWrap.style.background = 'var(--icon-bg-default)';
                        iconWrap.style.color = 'var(--text-primary)';
                    }

                    document.getElementById('activityDetailsCreated').textContent = trigger.getAttribute('data-activity-created') || 'N/A';
                    document.getElementById('activityDetailsCompleted').textContent = trigger.getAttribute('data-activity-completed') || 'N/A';

                    const auditTextEl = document.getElementById('activityDetailsAuditText');
                    if (auditTextEl) {
                        auditTextEl.textContent = auditText;
                    }

                    playActivityDetailsEntrance();
                }, 120);
            });
        }

        updateDepositButton();
        applyRealtimeAnalytics(computeAnalyticsFromActivities(ALL_ACTIVITIES));
        startActivityRealtimePolling();

        window.addEventListener('beforeunload', function () {
            if (activityPollTimer) {
                clearInterval(activityPollTimer);
            }
        });
    </script>
</body>
</html>

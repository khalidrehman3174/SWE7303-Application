<?php
$pageTitle = 'FinPay Pro - Asset Details';
$activePage = 'assets';
require_once 'templates/head.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/available_balance.php';

$asset = isset($_GET['asset']) ? strtoupper(trim((string)$_GET['asset'])) : 'BTC';
$asset = preg_replace('/[^A-Z0-9]/', '', $asset);
if ($asset === '' || strlen($asset) > 10) {
    $asset = 'BTC';
}

$assetMeta = [
    'GBP' => ['name' => 'British Pound', 'type' => 'Fiat Currency', 'icon' => 'fas fa-pound-sign', 'color' => '#3b82f6', 'description' => 'The base fiat currency in your FinPay account for deposits, withdrawals, and settlements.'],
    'BTC' => ['name' => 'Bitcoin', 'type' => 'Digital Asset', 'icon' => 'fab fa-bitcoin', 'color' => '#f59e0b', 'description' => 'Bitcoin is the original decentralized digital currency and a core store-of-value asset.'],
    'ETH' => ['name' => 'Ethereum', 'type' => 'Smart Contract Asset', 'icon' => 'fab fa-ethereum', 'color' => '#6366f1', 'description' => 'Ethereum powers decentralized applications and programmable on-chain transactions.'],
    'USDT' => ['name' => 'Tether', 'type' => 'Stablecoin', 'icon' => 'fas fa-coins', 'color' => '#26A17B', 'description' => 'USDT is a USD-pegged stablecoin used for trading liquidity and capital preservation.'],
    'BNB' => ['name' => 'BNB', 'type' => 'Exchange Utility Asset', 'icon' => 'fas fa-cube', 'color' => '#eab308', 'description' => 'BNB is the utility asset of the Binance ecosystem and related chain networks.'],
    'SOL' => ['name' => 'Solana', 'type' => 'Layer 1 Asset', 'icon' => 'fas fa-bolt', 'color' => '#10b981', 'description' => 'Solana is a high-throughput blockchain focused on low-latency transactions.'],
    'XRP' => ['name' => 'XRP', 'type' => 'Payment Asset', 'icon' => 'fas fa-water', 'color' => '#475569', 'description' => 'XRP is used in fast cross-border value transfer and payment infrastructure use cases.'],
];

$meta = $assetMeta[$asset] ?? [
    'name' => $asset,
    'type' => 'Digital Asset',
    'icon' => 'fas fa-coins',
    'color' => '#64748b',
    'description' => 'A tracked asset in your FinPay portfolio.',
];

$userId = (int)$_SESSION['user_id'];
$amount = 0.0;
if ($asset === 'GBP') {
    $balancePayload = finpay_get_available_balance_gbp($dbc, $userId);
    $amount = (float)($balancePayload['amount'] ?? 0.0);
} else {
    $stmt = mysqli_prepare($dbc, 'SELECT balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $userId, $asset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if ($row && isset($row['balance'])) {
            $amount = (float)$row['balance'];
        }
    }
}

$amountDisplay = number_format($amount, $asset === 'GBP' ? 2 : 6);

$btcSwapAmount = 0.0;
$ethSwapAmount = 0.0;
$swapStmt = mysqli_prepare($dbc, 'SELECT symbol, balance FROM wallets WHERE user_id = ? AND symbol IN (\'BTC\', \'ETH\')');
if ($swapStmt) {
    mysqli_stmt_bind_param($swapStmt, 'i', $userId);
    mysqli_stmt_execute($swapStmt);
    $swapResult = mysqli_stmt_get_result($swapStmt);
    if ($swapResult) {
        while ($swapRow = mysqli_fetch_assoc($swapResult)) {
            $swapSymbol = strtoupper((string)($swapRow['symbol'] ?? ''));
            $swapBalance = (float)($swapRow['balance'] ?? 0.0);
            if ($swapSymbol === 'BTC') {
                $btcSwapAmount = $swapBalance;
            }
            if ($swapSymbol === 'ETH') {
                $ethSwapAmount = $swapBalance;
            }
        }
    }
    mysqli_stmt_close($swapStmt);
}

$swapGbpPayload = finpay_get_available_balance_gbp($dbc, $userId);
$swapGbpAmount = (float)($swapGbpPayload['amount'] ?? 0.0);

function asset_details_table_exists(mysqli $dbc, string $table): bool
{
    $safe = trim($table);
    if ($safe === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $safe)) {
        return false;
    }

    $stmt = mysqli_prepare(
        $dbc,
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $safe);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result ? (mysqli_fetch_row($result) !== null) : false;
    mysqli_stmt_close($stmt);

    return $exists;
}

function asset_details_table_columns(mysqli $dbc, string $table): array
{
    $safe = trim($table);
    if ($safe === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $safe)) {
        return [];
    }

    $columns = [];
    $query = 'SHOW COLUMNS FROM `' . $safe . '`';
    $result = mysqli_query($dbc, $query);
    if (!$result) {
        return [];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '') {
            $columns[] = $field;
        }
    }

    return $columns;
}

function asset_details_first_existing_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function asset_details_fetch_activity_seed(mysqli $dbc, int $userId, string $asset, int $limit = 24): array
{
    $out = [
        'alerts' => [],
        'history' => [],
    ];

    if (!asset_details_table_exists($dbc, 'transactions')) {
        return $out;
    }

    $columns = asset_details_table_columns($dbc, 'transactions');
    if (empty($columns)) {
        return $out;
    }

    $userCol = asset_details_first_existing_column($columns, ['user_id']);
    $symbolCol = asset_details_first_existing_column($columns, ['symbol', 'asset', 'currency']);
    $typeCol = asset_details_first_existing_column($columns, ['type', 'transaction_type', 'category']);
    $amountCol = asset_details_first_existing_column($columns, ['amount', 'net_amount']);
    $statusCol = asset_details_first_existing_column($columns, ['status', 'state']);
    $descriptionCol = asset_details_first_existing_column($columns, ['description', 'note', 'narration']);
    $createdCol = asset_details_first_existing_column($columns, ['created_at', 'updated_at', 'timestamp']);
    $idCol = asset_details_first_existing_column($columns, ['id', 'transaction_id']);

    if ($userCol === null || $symbolCol === null || $typeCol === null || $amountCol === null || $statusCol === null || $descriptionCol === null) {
        return $out;
    }

    $orderCol = $createdCol !== null ? $createdCol : ($idCol !== null ? $idCol : null);
    if ($orderCol === null) {
        return $out;
    }

    $sql = 'SELECT * FROM `transactions` WHERE ' . $userCol . ' = ? AND UPPER(' . $symbolCol . ') = ? ORDER BY ' . $orderCol . ' DESC LIMIT ?';
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return $out;
    }

    $assetUpper = strtoupper($asset);
    mysqli_stmt_bind_param($stmt, 'isi', $userId, $assetUpper, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $type = strtolower(trim((string)($row[$typeCol] ?? 'activity')));
            $status = strtolower(trim((string)($row[$statusCol] ?? 'pending')));
            $amountRaw = (float)($row[$amountCol] ?? 0.0);
            $amountAbs = abs($amountRaw);
            $amountPrecision = strtoupper($assetUpper) === 'GBP' ? 2 : 6;
            $amountDisplay = number_format($amountAbs, $amountPrecision, '.', '');
            $description = trim((string)($row[$descriptionCol] ?? ''));
            $createdAt = $createdCol !== null ? trim((string)($row[$createdCol] ?? '')) : '';

            $kind = 'activity';
            if (strpos($type, 'withdraw') !== false) {
                $kind = 'withdrawal';
            } elseif (strpos($type, 'deposit') !== false) {
                $kind = 'deposit';
            } elseif (strpos($type, 'reward') !== false) {
                $kind = 'reward';
            }

            $title = ucwords(str_replace(['_', '-'], ' ', $type));
            if ($title === '') {
                $title = 'Asset Activity';
            }

            $direction = $amountRaw < 0 ? '-' : '+';
            $messageParts = [];
            if ($amountAbs > 0) {
                $messageParts[] = $direction . $amountDisplay . ' ' . $assetUpper;
            }
            if ($description !== '') {
                $messageParts[] = $description;
            }
            $messageParts[] = 'Status: ' . ($status !== '' ? strtoupper($status) : 'PENDING');

            $historyItem = [
                'kind' => $kind,
                'title' => $title,
                'message' => implode(' - ', $messageParts),
                'ts' => $createdAt !== '' ? $createdAt : date('c'),
                'details' => [
                    'source' => 'transactions',
                    'status' => strtoupper($status !== '' ? $status : 'pending'),
                    'amount' => ($direction . $amountDisplay . ' ' . $assetUpper),
                    'method' => $title,
                    'reference' => '--',
                    'time' => ($createdAt !== '' ? $createdAt : date('c')),
                    'asset' => $assetUpper,
                ],
            ];

            $out['history'][] = $historyItem;

            if (in_array($status, ['pending', 'processing', 'failed', 'rejected', 'cancelled', 'error'], true)) {
                $level = in_array($status, ['failed', 'rejected', 'cancelled', 'error'], true) ? 'error' : 'warning';
                $out['alerts'][] = [
                    'level' => $level,
                    'title' => $title,
                    'message' => implode(' - ', $messageParts),
                    'ts' => $createdAt !== '' ? $createdAt : date('c'),
                ];
            }
        }
    }

    mysqli_stmt_close($stmt);

    if (asset_details_table_exists($dbc, 'deposits')) {
        $depositColumns = asset_details_table_columns($dbc, 'deposits');
        $depUserCol = asset_details_first_existing_column($depositColumns, ['user_id']);
        $depSymbolCol = asset_details_first_existing_column($depositColumns, ['currency', 'asset', 'symbol']);
        $depAmountCol = asset_details_first_existing_column($depositColumns, ['net_amount', 'amount']);
        $depStatusCol = asset_details_first_existing_column($depositColumns, ['status', 'state']);
        $depMethodCol = asset_details_first_existing_column($depositColumns, ['method', 'provider', 'channel']);
        $depRefCol = asset_details_first_existing_column($depositColumns, ['reference', 'deposit_id', 'public_id', 'id']);
        $depCreatedCol = asset_details_first_existing_column($depositColumns, ['created_at', 'updated_at', 'timestamp']);
        $depIdCol = asset_details_first_existing_column($depositColumns, ['id', 'deposit_id', 'public_id']);

        if ($depUserCol !== null && $depSymbolCol !== null && $depAmountCol !== null && $depStatusCol !== null) {
            $depOrderCol = $depCreatedCol !== null ? $depCreatedCol : $depIdCol;
            if ($depOrderCol !== null) {
                $depSql = 'SELECT * FROM `deposits` WHERE ' . $depUserCol . ' = ? AND UPPER(' . $depSymbolCol . ') = ? ORDER BY ' . $depOrderCol . ' DESC LIMIT ?';
                $depStmt = mysqli_prepare($dbc, $depSql);

                if ($depStmt) {
                    mysqli_stmt_bind_param($depStmt, 'isi', $userId, $assetUpper, $limit);
                    mysqli_stmt_execute($depStmt);
                    $depResult = mysqli_stmt_get_result($depStmt);

                    if ($depResult) {
                        while ($dep = mysqli_fetch_assoc($depResult)) {
                            $depStatus = strtolower(trim((string)($dep[$depStatusCol] ?? 'pending')));
                            $depAmountRaw = (float)($dep[$depAmountCol] ?? 0.0);
                            $depAmountAbs = abs($depAmountRaw);
                            $depAmountPrecision = strtoupper($assetUpper) === 'GBP' ? 2 : 6;
                            $depAmountDisplay = number_format($depAmountAbs, $depAmountPrecision, '.', '');
                            $depCreatedAt = $depCreatedCol !== null ? trim((string)($dep[$depCreatedCol] ?? '')) : '';
                            $depMethod = $depMethodCol !== null ? trim((string)($dep[$depMethodCol] ?? '')) : '';
                            $depReference = $depRefCol !== null ? trim((string)($dep[$depRefCol] ?? '')) : '';

                            $depMessageParts = [];
                            if ($depAmountAbs > 0) {
                                $depMessageParts[] = '+' . $depAmountDisplay . ' ' . $assetUpper;
                            }
                            if ($depMethod !== '') {
                                $depMessageParts[] = 'Method: ' . $depMethod;
                            }
                            if ($depReference !== '') {
                                $depMessageParts[] = 'Ref: ' . $depReference;
                            }
                            $depMessageParts[] = 'Status: ' . strtoupper($depStatus !== '' ? $depStatus : 'pending');

                            $out['history'][] = [
                                'kind' => 'deposit',
                                'title' => 'Deposit',
                                'message' => implode(' - ', $depMessageParts),
                                'ts' => $depCreatedAt !== '' ? $depCreatedAt : date('c'),
                                'details' => [
                                    'source' => 'deposits',
                                    'status' => strtoupper($depStatus !== '' ? $depStatus : 'pending'),
                                    'amount' => '+' . $depAmountDisplay . ' ' . $assetUpper,
                                    'method' => ($depMethod !== '' ? $depMethod : '--'),
                                    'reference' => ($depReference !== '' ? $depReference : '--'),
                                    'time' => ($depCreatedAt !== '' ? $depCreatedAt : date('c')),
                                    'asset' => $assetUpper,
                                ],
                            ];

                            if (in_array($depStatus, ['pending', 'processing', 'failed', 'rejected', 'cancelled', 'error'], true)) {
                                $depLevel = in_array($depStatus, ['failed', 'rejected', 'cancelled', 'error'], true) ? 'error' : 'warning';
                                $out['alerts'][] = [
                                    'level' => $depLevel,
                                    'title' => 'Deposit Update',
                                    'message' => implode(' - ', $depMessageParts),
                                    'ts' => $depCreatedAt !== '' ? $depCreatedAt : date('c'),
                                ];
                            }
                        }
                    }

                    mysqli_stmt_close($depStmt);
                }
            }
        }
    }

    return $out;
}

$assetFeedSeed = asset_details_fetch_activity_seed($dbc, $userId, $asset, 24);

$assetNetworks = [
    'BTC' => [
        ['id' => 'bitcoin', 'name' => 'Bitcoin Testnet', 'tag' => 'Active', 'eta' => '10-30 mins'],
    ],
    'ETH' => [
        ['id' => 'ethereum', 'name' => 'Ethereum', 'tag' => 'ERC-20', 'eta' => '2-8 mins'],
        ['id' => 'arbitrum', 'name' => 'Arbitrum', 'tag' => 'L2', 'eta' => '< 3 mins'],
    ],
    'USDT' => [
        ['id' => 'ethereum', 'name' => 'Ethereum', 'tag' => 'ERC-20', 'eta' => '2-8 mins'],
        ['id' => 'tron', 'name' => 'TRON', 'tag' => 'TRC-20', 'eta' => '< 2 mins'],
        ['id' => 'bsc', 'name' => 'BNB Chain', 'tag' => 'BEP-20', 'eta' => '< 3 mins'],
    ],
    'BNB' => [
        ['id' => 'bsc', 'name' => 'BNB Chain', 'tag' => 'BEP-20', 'eta' => '< 3 mins'],
    ],
    'SOL' => [
        ['id' => 'solana', 'name' => 'Solana', 'tag' => 'Native', 'eta' => '< 2 mins'],
    ],
    'XRP' => [
        ['id' => 'xrp', 'name' => 'XRP Ledger', 'tag' => 'Native', 'eta' => '< 2 mins'],
    ],
];

$availableNetworks = $assetNetworks[$asset] ?? [
    ['id' => 'mainnet', 'name' => 'Mainnet', 'tag' => 'Default', 'eta' => '2-10 mins'],
];
?>
<body>
    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">
        <header class="mobile-header">
            <a class="profile-btn" href="assets.php"><i class="fas fa-chevron-left"></i></a>
            <div style="font-weight: 700; letter-spacing: 1px;">ASSET DETAILS</div>
            <div class="profile-btn"><i class="fas fa-ellipsis-h"></i></div>
        </header>

        <div class="px-3 px-lg-5 pt-lg-5 pb-4">
            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <a href="assets.php" class="asset-back-btn"><i class="fas fa-chevron-left"></i><span>Assets</span></a>
                <div class="d-flex gap-2">
                    <button class="btn-pro btn-pro-secondary" style="max-width: 170px;" data-bs-toggle="offcanvas" data-bs-target="#assetHistoryModal" aria-controls="assetHistoryModal"><i class="fas fa-clock-rotate-left"></i> History</button>
                    <button class="btn-pro btn-pro-primary" style="max-width: 170px;" data-bs-toggle="offcanvas" data-bs-target="#assetAlertsModal" aria-controls="assetAlertsModal"><i class="fas fa-bell"></i> Alerts</button>
                </div>
            </div>

            <section class="glass-panel p-4 p-lg-5 mb-4" style="border-radius: 28px; position: relative; overflow: hidden;">
                <div style="position:absolute; inset:auto -70px -80px auto; width:240px; height:240px; border-radius:50%; background: radial-gradient(circle, <?php echo htmlspecialchars($meta['color'], ENT_QUOTES, 'UTF-8'); ?> 0%, transparent 68%); opacity:0.2; filter: blur(6px);"></div>
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-start align-items-lg-center" style="position: relative; z-index: 1;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:64px; height:64px; border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:1.8rem; background: rgba(255,255,255,0.75); color: <?php echo htmlspecialchars($meta['color'], ENT_QUOTES, 'UTF-8'); ?>; border:1px solid var(--border-light);">
                            <i class="<?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        </div>
                        <div>
                            <div id="coin-name" style="font-size:1.7rem; font-weight:800; letter-spacing:-0.3px;"><?php echo htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="asset-sub" style="font-size:0.95rem; margin-top:2px;"><span id="coin-symbol"><?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></span> • <?php echo htmlspecialchars($meta['type'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="asset-inline-actions asset-inline-actions-left mt-3">
                                <button class="asset-inline-btn is-primary" data-bs-toggle="offcanvas" data-bs-target="#assetDepositModal" aria-controls="assetDepositModal" aria-label="Deposit" title="Deposit"><i class="fas fa-arrow-down"></i><span class="asset-inline-label">Deposit</span></button>
                                <button class="asset-inline-btn" data-bs-toggle="offcanvas" data-bs-target="#assetWithdrawModal" aria-controls="assetWithdrawModal" aria-label="Withdraw" title="Withdraw"><i class="fas fa-arrow-up"></i><span class="asset-inline-label">Withdraw</span></button>
                                <button class="asset-inline-btn" data-bs-toggle="offcanvas" data-bs-target="#swapModal" aria-controls="swapModal" aria-label="Swap" title="Swap"><i class="fas fa-exchange-alt"></i><span class="asset-inline-label">Swap</span></button>
                            </div>
                        </div>
                    </div>

                    <div class="text-lg-end asset-hero-right">
                        <div id="market-price" class="d-none d-lg-block" style="font-size:2rem; font-weight:800; line-height:1;">$0.00</div>
                        <div id="daily-change" class="asset-sub d-none d-lg-block" style="font-weight:700; margin-top:8px; color: var(--text-secondary);">24h change loading...</div>

                        <div class="asset-mobile-holding d-lg-none">
                            <div class="asset-mobile-holding-label">You Hold</div>
                            <div class="asset-mobile-holding-value"><?php echo htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="asset-inline-actions asset-inline-actions-mobile d-lg-none mt-3">
                            <button class="asset-inline-btn is-primary" data-bs-toggle="offcanvas" data-bs-target="#assetDepositModal" aria-controls="assetDepositModal" aria-label="Deposit" title="Deposit"><i class="fas fa-arrow-down"></i><span class="asset-inline-label">Deposit</span></button>
                            <button class="asset-inline-btn" data-bs-toggle="offcanvas" data-bs-target="#assetWithdrawModal" aria-controls="assetWithdrawModal" aria-label="Withdraw" title="Withdraw"><i class="fas fa-arrow-up"></i><span class="asset-inline-label">Withdraw</span></button>
                            <button class="asset-inline-btn" data-bs-toggle="offcanvas" data-bs-target="#swapModal" aria-controls="swapModal" aria-label="Swap" title="Swap"><i class="fas fa-exchange-alt"></i><span class="asset-inline-label">Swap</span></button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-3 g-lg-4 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-panel p-4" style="border-radius:20px; height:100%;">
                        <div class="asset-sub text-uppercase" style="letter-spacing:1px;">You Hold</div>
                        <div id="crypto-balance" data-amount="<?php echo htmlspecialchars((string)$amount, ENT_QUOTES, 'UTF-8'); ?>" style="font-size:1.5rem; font-weight:800; margin-top:8px;"><?php echo htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-panel p-4" style="border-radius:20px; height:100%;">
                        <div class="asset-sub text-uppercase" style="letter-spacing:1px;">Current Value</div>
                        <div id="fiat-balance" style="font-size:1.5rem; font-weight:800; margin-top:8px;">$0.00</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-panel p-4" style="border-radius:20px; height:100%;">
                        <div class="asset-sub text-uppercase" style="letter-spacing:1px;">24H Volume</div>
                        <div id="trading-volume" style="font-size:1.5rem; font-weight:800; margin-top:8px;">$0.00</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="glass-panel p-4" style="border-radius:20px; height:100%;">
                        <div class="asset-sub text-uppercase" style="letter-spacing:1px;">24H Range</div>
                        <div id="day-range" style="font-size:1.15rem; font-weight:800; margin-top:10px;">$0.00 - $0.00</div>
                    </div>
                </div>
            </section>

            <section class="glass-panel p-4 p-lg-5 mb-4" style="border-radius:24px;">
                <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between mb-3">
                    <h3 class="section-heading mb-0">About This Asset</h3>
                    <span style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color: var(--text-secondary);">Live market estimates</span>
                </div>
                <p id="about-desc" class="mb-0" style="font-size:1rem; line-height:1.8; color: var(--text-secondary); max-width: 920px;"><?php echo htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        </div>
    </main>

    <?php require_once 'templates/bottom_nav.php'; ?>
    <style>
        .asset-inline-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
        }

        .asset-inline-actions-mobile {
            display: none;
        }

        .asset-mobile-holding {
            display: none;
        }

        .asset-inline-btn {
            border: 1px solid var(--border-light);
            background: linear-gradient(180deg, var(--bg-surface) 0%, var(--bg-surface-light) 100%);
            color: var(--text-primary);
            border-radius: 14px;
            min-height: 44px;
            padding: 0.55rem 0.9rem;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.22s ease;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .asset-inline-btn i {
            font-size: 0.78rem;
        }

        .asset-inline-label {
            line-height: 1;
        }

        .asset-inline-btn:hover {
            background: var(--hover-bg);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.1);
        }

        .asset-inline-btn.is-primary {
            border-color: transparent;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-color);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.2);
        }

        .asset-inline-btn.is-primary:hover {
            filter: brightness(0.98);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.24);
        }

        @media (max-width: 991.98px) {
            .asset-inline-actions-left {
                display: none;
            }

            .asset-hero-right {
                width: 100%;
                text-align: left;
            }

            .asset-mobile-holding {
                display: block;
            }

            .asset-mobile-holding-label {
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: var(--text-secondary);
                font-weight: 700;
                margin-bottom: 0.35rem;
            }

            .asset-mobile-holding-value {
                font-size: 1.35rem;
                font-weight: 800;
                line-height: 1.1;
                color: var(--text-primary);
            }

            .asset-inline-actions-mobile {
                display: flex;
                width: 100%;
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 0.5rem;
            }

            .asset-inline-btn {
                flex: 1 1 0;
                width: auto;
                min-height: 64px;
                padding: 0.55rem 0.4rem;
                justify-content: center;
                align-items: center;
                flex-direction: column;
                gap: 0.22rem;
                border-radius: 12px;
            }

            .asset-inline-label {
                display: block;
                font-size: 0.72rem;
                letter-spacing: 0.1px;
                text-align: center;
                line-height: 1.15;
            }

            .asset-inline-btn i {
                font-size: 0.82rem;
            }
        }

        @media (max-width: 575.98px) {
            .asset-inline-actions {
                gap: 0.5rem;
            }

            .asset-mobile-holding-value {
                font-size: 1.25rem;
            }
        }

        .transfer-flow {
            position: relative;
            padding: 1.65rem 1.05rem 8.5rem 1.05rem !important;
            gap: 1.2rem;
        }

        .transfer-panel {
            border: 1px solid var(--border-light);
            background: linear-gradient(180deg, rgba(255,255,255,0.58) 0%, rgba(255,255,255,0.35) 100%);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
        }

        @media (prefers-color-scheme: dark) {
            .transfer-panel {
                background: linear-gradient(180deg, rgba(255,255,255,0.045) 0%, rgba(255,255,255,0.02) 100%);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
            }
        }

        .transfer-section-title {
            font-size: 1.08rem;
            font-weight: 700;
            margin-bottom: 0.7rem;
        }

        .transfer-subline {
            font-size: 0.86rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .transfer-network-panel {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .network-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            margin-bottom: 0;
            max-height: min(52vh, 430px);
            overflow-y: auto;
            padding-right: 0.3rem;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
        }

        .network-list::-webkit-scrollbar {
            width: 8px;
        }

        .network-list::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.45);
            border-radius: 999px;
        }

        .network-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .network-row {
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            background: var(--bg-surface-light);
            color: var(--text-primary);
            padding: 1.05rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
            border-radius: 16px;
        }

        .network-row:hover {
            background: rgba(0, 210, 106, 0.03);
        }

        .network-row.is-selected {
            background: rgba(0, 210, 106, 0.05);
            border-color: var(--accent);
        }

        .network-main {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .network-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .network-eta {
            font-size: 0.77rem;
            color: var(--text-secondary);
        }

        .network-check {
            font-size: 1.15rem;
            color: var(--text-secondary);
        }

        .network-row.is-selected .network-check {
            color: var(--accent);
        }

        .network-warning-inline {
            margin-top: 0.9rem;
            border: 1px solid rgba(245, 158, 11, 0.4);
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(250,250,250,0.94) 100%);
            border-radius: 16px;
            padding: 0.95rem 0.95rem 1rem;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            box-shadow: 0 -6px 24px rgba(15, 23, 42, 0.12);
        }

        .network-warning-inline.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .network-warning-dock {
            position: absolute;
            left: 1.05rem;
            right: 1.05rem;
            bottom: 1rem;
            z-index: 12;
            margin-top: 0;
        }

        .network-warning-text {
            font-size: 0.83rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 0.72rem;
        }

        .network-warning-actions {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.72rem;
        }

        .network-warning-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
            user-select: none;
            cursor: pointer;
            margin-right: 0;
            width: fit-content;
        }

        .network-warning-toggle input {
            width: 15px;
            height: 15px;
            accent-color: #10b981;
        }

        @media (prefers-color-scheme: dark) {
            .network-warning-inline {
                background: linear-gradient(180deg, rgba(15, 23, 42, 0.92) 0%, rgba(15, 23, 42, 0.88) 100%);
                box-shadow: 0 -8px 26px rgba(0, 0, 0, 0.45);
            }
        }

        .network-arrow {
            font-size: 0.75rem;
            color: var(--text-secondary);
            opacity: 0.75;
        }

        .qr-panel {
            border: 1px solid var(--border-light);
            background: var(--bg-surface-light);
            border-radius: 16px;
            padding: 14px;
            margin-bottom: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-image-wrap {
            width: 128px;
            height: 128px;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qr-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .address-panel {
            border-radius: 14px;
            border: 1px dashed var(--border-light);
            background: var(--bg-surface-light);
            padding: 12px;
            margin-bottom: 0.2rem;
        }

        .transfer-cta {
            margin-top: 0.45rem;
        }

        .transfer-address-note {
            font-size: 0.79rem;
            color: var(--text-secondary);
            margin-top: 0.6rem;
        }

        .transfer-field {
            margin-bottom: 0.85rem;
        }

        .transfer-label {
            font-size: 0.78rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .transfer-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            background: var(--bg-surface-light);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .transfer-input:focus {
            border-color: rgba(16, 185, 129, 0.45);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        .transfer-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.84rem;
            color: var(--text-secondary);
            border-top: 1px dashed var(--border-light);
            padding-top: 0.65rem;
            margin-top: 0.2rem;
            margin-bottom: 0.8rem;
        }

        .withdraw-processing-card {
            border: 1px solid rgba(59, 130, 246, 0.28);
            background: linear-gradient(180deg, rgba(239, 246, 255, 0.9) 0%, rgba(248, 250, 252, 0.95) 100%);
            border-radius: 16px;
            padding: 0.95rem;
            margin-bottom: 0.9rem;
        }

        .withdraw-processing-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
        }

        .withdraw-processing-title {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .withdraw-processing-pill {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-radius: 999px;
            padding: 5px 8px;
            border: 1px solid rgba(59, 130, 246, 0.45);
            color: #1d4ed8;
            background: rgba(219, 234, 254, 0.8);
        }

        .withdraw-processing-list {
            border-top: 1px dashed rgba(59, 130, 246, 0.35);
            padding-top: 0.7rem;
            display: grid;
            gap: 0.55rem;
        }

        .withdraw-processing-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.8rem;
            font-size: 0.82rem;
        }

        .withdraw-processing-row .k {
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            font-size: 0.68rem;
            min-width: 100px;
        }

        .withdraw-processing-row .v {
            color: var(--text-primary);
            text-align: right;
            word-break: break-all;
            font-weight: 600;
        }

        .withdraw-processing-footnote {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 0.6rem;
            margin-bottom: 0;
            line-height: 1.45;
        }

        .asset-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .asset-back-btn i {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--hover-bg);
            font-size: 0.75rem;
        }

        .asset-back-btn:hover {
            color: var(--text-primary);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .transfer-back-btn {
            cursor: pointer;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            background: var(--bg-surface);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .transfer-back-btn:hover {
            background: var(--hover-bg);
            transform: translateY(-1px);
        }

        .transfer-mini-back {
            border: 0;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            padding: 6px 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s ease;
        }

        .transfer-mini-back:hover {
            color: var(--text-primary);
        }

        .asset-feed-wrap {
            display: grid;
            gap: 0.7rem;
        }

        .asset-feed-empty {
            border: 1px dashed var(--border-light);
            border-radius: 14px;
            padding: 1rem;
            color: var(--text-secondary);
            font-size: 0.84rem;
            text-align: center;
            background: var(--bg-surface-light);
        }

        .asset-alert-item,
        .asset-history-item {
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 0.82rem 0.9rem;
            background: var(--bg-surface-light);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }

        .asset-alert-head,
        .asset-history-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.7rem;
            margin-bottom: 0.36rem;
        }

        .asset-alert-title,
        .asset-history-title {
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.35;
        }

        .asset-alert-meta,
        .asset-history-meta {
            font-size: 0.73rem;
            color: var(--text-secondary);
            font-weight: 600;
            white-space: nowrap;
        }

        .asset-alert-message,
        .asset-history-message {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 0;
        }

        .asset-alert-item.is-info {
            border-color: rgba(37, 99, 235, 0.28);
            background: linear-gradient(180deg, rgba(239, 246, 255, 0.86) 0%, rgba(248, 250, 252, 0.92) 100%);
        }

        .asset-alert-item.is-success {
            border-color: rgba(5, 150, 105, 0.28);
            background: linear-gradient(180deg, rgba(236, 253, 245, 0.86) 0%, rgba(248, 250, 252, 0.92) 100%);
        }

        .asset-alert-item.is-warning {
            border-color: rgba(217, 119, 6, 0.3);
            background: linear-gradient(180deg, rgba(255, 247, 237, 0.9) 0%, rgba(248, 250, 252, 0.92) 100%);
        }

        .asset-alert-item.is-error {
            border-color: rgba(220, 38, 38, 0.24);
            background: linear-gradient(180deg, rgba(254, 242, 242, 0.88) 0%, rgba(248, 250, 252, 0.93) 100%);
        }

        .asset-pill {
            font-size: 0.66rem;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            border-radius: 999px;
            padding: 4px 7px;
            font-weight: 800;
            border: 1px solid var(--border-light);
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.66);
        }

        body.withdraw-authorize-open .modal-backdrop.show {
            z-index: 10590;
            background-color: rgba(7, 14, 24, 0.56);
        }

        #withdrawAuthorizeModal.show {
            background-color: rgba(7, 14, 24, 0.56);
        }

        @media (max-width: 768px) {
            .transfer-flow {
                padding-bottom: 8rem !important;
            }

            .network-list {
                max-height: min(48vh, 340px);
            }

            .network-warning-dock {
                left: 0.85rem;
                right: 0.85rem;
                bottom: 0.85rem;
            }
        }
    </style>

    <!-- Asset Deposit Modal -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="assetDepositModal" style="z-index: 10530;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn" aria-label="Close deposit modal"><i class="fas fa-chevron-left"></i></button>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;">Deposit <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Network and address</div>
                </div>
            </div>
            <span id="depositStepBadge" style="font-size: 0.72rem; font-weight: 700; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border-light); color: var(--text-secondary);">Step 1/2</span>
        </div>

        <div class="chat-body transfer-flow">
            <section id="depositStep1" class="transfer-panel transfer-network-panel">
                <div class="transfer-section-title">Select Network</div>

                <div id="depositNetworkList" class="network-list"></div>
            </section>

            <section id="depositStep2" class="transfer-panel d-none">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div style="font-weight: 700; font-size: 1.08rem;">Deposit Address</div>
                        <div id="depositNetworkMeta" style="font-size: 0.85rem; color: var(--text-secondary);"></div>
                    </div>
                    <button id="depositBackBtn" class="transfer-mini-back"><i class="fas fa-chevron-left"></i> Back</button>
                </div>

                <div class="qr-panel">
                    <div class="qr-image-wrap">
                        <img id="depositQrImage" src="" alt="Deposit QR Code">
                    </div>
                </div>

                <div class="address-panel">
                    <div id="depositAddress" style="font-size: 0.88rem; line-height: 1.6; word-break: break-all; color: var(--text-primary);"></div>
                </div>

                <div class="d-flex gap-2 transfer-cta">
                    <button id="copyDepositAddressBtn" class="btn-pro btn-pro-primary" style="width: 100%;"><i class="fas fa-copy"></i> Copy Address</button>
                </div>
                <p class="transfer-address-note mb-0">Use only the selected network.</p>
            </section>

            <div id="depositNetworkWarning" class="network-warning-inline network-warning-dock d-none">
                <p id="depositNetworkWarningText" class="network-warning-text mb-0">Make sure the selected network matches your sender wallet network.</p>
                <div class="network-warning-actions">
                    <label class="network-warning-toggle">
                        <input id="depositDontShowAgain" type="checkbox">
                        <span>Don't show again</span>
                    </label>
                    <button id="depositNetworkConfirmBtn" type="button" class="btn-pro btn-pro-primary" style="width: 100%;"><i class="fas fa-check"></i> Got it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Asset Withdraw Modal -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="assetWithdrawModal" style="z-index: 10525;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn" aria-label="Close withdraw modal"><i class="fas fa-chevron-left"></i></button>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;">Withdraw <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Network and transfer</div>
                </div>
            </div>
            <span id="withdrawStepBadge" style="font-size: 0.72rem; font-weight: 700; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border-light); color: var(--text-secondary);">Step 1/3</span>
        </div>

        <div class="chat-body transfer-flow">
            <section id="withdrawStep1" class="transfer-panel transfer-network-panel">
                <div class="transfer-section-title">Select Network</div>
                <div class="transfer-subline">Balance: <?php echo htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                <div id="withdrawNetworkList" class="network-list"></div>
            </section>

            <section id="withdrawStep2" class="transfer-panel d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div style="font-weight: 700; font-size: 1.08rem;">Withdrawal</div>
                        <div id="withdrawNetworkMeta" style="font-size: 0.84rem; color: var(--text-secondary);"></div>
                    </div>
                    <button id="withdrawBackBtn" class="transfer-mini-back"><i class="fas fa-chevron-left"></i> Back</button>
                </div>

                <div class="transfer-field">
                    <label class="transfer-label">Address</label>
                    <input id="withdrawAddressInput" class="transfer-input" type="text" placeholder="Enter destination wallet address">
                </div>

                <div class="transfer-field">
                    <label class="transfer-label">Amount (<?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?>)</label>
                    <input id="withdrawAmountInput" class="transfer-input" type="number" min="0" step="0.000001" placeholder="0.00">
                </div>

                <div class="transfer-summary">
                    <span>Estimated network fee</span>
                    <span id="withdrawFeeText">--</span>
                </div>

                <button id="withdrawReviewBtn" class="btn-pro btn-pro-primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Review Withdrawal</button>
            </section>

            <section id="withdrawStep3" class="transfer-panel d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div style="font-weight: 700; font-size: 1.08rem;">Processing Request</div>
                        <div style="font-size: 0.84rem; color: var(--text-secondary);">Submitted to operations queue</div>
                    </div>
                    <button id="withdrawReceiptBackBtn" class="transfer-mini-back"><i class="fas fa-chevron-left"></i> Back</button>
                </div>

                <div class="withdraw-processing-card">
                    <div class="withdraw-processing-head">
                        <div class="withdraw-processing-title">Withdrawal Receipt</div>
                        <span id="withdrawReceiptStatus" class="withdraw-processing-pill">processing</span>
                    </div>

                    <div class="withdraw-processing-list">
                        <div class="withdraw-processing-row"><span class="k">Asset</span><span id="withdrawReceiptAsset" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Amount</span><span id="withdrawReceiptAmount" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Network</span><span id="withdrawReceiptNetwork" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Stage</span><span id="withdrawReceiptStage" class="v">Queued</span></div>
                        <div class="withdraw-processing-row"><span class="k">Destination</span><span id="withdrawReceiptAddress" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Request ID</span><span id="withdrawReceiptReference" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">TxID</span><span id="withdrawReceiptTxid" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Submitted</span><span id="withdrawReceiptSubmitted" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">Last update</span><span id="withdrawReceiptLastUpdate" class="v">--</span></div>
                        <div class="withdraw-processing-row"><span class="k">ETA</span><span id="withdrawReceiptEta" class="v">--</span></div>
                    </div>

                    <p class="withdraw-processing-footnote">Your request has passed validation and balance lock. Status updates will continue here automatically for the next few minutes.</p>
                </div>

                <button id="withdrawGoProcessingBtn" class="btn-pro btn-pro-primary" style="width:100%;"><i class="fas fa-rotate"></i> Refresh Updates</button>
            </section>

            <div id="withdrawNetworkWarning" class="network-warning-inline network-warning-dock d-none">
                <p id="withdrawNetworkWarningText" class="network-warning-text mb-0">Make sure the selected network matches your destination wallet network.</p>
                <div class="network-warning-actions">
                    <label class="network-warning-toggle">
                        <input id="withdrawDontShowAgain" type="checkbox">
                        <span>Don't show again</span>
                    </label>
                    <button id="withdrawNetworkConfirmBtn" type="button" class="btn-pro btn-pro-primary" style="width: 100%;"><i class="fas fa-check"></i> Got it</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="withdrawAuthorizeModal" tabindex="-1" aria-labelledby="withdrawAuthorizeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10600;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--panel-bg, var(--bg-surface)); border: 1px solid var(--border-light); color: var(--text-primary); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-light);">
                    <h5 class="modal-title" id="withdrawAuthorizeModalLabel" style="font-weight: 700;">Authorize Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="color: var(--text-secondary); font-size: 0.92rem;">Enter your account password to confirm this withdrawal request.</p>
                    <div class="transfer-field mb-0">
                        <label class="transfer-label" for="withdrawAuthorizePasswordInput">Account Password</label>
                        <input id="withdrawAuthorizePasswordInput" class="transfer-input" type="password" autocomplete="current-password" placeholder="Enter your login password">
                    </div>
                    <div id="withdrawAuthorizeError" class="mt-3 d-none" style="color: #dc3545; font-size: 0.86rem;"></div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-light);">
                    <button type="button" class="btn-pro btn-pro-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="withdrawAuthorizeSubmitBtn" class="btn-pro btn-pro-primary"><i class="fas fa-lock"></i> Confirm & Submit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="assetAlertsModal" style="z-index: 10524;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn" aria-label="Close alerts"><i class="fas fa-chevron-left"></i></button>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;">Alerts</div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Notifications for <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <button id="clearAssetAlertsBtn" type="button" class="btn-pro btn-pro-secondary" style="max-width: 110px; min-height: 36px;"><i class="fas fa-trash"></i> Clear</button>
        </div>

        <div class="chat-body transfer-flow" style="padding-top:1.2rem !important;">
            <div id="assetAlertsFeed" class="asset-feed-wrap"></div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="assetHistoryModal" style="z-index: 10523;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn" aria-label="Close history"><i class="fas fa-chevron-left"></i></button>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;">History</div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Recent <?php echo htmlspecialchars($asset, ENT_QUOTES, 'UTF-8'); ?> actions</div>
                </div>
            </div>
            <button id="clearAssetHistoryBtn" type="button" class="btn-pro btn-pro-secondary" style="max-width: 110px; min-height: 36px;"><i class="fas fa-trash"></i> Clear</button>
        </div>

        <div class="chat-body transfer-flow" style="padding-top:1.2rem !important;">
            <div id="assetHistoryFeed" class="asset-feed-wrap"></div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="assetTxDetailsModal" style="z-index: 10522;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn" aria-label="Close transaction details"><i class="fas fa-chevron-left"></i></button>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;" id="txDetailsTitle">Transaction Details</div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;" id="txDetailsAsset">--</div>
                </div>
            </div>
        </div>

        <div class="chat-body transfer-flow" style="padding-top:1.2rem !important;">
            <div class="asset-feed-item">
                <p class="asset-feed-message mb-3" id="txDetailsMessage">Select a transaction from History to view details.</p>
                <div class="small text-muted mb-2">Status: <span id="txDetailsStatus">--</span></div>
                <div class="small text-muted mb-2">Amount: <span id="txDetailsAmount">--</span></div>
                <div class="small text-muted mb-2">Method: <span id="txDetailsMethod">--</span></div>
                <div class="small text-muted mb-2">Reference: <span id="txDetailsReference">--</span></div>
                <div class="small text-muted">Time: <span id="txDetailsTime">--</span></div>
            </div>
        </div>
    </div>

    <?php require_once 'templates/swap_widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.assetTransferConfig = <?php echo json_encode([
            'asset' => $asset,
            'networks' => $availableNetworks,
            'balance' => $amount,
            'balanceDisplay' => $amountDisplay,
        ], JSON_UNESCAPED_SLASHES); ?>;

        window.assetSwapDefaults = <?php echo json_encode([
            'symbol' => $asset,
            'name' => $meta['name'],
            'icon' => $meta['icon'],
            'color' => $meta['color'],
            'amount' => $amount,
            'amountDisplay' => $amountDisplay,
            'gbpAmount' => $swapGbpAmount,
            'btcAmount' => $btcSwapAmount,
            'ethAmount' => $ethSwapAmount,
        ], JSON_UNESCAPED_SLASHES); ?>;

        window.assetFeedSeed = <?php echo json_encode($assetFeedSeed, JSON_UNESCAPED_SLASHES); ?>;

        (function () {
            function formatNetworkFee(networkId) {
                var fees = {
                    bitcoin: '0.00012 ' + window.assetTransferConfig.asset,
                    lightning: '0.00001 ' + window.assetTransferConfig.asset,
                    ethereum: '0.0025 ' + window.assetTransferConfig.asset,
                    arbitrum: '0.0006 ' + window.assetTransferConfig.asset,
                    tron: '1.00 ' + window.assetTransferConfig.asset,
                    bsc: '0.0008 ' + window.assetTransferConfig.asset,
                    solana: '0.00002 ' + window.assetTransferConfig.asset,
                    xrp: '0.25 ' + window.assetTransferConfig.asset,
                    mainnet: 'Network fee varies',
                };

                return fees[networkId] || 'Network fee varies';
            }

            function makeDepositAddress(symbol, networkId) {
                var seed = (symbol + '_' + networkId + '_finpay').toUpperCase().replace(/[^A-Z0-9]/g, '');
                var body = '';
                while (body.length < 34) {
                    body += seed;
                }
                return (networkId.substring(0, 3).toUpperCase() + '1' + body.substring(0, 33));
            }

            async function resolveDepositAddress(symbol, networkId) {
                var safeSymbol = String(symbol || '').toUpperCase();
                if (safeSymbol !== 'BTC') {
                    return makeDepositAddress(safeSymbol, networkId);
                }

                var endpoint = '../api/v1/crypto/btc_deposit_address.php?asset=' + encodeURIComponent(safeSymbol) + '&network=' + encodeURIComponent(networkId || 'bitcoin');
                var response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                var raw = await response.text();
                var payload;
                try {
                    payload = JSON.parse(raw);
                } catch (e) {
                    throw new Error('Deposit address API returned invalid JSON');
                }

                if (!response.ok || !payload || payload.success !== true || !payload.data || !payload.data.address || !payload.data.address.address) {
                    var msg = payload && payload.message ? payload.message : 'Could not generate BTC deposit address';
                    throw new Error(msg);
                }

                return String(payload.data.address.address);
            }

            function initTransferModals() {
                var cfg = window.assetTransferConfig || null;
                if (!cfg || !Array.isArray(cfg.networks) || cfg.networks.length === 0) {
                    return;
                }

                var alertsStorageKey = 'finpay_asset_alerts_' + cfg.asset;
                var historyStorageKey = 'finpay_asset_history_' + cfg.asset;
                var activityJournalKey = 'finpay_activity_journal';

                var alertFeedEl = document.getElementById('assetAlertsFeed');
                var historyFeedEl = document.getElementById('assetHistoryFeed');
                var clearAlertsBtn = document.getElementById('clearAssetAlertsBtn');
                var clearHistoryBtn = document.getElementById('clearAssetHistoryBtn');
                var historyModalEl = document.getElementById('assetHistoryModal');
                var historyModal = historyModalEl && window.bootstrap ? window.bootstrap.Offcanvas.getOrCreateInstance(historyModalEl) : null;
                var txDetailsTitleEl = document.getElementById('txDetailsTitle');
                var txDetailsAssetEl = document.getElementById('txDetailsAsset');
                var txDetailsMessageEl = document.getElementById('txDetailsMessage');
                var txDetailsStatusEl = document.getElementById('txDetailsStatus');
                var txDetailsAmountEl = document.getElementById('txDetailsAmount');
                var txDetailsMethodEl = document.getElementById('txDetailsMethod');
                var txDetailsReferenceEl = document.getElementById('txDetailsReference');
                var txDetailsTimeEl = document.getElementById('txDetailsTime');
                var txDetailsModalEl = document.getElementById('assetTxDetailsModal');
                var txDetailsModal = txDetailsModalEl && window.bootstrap ? window.bootstrap.Offcanvas.getOrCreateInstance(txDetailsModalEl) : null;

                function nowIso() {
                    return new Date().toISOString();
                }

                function readFeed(key) {
                    try {
                        var raw = localStorage.getItem(key);
                        var parsed = raw ? JSON.parse(raw) : [];
                        return Array.isArray(parsed) ? parsed : [];
                    } catch (e) {
                        return [];
                    }
                }

                function writeFeed(key, list) {
                    try {
                        localStorage.setItem(key, JSON.stringify(list.slice(0, 80)));
                    } catch (e) {
                        // no-op
                    }
                }

                function formatFeedTime(ts) {
                    var d = ts ? new Date(ts) : new Date();
                    if (Number.isNaN(d.getTime())) {
                        d = new Date();
                    }
                    return d.toLocaleString();
                }

                function renderAlerts() {
                    if (!alertFeedEl) {
                        return;
                    }

                    var items = readFeed(alertsStorageKey);
                    if (!items.length) {
                        alertFeedEl.innerHTML = '<div class="asset-feed-empty">No alerts yet. Important updates for this asset will appear here.</div>';
                        return;
                    }

                    alertFeedEl.innerHTML = items.map(function (item) {
                        var level = String(item.level || 'info');
                        var safeLevel = ['info', 'success', 'warning', 'error'].indexOf(level) >= 0 ? level : 'info';
                        return '<article class="asset-alert-item is-' + safeLevel + '">' +
                            '<div class="asset-alert-head">' +
                                '<div class="asset-alert-title">' + String(item.title || 'Alert') + '</div>' +
                                '<div class="asset-alert-meta">' + formatFeedTime(item.ts) + '</div>' +
                            '</div>' +
                            '<p class="asset-alert-message">' + String(item.message || '') + '</p>' +
                        '</article>';
                    }).join('');
                }

                function renderHistory() {
                    if (!historyFeedEl) {
                        return;
                    }

                    var items = readFeed(historyStorageKey);
                    if (!items.length) {
                        historyFeedEl.innerHTML = '<div class="asset-feed-empty">No history yet. Your asset activity will be tracked here.</div>';
                        return;
                    }

                    historyFeedEl.innerHTML = items.map(function (item, idx) {
                        var kind = String(item.kind || 'activity').toUpperCase();
                        return '<article class="asset-history-item clickable" data-history-idx="' + idx + '" tabindex="0" role="button" aria-label="View transaction details">' +
                            '<div class="asset-history-head">' +
                                '<div class="asset-history-title">' + String(item.title || 'Activity') + '</div>' +
                                '<div class="asset-history-meta">' + formatFeedTime(item.ts) + '</div>' +
                            '</div>' +
                            '<div class="d-flex justify-content-between align-items-center gap-2 mb-1">' +
                                '<span class="asset-pill">' + kind + '</span>' +
                                '<span class="asset-history-meta">' + cfg.asset + '</span>' +
                            '</div>' +
                            '<p class="asset-history-message">' + String(item.message || '') + '</p>' +
                        '</article>';
                    }).join('');
                }

                function openHistoryDetails(item) {
                    if (!item || !txDetailsModal) {
                        return;
                    }

                    var details = item.details && typeof item.details === 'object' ? item.details : {};
                    if (txDetailsTitleEl) {
                        txDetailsTitleEl.textContent = String(item.title || 'Transaction Details');
                    }
                    if (txDetailsAssetEl) {
                        txDetailsAssetEl.textContent = String(details.asset || cfg.asset || '--');
                    }
                    if (txDetailsMessageEl) {
                        txDetailsMessageEl.textContent = String(item.message || '--');
                    }
                    if (txDetailsStatusEl) {
                        txDetailsStatusEl.textContent = String(details.status || String(item.kind || '--').toUpperCase());
                    }
                    if (txDetailsAmountEl) {
                        txDetailsAmountEl.textContent = String(details.amount || '--');
                    }
                    if (txDetailsMethodEl) {
                        txDetailsMethodEl.textContent = String(details.method || '--');
                    }
                    if (txDetailsReferenceEl) {
                        txDetailsReferenceEl.textContent = String(details.reference || '--');
                    }
                    if (txDetailsTimeEl) {
                        txDetailsTimeEl.textContent = formatFeedTime(details.time || item.ts);
                    }

                    if (historyModal) {
                        historyModal.hide();
                    }
                    txDetailsModal.show();
                }

                function sortByTsDesc(list) {
                    return list.slice().sort(function (a, b) {
                        var ta = Date.parse((a && a.ts) ? String(a.ts) : '') || 0;
                        var tb = Date.parse((b && b.ts) ? String(b.ts) : '') || 0;
                        return tb - ta;
                    });
                }

                function dedupeAndLimit(list, maxItems, mode) {
                    var seen = Object.create(null);
                    var out = [];
                    sortByTsDesc(Array.isArray(list) ? list : []).forEach(function (item) {
                        if (!item || typeof item !== 'object') {
                            return;
                        }

                        var title = String(item.title || '').trim();
                        var message = String(item.message || '').trim();
                        var ts = String(item.ts || '').trim();
                        var tag = mode === 'alerts'
                            ? String(item.level || 'info').toLowerCase()
                            : String(item.kind || 'activity').toLowerCase();

                        var signature = [tag, title, message, ts.slice(0, 19)].join('|');
                        if (signature === '|||') {
                            return;
                        }
                        if (seen[signature]) {
                            return;
                        }
                        seen[signature] = true;
                        out.push(item);
                    });

                    return out.slice(0, maxItems);
                }

                function detailLooksLikeAsset(detail) {
                    if (!detail || typeof detail !== 'object') {
                        return false;
                    }

                    var symbol = String(detail.asset || detail.symbol || '').toUpperCase();
                    if (symbol && symbol === String(cfg.asset || '').toUpperCase()) {
                        return true;
                    }

                    var title = String(detail.title || '').toUpperCase();
                    var message = String(detail.message || '').toUpperCase();
                    var needle = String(cfg.asset || '').toUpperCase();

                    return !!needle && (title.indexOf(needle) >= 0 || message.indexOf(needle) >= 0);
                }

                function collectJournalSeed() {
                    var raw = readFeed(activityJournalKey);
                    var out = {
                        alerts: [],
                        history: [],
                    };

                    raw.forEach(function (entry) {
                        var detail = entry && entry.detail ? entry.detail : entry;
                        if (!detailLooksLikeAsset(detail)) {
                            return;
                        }

                        var kind = String(detail.kind || detail.type || 'activity').toLowerCase();
                        var ts = String(entry.ts || detail.ts || nowIso());
                        var title = String(detail.title || 'Activity');
                        var message = String(detail.message || 'Asset activity update');

                        out.history.push({
                            kind: kind,
                            title: title,
                            message: message,
                            ts: ts,
                            details: detail.details && typeof detail.details === 'object' ? detail.details : {
                                source: String(detail.source || 'activity'),
                                status: kind.toUpperCase(),
                                amount: '--',
                                method: '--',
                                reference: '--',
                                time: ts,
                                asset: String(cfg.asset || '').toUpperCase(),
                            },
                        });

                        out.alerts.push({
                            level: kind === 'error' ? 'error' : (kind === 'warning' ? 'warning' : 'info'),
                            title: title,
                            message: message,
                            ts: ts,
                        });
                    });

                    return out;
                }

                function hydrateFeeds() {
                    var persistedAlerts = readFeed(alertsStorageKey);
                    var persistedHistory = readFeed(historyStorageKey);
                    var seed = window.assetFeedSeed && typeof window.assetFeedSeed === 'object'
                        ? window.assetFeedSeed
                        : { alerts: [], history: [] };
                    var journal = collectJournalSeed();

                    var mergedAlerts = dedupeAndLimit(
                        [].concat(persistedAlerts, seed.alerts || [], journal.alerts || []),
                        80,
                        'alerts'
                    );
                    var mergedHistory = dedupeAndLimit(
                        [].concat(persistedHistory, seed.history || [], journal.history || []),
                        80,
                        'history'
                    );

                    if (!mergedAlerts.length && mergedHistory.length) {
                        mergedAlerts = mergedHistory.slice(0, 6).map(function (item) {
                            return {
                                level: 'info',
                                title: String(item.title || 'Activity'),
                                message: String(item.message || 'Recent asset activity update.'),
                                ts: String(item.ts || nowIso())
                            };
                        });
                    }

                    writeFeed(alertsStorageKey, mergedAlerts);
                    writeFeed(historyStorageKey, mergedHistory);
                }

                function pushAlert(level, title, message) {
                    var items = readFeed(alertsStorageKey);
                    items.unshift({
                        level: level,
                        title: title,
                        message: message,
                        ts: nowIso()
                    });
                    writeFeed(alertsStorageKey, items);
                    renderAlerts();
                }

                function pushHistory(kind, title, message, details) {
                    var items = readFeed(historyStorageKey);
                    items.unshift({
                        kind: kind,
                        title: title,
                        message: message,
                        ts: nowIso(),
                        details: details && typeof details === 'object' ? details : null,
                    });
                    writeFeed(historyStorageKey, items);
                    renderHistory();
                }

                if (clearAlertsBtn) {
                    clearAlertsBtn.addEventListener('click', function () {
                        writeFeed(alertsStorageKey, []);
                        renderAlerts();
                    });
                }

                if (clearHistoryBtn) {
                    clearHistoryBtn.addEventListener('click', function () {
                        writeFeed(historyStorageKey, []);
                        renderHistory();
                    });
                }

                if (historyFeedEl) {
                    historyFeedEl.addEventListener('click', function (event) {
                        var row = event.target && event.target.closest ? event.target.closest('[data-history-idx]') : null;
                        if (!row) {
                            return;
                        }

                        var idx = parseInt(row.getAttribute('data-history-idx') || '-1', 10);
                        var items = readFeed(historyStorageKey);
                        if (idx < 0 || idx >= items.length) {
                            return;
                        }

                        openHistoryDetails(items[idx]);
                    });

                    historyFeedEl.addEventListener('keydown', function (event) {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }

                        var row = event.target && event.target.closest ? event.target.closest('[data-history-idx]') : null;
                        if (!row) {
                            return;
                        }

                        event.preventDefault();
                        var idx = parseInt(row.getAttribute('data-history-idx') || '-1', 10);
                        var items = readFeed(historyStorageKey);
                        if (idx < 0 || idx >= items.length) {
                            return;
                        }

                        openHistoryDetails(items[idx]);
                    });
                }

                hydrateFeeds();
                renderAlerts();
                renderHistory();

                window.addEventListener('finpay:activity', function (event) {
                    var detail = (event && event.detail) ? event.detail : {};
                    if (!detailLooksLikeAsset(detail)) {
                        return;
                    }
                    if (String(detail.source || '') === 'asset_details') {
                        return;
                    }

                    var kind = String(detail.kind || detail.type || 'info').toLowerCase();
                    var title = String(detail.title || 'Activity');
                    var message = String(detail.message || 'Asset activity update');

                    pushHistory(kind, title, message, detail.details && typeof detail.details === 'object' ? detail.details : {
                        source: String(detail.source || 'activity'),
                        status: kind.toUpperCase(),
                        amount: '--',
                        method: '--',
                        reference: '--',
                        time: nowIso(),
                        asset: String(cfg.asset || '').toUpperCase(),
                    });
                    pushAlert(kind === 'error' ? 'error' : (kind === 'warning' ? 'warning' : 'info'), title, message);
                });

                window.addEventListener('finpay:swap-completed', function (event) {
                    var detail = (event && event.detail) ? event.detail : {};
                    var payCurrency = String(detail.payCurrency || '').toUpperCase();
                    var receiveCurrency = String(detail.receiveCurrency || '').toUpperCase();
                    var payAmount = Number(detail.payAmount || 0);
                    var receiveAmount = Number(detail.receiveAmount || 0);
                    var currentAsset = String(cfg.asset || '').toUpperCase();

                    if (!Number.isFinite(payAmount) || !Number.isFinite(receiveAmount) || payAmount <= 0 || receiveAmount <= 0) {
                        return;
                    }

                    if (!window.assetSwapDefaults || typeof window.assetSwapDefaults !== 'object') {
                        window.assetSwapDefaults = {};
                    }

                    if (payCurrency === 'GBP') {
                        window.assetSwapDefaults.gbpAmount = Math.max(0, Number(window.assetSwapDefaults.gbpAmount || 0) - payAmount);
                    }
                    if (receiveCurrency === 'GBP') {
                        window.assetSwapDefaults.gbpAmount = Number(window.assetSwapDefaults.gbpAmount || 0) + receiveAmount;
                    }
                    if (payCurrency === 'BTC') {
                        window.assetSwapDefaults.btcAmount = Math.max(0, Number(window.assetSwapDefaults.btcAmount || 0) - payAmount);
                    }
                    if (receiveCurrency === 'BTC') {
                        window.assetSwapDefaults.btcAmount = Number(window.assetSwapDefaults.btcAmount || 0) + receiveAmount;
                    }
                    if (payCurrency === 'ETH') {
                        window.assetSwapDefaults.ethAmount = Math.max(0, Number(window.assetSwapDefaults.ethAmount || 0) - payAmount);
                    }
                    if (receiveCurrency === 'ETH') {
                        window.assetSwapDefaults.ethAmount = Number(window.assetSwapDefaults.ethAmount || 0) + receiveAmount;
                    }

                    var delta = 0;
                    if (payCurrency === currentAsset) {
                        delta -= payAmount;
                    }
                    if (receiveCurrency === currentAsset) {
                        delta += receiveAmount;
                    }
                    if (delta === 0) {
                        return;
                    }

                    var updatedAmount = Math.max(0, Number(cfg.balance || 0) + delta);
                    cfg.balance = updatedAmount;
                    cfg.balanceDisplay = currentAsset === 'GBP'
                        ? updatedAmount.toFixed(2)
                        : updatedAmount.toFixed(6).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');

                    var cryptoBalanceEl = document.getElementById('crypto-balance');
                    if (cryptoBalanceEl) {
                        cryptoBalanceEl.setAttribute('data-amount', String(updatedAmount));
                        cryptoBalanceEl.textContent = cfg.balanceDisplay + ' ' + currentAsset;
                    }

                    var mobileHoldingEl = document.querySelector('.asset-mobile-holding-value');
                    if (mobileHoldingEl) {
                        mobileHoldingEl.textContent = cfg.balanceDisplay + ' ' + currentAsset;
                    }

                    var sublineEls = document.querySelectorAll('.transfer-subline');
                    sublineEls.forEach(function (el) {
                        if (el && el.textContent && el.textContent.indexOf('Balance:') === 0) {
                            el.textContent = 'Balance: ' + cfg.balanceDisplay + ' ' + currentAsset;
                        }
                    });
                });

                var selectedDepositNetwork = null;
                var selectedWithdrawNetwork = null;

                var depositList = document.getElementById('depositNetworkList');
                var withdrawList = document.getElementById('withdrawNetworkList');
                var depositStep1 = document.getElementById('depositStep1');
                var depositStep2 = document.getElementById('depositStep2');
                var withdrawStep1 = document.getElementById('withdrawStep1');
                var withdrawStep2 = document.getElementById('withdrawStep2');
                var withdrawStep3 = document.getElementById('withdrawStep3');
                var depositStepBadge = document.getElementById('depositStepBadge');
                var withdrawStepBadge = document.getElementById('withdrawStepBadge');
                var depositAddressEl = document.getElementById('depositAddress');
                var depositQrImage = document.getElementById('depositQrImage');
                var depositNetworkMeta = document.getElementById('depositNetworkMeta');
                var withdrawNetworkMeta = document.getElementById('withdrawNetworkMeta');
                var withdrawFeeText = document.getElementById('withdrawFeeText');
                var withdrawReceiptStatus = document.getElementById('withdrawReceiptStatus');
                var withdrawReceiptAsset = document.getElementById('withdrawReceiptAsset');
                var withdrawReceiptAmount = document.getElementById('withdrawReceiptAmount');
                var withdrawReceiptNetwork = document.getElementById('withdrawReceiptNetwork');
                var withdrawReceiptAddress = document.getElementById('withdrawReceiptAddress');
                var withdrawReceiptReference = document.getElementById('withdrawReceiptReference');
                var withdrawReceiptTxid = document.getElementById('withdrawReceiptTxid');
                var withdrawReceiptSubmitted = document.getElementById('withdrawReceiptSubmitted');
                var withdrawReceiptLastUpdate = document.getElementById('withdrawReceiptLastUpdate');
                var withdrawReceiptStage = document.getElementById('withdrawReceiptStage');
                var withdrawReceiptEta = document.getElementById('withdrawReceiptEta');
                var withdrawGoProcessingBtn = document.getElementById('withdrawGoProcessingBtn');
                var depositWarningBox = document.getElementById('depositNetworkWarning');
                var depositWarningText = document.getElementById('depositNetworkWarningText');
                var depositConfirmBtn = document.getElementById('depositNetworkConfirmBtn');
                var depositDontShowAgain = document.getElementById('depositDontShowAgain');
                var withdrawWarningBox = document.getElementById('withdrawNetworkWarning');
                var withdrawWarningText = document.getElementById('withdrawNetworkWarningText');
                var withdrawConfirmBtn = document.getElementById('withdrawNetworkConfirmBtn');
                var withdrawDontShowAgain = document.getElementById('withdrawDontShowAgain');
                var depositWarningAfterConfirm = null;
                var withdrawWarningAfterConfirm = null;
                var withdrawStatusPollTimer = null;
                var withdrawStageTimer = null;
                var withdrawCurrentReference = '';
                var withdrawSubmittedAtMs = 0;

                function shortAddress(address) {
                    var text = String(address || '').trim();
                    if (text.length <= 20) {
                        return text;
                    }
                    return text.substring(0, 10) + '...' + text.substring(text.length - 8);
                }

                function clearWithdrawProcessingTimers() {
                    if (withdrawStatusPollTimer) {
                        clearInterval(withdrawStatusPollTimer);
                        withdrawStatusPollTimer = null;
                    }
                    if (withdrawStageTimer) {
                        clearInterval(withdrawStageTimer);
                        withdrawStageTimer = null;
                    }
                }

                function updateWithdrawStageByElapsed() {
                    if (!withdrawReceiptStage) {
                        return;
                    }

                    var elapsedSec = Math.max(0, Math.floor((Date.now() - withdrawSubmittedAtMs) / 1000));
                    var stage = 'Queued for processing';
                    if (elapsedSec >= 60) {
                        stage = 'Compliance and risk checks';
                    }
                    if (elapsedSec >= 120) {
                        stage = 'Preparing network broadcast';
                    }
                    if (elapsedSec >= 180) {
                        stage = 'Awaiting network confirmations';
                    }

                    withdrawReceiptStage.textContent = stage;
                }

                function refreshWithdrawStatus() {
                    if (!withdrawCurrentReference) {
                        return;
                    }

                    fetch('../api/v1/crypto/withdraw_status.php?reference=' + encodeURIComponent(withdrawCurrentReference), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(function (response) {
                        return response.text().then(function (raw) {
                            var payload;
                            try {
                                payload = JSON.parse(raw);
                            } catch (e) {
                                throw new Error('Status API returned invalid JSON');
                            }

                            if (!response.ok || !payload || payload.success !== true || !payload.data) {
                                throw new Error(payload && payload.message ? payload.message : 'Unable to refresh status');
                            }

                            return payload.data.withdrawal || {};
                        });
                    })
                    .then(function (remote) {
                        if (withdrawReceiptStatus) {
                            withdrawReceiptStatus.textContent = String(remote.status || 'processing').toUpperCase();
                        }
                        if (withdrawReceiptEta) {
                            withdrawReceiptEta.textContent = remote.eta || '5-20 minutes';
                        }
                        if (withdrawReceiptLastUpdate) {
                            withdrawReceiptLastUpdate.textContent = new Date().toLocaleTimeString();
                        }
                    })
                    .catch(function () {
                        if (withdrawReceiptLastUpdate) {
                            withdrawReceiptLastUpdate.textContent = new Date().toLocaleTimeString();
                        }
                    });
                }

                function showWithdrawProcessingStep(details) {
                    clearWithdrawProcessingTimers();
                    if (withdrawStep1) withdrawStep1.classList.add('d-none');
                    if (withdrawStep2) withdrawStep2.classList.add('d-none');
                    if (withdrawStep3) withdrawStep3.classList.remove('d-none');
                    if (withdrawStepBadge) withdrawStepBadge.textContent = 'Step 3/3';

                    withdrawCurrentReference = details.reference ? String(details.reference) : '';
                    withdrawSubmittedAtMs = Date.parse(details.submitted_at || '') || Date.now();

                    if (withdrawReceiptStatus) {
                        withdrawReceiptStatus.textContent = (details.status || 'processing').toUpperCase();
                    }
                    if (withdrawReceiptAsset) {
                        withdrawReceiptAsset.textContent = cfg.asset;
                    }
                    if (withdrawReceiptAmount) {
                        withdrawReceiptAmount.textContent = (details.amount_display || '--') + ' ' + cfg.asset;
                    }
                    if (withdrawReceiptNetwork) {
                        withdrawReceiptNetwork.textContent = selectedWithdrawNetwork ? selectedWithdrawNetwork.name : (details.network || '--');
                    }
                    if (withdrawReceiptAddress) {
                        withdrawReceiptAddress.textContent = shortAddress(details.address || '');
                    }
                    if (withdrawReceiptReference) {
                        withdrawReceiptReference.textContent = details.reference || '--';
                    }
                    if (withdrawReceiptTxid) {
                        withdrawReceiptTxid.textContent = details.tracking_txid || '--';
                    }
                    if (withdrawReceiptSubmitted) {
                        withdrawReceiptSubmitted.textContent = details.submitted_at || new Date().toISOString();
                    }
                    if (withdrawReceiptLastUpdate) {
                        withdrawReceiptLastUpdate.textContent = new Date().toLocaleTimeString();
                    }
                    if (withdrawReceiptEta) {
                        withdrawReceiptEta.textContent = details.eta || '5-20 minutes';
                    }

                    updateWithdrawStageByElapsed();

                    if (withdrawGoProcessingBtn) {
                        withdrawGoProcessingBtn.onclick = function () {
                            refreshWithdrawStatus();
                        };
                    }

                    refreshWithdrawStatus();
                    withdrawStageTimer = setInterval(updateWithdrawStageByElapsed, 15000);
                    withdrawStatusPollTimer = setInterval(refreshWithdrawStatus, 25000);
                }

                function shouldSkipWarning() {
                    try {
                        return localStorage.getItem('finpay_skip_network_warning') === '1';
                    } catch (e) {
                        return false;
                    }
                }

                function setSkipWarning(enabled) {
                    try {
                        if (enabled) {
                            localStorage.setItem('finpay_skip_network_warning', '1');
                        } else {
                            localStorage.removeItem('finpay_skip_network_warning');
                        }
                    } catch (e) {
                        // no-op
                    }
                }

                function withInlineNetworkWarning(networkName, actionLabel, warningBox, warningText, dontShowAgainInput, setAfterConfirm, done) {
                    if (shouldSkipWarning()) {
                        done();
                        return;
                    }

                    if (!warningBox) {
                        done();
                        return;
                    }

                    setAfterConfirm(done);
                    if (warningText) {
                        warningText.textContent = 'You selected ' + networkName + ' for ' + actionLabel + '. Make sure your sender/receiver wallet uses the same network.';
                    }
                    if (dontShowAgainInput) {
                        dontShowAgainInput.checked = false;
                    }
                    warningBox.classList.remove('d-none');
                    warningBox.classList.remove('is-visible');
                    requestAnimationFrame(function () {
                        warningBox.classList.add('is-visible');
                    });
                }

                function hideInlineNetworkWarning(warningBox) {
                    if (!warningBox) {
                        return;
                    }
                    warningBox.classList.remove('is-visible');
                    setTimeout(function () {
                        warningBox.classList.add('d-none');
                    }, 180);
                }

                function networkRowHtml(nw, selected) {
                    return '<button type="button" class="network-row ' + (selected ? 'is-selected' : '') + '" data-network-id="' + nw.id + '">' +
                    '<span class="network-main">' +
                    '<span><strong style="font-size:0.95rem; font-weight:700;">' + nw.name + '</strong><span style="display:block; font-size:0.74rem; color:var(--text-secondary); margin-top:2px;">' + nw.tag + '</span></span>' +
                    '</span>' +
                    '<span class="network-meta"><span class="network-eta">' + nw.eta + '</span><i class="' + (selected ? 'fas fa-check-circle' : 'far fa-circle') + ' network-check"></i></span></button>';
                }

                function renderDepositNetworks() {
                    if (!depositList) return;
                    depositList.innerHTML = cfg.networks.map(function (nw) {
                        return networkRowHtml(nw, selectedDepositNetwork && selectedDepositNetwork.id === nw.id);
                    }).join('');
                }

                function renderWithdrawNetworks() {
                    if (!withdrawList) return;
                    withdrawList.innerHTML = cfg.networks.map(function (nw) {
                        return networkRowHtml(nw, selectedWithdrawNetwork && selectedWithdrawNetwork.id === nw.id);
                    }).join('');
                }

                function resetDepositFlow() {
                    selectedDepositNetwork = null;
                    renderDepositNetworks();
                    if (depositStep2) depositStep2.classList.add('d-none');
                    if (depositStep1) depositStep1.classList.remove('d-none');
                    if (depositStepBadge) depositStepBadge.textContent = 'Step 1/2';
                    if (depositNetworkMeta) depositNetworkMeta.textContent = '';
                    if (depositAddressEl) depositAddressEl.textContent = '';
                    if (depositQrImage) depositQrImage.removeAttribute('src');
                    hideInlineNetworkWarning(depositWarningBox);
                    depositWarningAfterConfirm = null;
                }

                function resetWithdrawFlow() {
                    clearWithdrawProcessingTimers();
                    withdrawCurrentReference = '';
                    withdrawSubmittedAtMs = 0;
                    pendingWithdrawalRequest = null;
                    selectedWithdrawNetwork = null;
                    renderWithdrawNetworks();
                    if (withdrawStep3) withdrawStep3.classList.add('d-none');
                    if (withdrawStep2) withdrawStep2.classList.add('d-none');
                    if (withdrawStep1) withdrawStep1.classList.remove('d-none');
                    if (withdrawStepBadge) withdrawStepBadge.textContent = 'Step 1/3';
                    if (withdrawNetworkMeta) withdrawNetworkMeta.textContent = '';
                    if (withdrawFeeText) withdrawFeeText.textContent = '--';
                    if (withdrawAddressInput) withdrawAddressInput.value = '';
                    if (withdrawAmountInput) withdrawAmountInput.value = '';
                    hideInlineNetworkWarning(withdrawWarningBox);
                    withdrawWarningAfterConfirm = null;
                    if (withdrawAuthorizeModal && withdrawAuthorizeModalEl && withdrawAuthorizeModalEl.classList.contains('show')) {
                        withdrawAuthorizeModal.hide();
                    }
                    if (withdrawAuthorizePasswordInput) {
                        withdrawAuthorizePasswordInput.value = '';
                    }
                    setWithdrawAuthorizeError('');
                    if (withdrawReviewBtn) {
                        withdrawReviewBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Review Withdrawal';
                    }
                }

                renderDepositNetworks();
                renderWithdrawNetworks();

                if (depositList) {
                    depositList.addEventListener('click', function (event) {
                        var btn = event.target.closest('button[data-network-id]');
                        if (!btn) return;
                        var id = btn.getAttribute('data-network-id');
                        selectedDepositNetwork = cfg.networks.find(function (nw) { return nw.id === id; }) || null;
                        renderDepositNetworks();

                        if (!selectedDepositNetwork) return;
                        withInlineNetworkWarning(
                            selectedDepositNetwork.name,
                            'deposit',
                            depositWarningBox,
                            depositWarningText,
                            depositDontShowAgain,
                            function (nextStep) { depositWarningAfterConfirm = nextStep; },
                            function () {
                            if (depositStep1) depositStep1.classList.add('d-none');
                            if (depositStep2) depositStep2.classList.remove('d-none');
                            if (depositStepBadge) depositStepBadge.textContent = 'Step 2/2';
                            if (depositNetworkMeta) depositNetworkMeta.textContent = selectedDepositNetwork.name + ' • ' + selectedDepositNetwork.tag;
                            if (depositAddressEl) depositAddressEl.textContent = 'Generating address...';
                            if (depositQrImage) {
                                depositQrImage.removeAttribute('src');
                            }

                            resolveDepositAddress(cfg.asset, selectedDepositNetwork.id)
                                .then(function (address) {
                                    if (depositAddressEl) depositAddressEl.textContent = address;
                                    if (depositQrImage) {
                                        depositQrImage.src = 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=' + encodeURIComponent(address);
                                    }
                                    pushAlert('info', 'Deposit Address Ready', selectedDepositNetwork.name + ' address generated for ' + cfg.asset + '.');
                                    pushHistory('deposit', 'Deposit Address Generated', selectedDepositNetwork.name + ' address is ready for funding.', {
                                        source: 'deposit_address',
                                        status: 'READY',
                                        amount: '--',
                                        method: selectedDepositNetwork.name,
                                        reference: '--',
                                        time: nowIso(),
                                        asset: cfg.asset,
                                    });
                                })
                                .catch(function (error) {
                                    var errMsg = (error && error.message) ? String(error.message) : 'Try again.';
                                    if (errMsg.length > 120) {
                                        errMsg = errMsg.substring(0, 120) + '...';
                                    }
                                    if (depositAddressEl) depositAddressEl.textContent = 'Address generation failed: ' + errMsg;
                                    if (depositNetworkMeta) depositNetworkMeta.textContent = (selectedDepositNetwork.name + ' • ' + selectedDepositNetwork.tag + ' • Error');
                                    if (copyBtn) {
                                        copyBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Retry';
                                        setTimeout(function () {
                                            copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Address';
                                        }, 1800);
                                    }
                                    pushAlert('error', 'Deposit Address Failed', 'Could not generate a deposit address for ' + cfg.asset + '.');
                                    pushHistory('deposit_error', 'Deposit Address Failed', errMsg, {
                                        source: 'deposit_address',
                                        status: 'FAILED',
                                        amount: '--',
                                        method: selectedDepositNetwork ? selectedDepositNetwork.name : '--',
                                        reference: '--',
                                        time: nowIso(),
                                        asset: cfg.asset,
                                    });
                                    console.error(error);
                                });
                            }
                        );
                    });
                }

                if (withdrawList) {
                    withdrawList.addEventListener('click', function (event) {
                        var btn = event.target.closest('button[data-network-id]');
                        if (!btn) return;
                        var id = btn.getAttribute('data-network-id');
                        selectedWithdrawNetwork = cfg.networks.find(function (nw) { return nw.id === id; }) || null;
                        renderWithdrawNetworks();

                        if (!selectedWithdrawNetwork) return;
                        withInlineNetworkWarning(
                            selectedWithdrawNetwork.name,
                            'withdrawal',
                            withdrawWarningBox,
                            withdrawWarningText,
                            withdrawDontShowAgain,
                            function (nextStep) { withdrawWarningAfterConfirm = nextStep; },
                            function () {
                            if (withdrawStep1) withdrawStep1.classList.add('d-none');
                            if (withdrawStep2) withdrawStep2.classList.remove('d-none');
                            if (withdrawStepBadge) withdrawStepBadge.textContent = 'Step 2/3';
                            if (withdrawNetworkMeta) withdrawNetworkMeta.textContent = selectedWithdrawNetwork.name + ' • ' + selectedWithdrawNetwork.tag;
                            if (withdrawFeeText) withdrawFeeText.textContent = formatNetworkFee(selectedWithdrawNetwork.id);
                            }
                        );
                    });
                }

                if (depositConfirmBtn) {
                    depositConfirmBtn.addEventListener('click', function () {
                        if (depositDontShowAgain && depositDontShowAgain.checked) {
                            setSkipWarning(true);
                        }
                        hideInlineNetworkWarning(depositWarningBox);
                        if (typeof depositWarningAfterConfirm === 'function') {
                            var next = depositWarningAfterConfirm;
                            depositWarningAfterConfirm = null;
                            next();
                        }
                    });
                }

                if (withdrawConfirmBtn) {
                    withdrawConfirmBtn.addEventListener('click', function () {
                        if (withdrawDontShowAgain && withdrawDontShowAgain.checked) {
                            setSkipWarning(true);
                        }
                        hideInlineNetworkWarning(withdrawWarningBox);
                        if (typeof withdrawWarningAfterConfirm === 'function') {
                            var next = withdrawWarningAfterConfirm;
                            withdrawWarningAfterConfirm = null;
                            next();
                        }
                    });
                }

                var depositBackBtn = document.getElementById('depositBackBtn');
                if (depositBackBtn) {
                    depositBackBtn.addEventListener('click', function () {
                        if (depositStep2) depositStep2.classList.add('d-none');
                        if (depositStep1) depositStep1.classList.remove('d-none');
                        if (depositStepBadge) depositStepBadge.textContent = 'Step 1/2';
                        hideInlineNetworkWarning(depositWarningBox);
                        depositWarningAfterConfirm = null;
                    });
                }

                var copyBtn = document.getElementById('copyDepositAddressBtn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        var address = depositAddressEl ? depositAddressEl.textContent : '';
                        if (!address) return;
                        navigator.clipboard.writeText(address).then(function () {
                            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
                            setTimeout(function () {
                                copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Address';
                            }, 1500);
                            pushAlert('success', 'Address Copied', 'Deposit address copied to clipboard.');
                            pushHistory('deposit', 'Address Copied', 'Deposit address copied for quick transfer setup.', {
                                source: 'deposit_address',
                                status: 'COPIED',
                                amount: '--',
                                method: selectedDepositNetwork ? selectedDepositNetwork.name : '--',
                                reference: '--',
                                time: nowIso(),
                                asset: cfg.asset,
                            });
                        });
                    });
                }

                var withdrawBackBtn = document.getElementById('withdrawBackBtn');
                if (withdrawBackBtn) {
                    withdrawBackBtn.addEventListener('click', function () {
                        if (withdrawStep2) withdrawStep2.classList.add('d-none');
                        if (withdrawStep1) withdrawStep1.classList.remove('d-none');
                        if (withdrawStepBadge) withdrawStepBadge.textContent = 'Step 1/3';
                        hideInlineNetworkWarning(withdrawWarningBox);
                        withdrawWarningAfterConfirm = null;
                    });
                }

                var withdrawReceiptBackBtn = document.getElementById('withdrawReceiptBackBtn');
                if (withdrawReceiptBackBtn) {
                    withdrawReceiptBackBtn.addEventListener('click', function () {
                        clearWithdrawProcessingTimers();
                        if (withdrawStep3) withdrawStep3.classList.add('d-none');
                        if (withdrawStep2) withdrawStep2.classList.remove('d-none');
                        if (withdrawStepBadge) withdrawStepBadge.textContent = 'Step 2/3';
                    });
                }

                var withdrawReviewBtn = document.getElementById('withdrawReviewBtn');
                var withdrawAddressInput = document.getElementById('withdrawAddressInput');
                var withdrawAmountInput = document.getElementById('withdrawAmountInput');
                var withdrawAuthorizeModalEl = document.getElementById('withdrawAuthorizeModal');
                var withdrawAuthorizePasswordInput = document.getElementById('withdrawAuthorizePasswordInput');
                var withdrawAuthorizeError = document.getElementById('withdrawAuthorizeError');
                var withdrawAuthorizeSubmitBtn = document.getElementById('withdrawAuthorizeSubmitBtn');
                var withdrawAuthorizeModal = (withdrawAuthorizeModalEl && window.bootstrap && window.bootstrap.Modal)
                    ? window.bootstrap.Modal.getOrCreateInstance(withdrawAuthorizeModalEl)
                    : null;
                var pendingWithdrawalRequest = null;
                var notify = (window.finpayNotify && typeof window.finpayNotify === 'function')
                    ? window.finpayNotify
                    : function (message) { alert(message); };

                function setWithdrawAuthorizeError(message) {
                    if (!withdrawAuthorizeError) {
                        return;
                    }
                    var text = String(message || '').trim();
                    withdrawAuthorizeError.textContent = text;
                    withdrawAuthorizeError.classList.toggle('d-none', text === '');
                }

                function submitWithdrawalRequest(password) {
                    if (!pendingWithdrawalRequest || !selectedWithdrawNetwork || !selectedWithdrawNetwork.id) {
                        throw new Error('Withdrawal session expired. Please review the request again.');
                    }

                    var address = pendingWithdrawalRequest.address;
                    var amount = pendingWithdrawalRequest.amount;

                    return fetch('../api/v1/crypto/withdraw_internal.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            asset: cfg.asset,
                            network: selectedWithdrawNetwork.id,
                            address: address,
                            amount: amount,
                            password: password
                        })
                    })
                    .then(function (response) {
                        return response.text().then(function (raw) {
                            var payload;
                            try {
                                payload = JSON.parse(raw);
                            } catch (e) {
                                throw new Error('Withdrawal API returned invalid JSON');
                            }

                            if (!response.ok || !payload || payload.success !== true || !payload.data) {
                                throw new Error(payload && payload.message ? payload.message : 'Unable to process withdrawal');
                            }

                            return payload.data;
                        });
                    })
                    .then(function (data) {
                        var balance = data.balance || {};
                        var newAmount = Number(balance.amount || 0);
                        var displayAmount = (cfg.asset === 'GBP')
                            ? newAmount.toFixed(2)
                            : newAmount.toFixed(6).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');

                        cfg.balance = newAmount;
                        cfg.balanceDisplay = displayAmount;

                        var cryptoBalanceEl = document.getElementById('crypto-balance');
                        if (cryptoBalanceEl) {
                            cryptoBalanceEl.setAttribute('data-amount', String(newAmount));
                            cryptoBalanceEl.textContent = displayAmount + ' ' + cfg.asset;
                        }

                        var mobileHoldingEl = document.querySelector('.asset-mobile-holding-value');
                        if (mobileHoldingEl) {
                            mobileHoldingEl.textContent = displayAmount + ' ' + cfg.asset;
                        }

                        var sublineEls = document.querySelectorAll('.transfer-subline');
                        sublineEls.forEach(function (el) {
                            if (el && el.textContent && el.textContent.indexOf('Balance:') === 0) {
                                el.textContent = 'Balance: ' + displayAmount + ' ' + cfg.asset;
                            }
                        });

                        var withdrawal = data.withdrawal || {};
                        var receipt = {
                            reference: withdrawal.reference || '',
                            amount_display: withdrawal.amount_display || amount.toFixed(6),
                            network: withdrawal.network || (selectedWithdrawNetwork ? selectedWithdrawNetwork.id : ''),
                            address: withdrawal.address || address,
                            tracking_txid: withdrawal.tracking_txid || '',
                            status: withdrawal.status || 'processing',
                            submitted_at: withdrawal.submitted_at || new Date().toISOString(),
                            eta: withdrawal.eta || '5-20 minutes'
                        };

                        if (withdrawAuthorizeModal) {
                            withdrawAuthorizeModal.hide();
                        }
                        if (withdrawAuthorizePasswordInput) {
                            withdrawAuthorizePasswordInput.value = '';
                        }
                        setWithdrawAuthorizeError('');

                        notify('Withdrawal submitted. It is now in processing queue.', {
                            type: 'success',
                            title: 'Withdrawal Submitted'
                        });
                        pushAlert('success', 'Withdrawal Submitted', cfg.asset + ' withdrawal is now in processing queue.');
                        pushHistory('withdrawal', 'Withdrawal Submitted', receipt.amount_display + ' ' + cfg.asset + ' to ' + shortAddress(receipt.address), {
                            source: 'withdrawals',
                            status: String(receipt.status || 'processing').toUpperCase(),
                            amount: '-' + receipt.amount_display + ' ' + cfg.asset,
                            method: selectedWithdrawNetwork ? selectedWithdrawNetwork.name : '--',
                            reference: receipt.reference || '--',
                            time: receipt.submitted_at || nowIso(),
                            asset: cfg.asset,
                        });

                        window.dispatchEvent(new CustomEvent('finpay:activity', {
                            detail: {
                                kind: 'info',
                                title: 'Withdrawal',
                                message: cfg.asset + ' withdrawal submitted for processing.',
                                asset: cfg.asset,
                                symbol: cfg.asset,
                                source: 'asset_details',
                                details: {
                                    source: 'withdrawals',
                                    status: String(receipt.status || 'processing').toUpperCase(),
                                    amount: '-' + receipt.amount_display + ' ' + cfg.asset,
                                    method: selectedWithdrawNetwork ? selectedWithdrawNetwork.name : '--',
                                    reference: receipt.reference || '--',
                                    time: receipt.submitted_at || nowIso(),
                                    asset: cfg.asset,
                                },
                                important: true
                            }
                        }));

                        showWithdrawProcessingStep(receipt);

                        if (withdrawAddressInput) {
                            withdrawAddressInput.value = '';
                        }
                        if (withdrawAmountInput) {
                            withdrawAmountInput.value = '';
                        }
                        pendingWithdrawalRequest = null;
                    });
                }

                if (withdrawReviewBtn) {
                    withdrawReviewBtn.addEventListener('click', function () {
                        var address = withdrawAddressInput ? withdrawAddressInput.value.trim() : '';
                        var amount = withdrawAmountInput ? parseFloat(withdrawAmountInput.value) : 0;

                        if (!address || amount <= 0) {
                            notify('Enter address and amount before continuing.', {
                                type: 'warning',
                                title: 'Withdrawal Validation'
                            });
                            return;
                        }

                        if (!selectedWithdrawNetwork || !selectedWithdrawNetwork.id) {
                            notify('Select a withdrawal network first.', {
                                type: 'warning',
                                title: 'Network Required'
                            });
                            return;
                        }

                        pendingWithdrawalRequest = {
                            address: address,
                            amount: amount
                        };

                        setWithdrawAuthorizeError('');
                        if (withdrawAuthorizePasswordInput) {
                            withdrawAuthorizePasswordInput.value = '';
                            withdrawAuthorizePasswordInput.focus();
                        }

                        if (withdrawAuthorizeModal) {
                            withdrawAuthorizeModal.show();
                        } else {
                            notify('Authorization modal is unavailable. Please refresh and try again.', {
                                type: 'error',
                                title: 'Withdrawal Failed',
                                duration: 4200
                            });
                        }
                    });
                }

                if (withdrawAuthorizeSubmitBtn) {
                    withdrawAuthorizeSubmitBtn.addEventListener('click', function () {
                        var password = withdrawAuthorizePasswordInput ? String(withdrawAuthorizePasswordInput.value || '') : '';
                        if (!password.trim()) {
                            setWithdrawAuthorizeError('Enter your account password to continue.');
                            if (withdrawAuthorizePasswordInput) {
                                withdrawAuthorizePasswordInput.focus();
                            }
                            return;
                        }

                        withdrawAuthorizeSubmitBtn.disabled = true;
                        submitWithdrawalRequest(password)
                            .catch(function (error) {
                                var msg = (error && error.message) ? String(error.message) : 'Could not process withdrawal';
                                setWithdrawAuthorizeError(msg);
                            })
                            .finally(function () {
                                withdrawAuthorizeSubmitBtn.disabled = false;
                            });
                    });
                }

                if (withdrawAuthorizePasswordInput) {
                    withdrawAuthorizePasswordInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            if (withdrawAuthorizeSubmitBtn && !withdrawAuthorizeSubmitBtn.disabled) {
                                withdrawAuthorizeSubmitBtn.click();
                            }
                        }
                    });
                }

                if (withdrawAuthorizeModalEl) {
                    withdrawAuthorizeModalEl.addEventListener('shown.bs.modal', function () {
                        document.body.classList.add('withdraw-authorize-open');
                    });

                    withdrawAuthorizeModalEl.addEventListener('hidden.bs.modal', function () {
                        document.body.classList.remove('withdraw-authorize-open');
                        setWithdrawAuthorizeError('');
                        if (withdrawAuthorizePasswordInput) {
                            withdrawAuthorizePasswordInput.value = '';
                        }
                    });
                }

                var depositModalEl = document.getElementById('assetDepositModal');
                if (depositModalEl) {
                    depositModalEl.addEventListener('hidden.bs.offcanvas', function () {
                        resetDepositFlow();
                    });
                }

                var withdrawModalEl = document.getElementById('assetWithdrawModal');
                if (withdrawModalEl) {
                    withdrawModalEl.addEventListener('hidden.bs.offcanvas', function () {
                        resetWithdrawFlow();
                    });
                }

                resetDepositFlow();
                resetWithdrawFlow();
            }

            function applySwapDefaults() {
                var modal = document.getElementById('swapModal');
                var cfg = window.assetSwapDefaults || null;
                if (!modal || !cfg) {
                    return;
                }

                var payInput = modal.querySelector('.swap-input-box:first-child input');
                var receiveInput = modal.querySelector('.swap-input-box:nth-child(3) input');
                var reviewOrderBtn = modal.querySelector('.mt-4.mt-auto.w-100 button.btn-pro.btn-pro-primary');

                var defaultState = {
                    payInput: payInput ? payInput.value : '',
                    receiveInput: receiveInput ? receiveInput.value : '',
                    reviewBtnHtml: reviewOrderBtn ? reviewOrderBtn.innerHTML : '',
                };

                var boxes = modal.querySelectorAll('.swap-input-box');
                if (!boxes || boxes.length < 2) {
                    return;
                }

                var receiveBox = boxes[1];
                var receiveSelector = receiveBox.querySelector('.asset-selector');
                if (receiveSelector) {
                    var symbolEl = receiveSelector.querySelector('span');
                    if (symbolEl) {
                        symbolEl.textContent = cfg.symbol;
                    }

                    var iconWrap = receiveSelector.querySelector('div');
                    if (iconWrap) {
                        iconWrap.style.background = 'rgba(0,0,0,0.05)';
                        iconWrap.style.color = cfg.color || '#10b981';
                        var iconEl = iconWrap.querySelector('i');
                        if (iconEl) {
                            iconEl.className = cfg.icon || 'fas fa-coins';
                        }
                    }
                }

                var receiveBalance = receiveBox.querySelector('.d-flex.justify-content-between.align-items-center.mb-3 div:last-child');
                if (receiveBalance) {
                    receiveBalance.textContent = 'Bal: ' + cfg.amountDisplay + ' ' + cfg.symbol;
                }

                var rateValue = modal.querySelector('.exchange-info .d-flex.justify-content-between.align-items-center.mb-2 div:last-child');
                if (rateValue) {
                    rateValue.textContent = '1 GBP = -- ' + cfg.symbol;
                }

                var helper = modal.querySelector('.mt-4.mt-auto.w-100 .text-center p');
                if (helper) {
                    helper.innerHTML = '<i class="fas fa-shield-alt text-success me-1"></i> Swaps restricted to GBP \u2194 ' + cfg.symbol + ' securely.';
                }

                modal.addEventListener('hidden.bs.offcanvas', function () {
                    if (payInput) {
                        payInput.value = defaultState.payInput;
                    }
                    if (receiveInput) {
                        receiveInput.value = defaultState.receiveInput;
                    }
                    if (reviewOrderBtn && defaultState.reviewBtnHtml) {
                        reviewOrderBtn.innerHTML = defaultState.reviewBtnHtml;
                    }

                    var scrollBody = modal.querySelector('.chat-body');
                    if (scrollBody) {
                        scrollBody.scrollTop = 0;
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                initTransferModals();
                applySwapDefaults();
            });
        })();
    </script>
    <script src="../assets/js/asset_details.js"></script>
</body>
</html>

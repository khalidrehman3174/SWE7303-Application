<?php
$pageTitle = 'FinPay Pro - Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../api/v1/lib/config.php';
$apiConfig = api_config();

$recentActivities = [];
$allActivities = [];

if (isset($dbc, $_SESSION['user_id'])) {
    $safeUserId = (int)$_SESSION['user_id'];
    $tableExistsResult = mysqli_query($dbc, "SHOW TABLES LIKE 'deposits'");

    if ($tableExistsResult && mysqli_num_rows($tableExistsResult) > 0) {
        $columnsResult = mysqli_query($dbc, 'SHOW COLUMNS FROM deposits');
        $depositColumns = [];

        if ($columnsResult) {
            while ($col = mysqli_fetch_assoc($columnsResult)) {
                $depositColumns[] = $col['Field'];
            }
        }

        $idExpr = in_array('deposit_id', $depositColumns, true)
            ? 'deposit_id'
            : (in_array('public_id', $depositColumns, true) ? 'public_id' : 'CAST(id AS CHAR)');

        $netAmountExpr = in_array('net_amount', $depositColumns, true)
            ? 'net_amount'
            : (in_array('amount', $depositColumns, true) ? 'amount' : '0');

        $completedAtExpr = in_array('completed_at', $depositColumns, true)
            ? 'completed_at'
            : (in_array('settled_at', $depositColumns, true) ? 'settled_at' : 'created_at');

        $sql = "SELECT
                    {$idExpr} AS activity_id,
                    method,
                    currency,
                    {$netAmountExpr} AS net_amount,
                    status,
                    provider,
                    created_at,
                    {$completedAtExpr} AS completed_at
                FROM deposits
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 3";

        $stmt = mysqli_prepare($dbc, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $safeUserId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $row['activity_type'] = 'deposit';
                    $recentActivities[] = $row;
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}

if (isset($dbc, $_SESSION['user_id'])) {
    $safeUserId = (int)$_SESSION['user_id'];
    $tableExistsResult = mysqli_query($dbc, "SHOW TABLES LIKE 'deposits'");

    if ($tableExistsResult && mysqli_num_rows($tableExistsResult) > 0) {
        $columnsResult = mysqli_query($dbc, 'SHOW COLUMNS FROM deposits');
        $depositColumns = [];

        if ($columnsResult) {
            while ($col = mysqli_fetch_assoc($columnsResult)) {
                $depositColumns[] = $col['Field'];
            }
        }

        $idExpr = in_array('deposit_id', $depositColumns, true)
            ? 'deposit_id'
            : (in_array('public_id', $depositColumns, true) ? 'public_id' : 'CAST(id AS CHAR)');

        $netAmountExpr = in_array('net_amount', $depositColumns, true)
            ? 'net_amount'
            : (in_array('amount', $depositColumns, true) ? 'amount' : '0');

        $completedAtExpr = in_array('completed_at', $depositColumns, true)
            ? 'completed_at'
            : (in_array('settled_at', $depositColumns, true) ? 'settled_at' : 'created_at');

        $sql = "SELECT
                    {$idExpr} AS activity_id,
                    method,
                    currency,
                    {$netAmountExpr} AS net_amount,
                    status,
                    provider,
                    created_at,
                    {$completedAtExpr} AS completed_at
                FROM deposits
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 500";

        $stmt = mysqli_prepare($dbc, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $safeUserId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $row['activity_type'] = 'deposit';
                    $allActivities[] = $row;
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}

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

function dashboard_deposit_activity_meta(string $method, string $status): array
{
    $safeMethod = strtolower($method);
    $safeStatus = strtolower($status);

    $map = [
        'bank' => ['icon_class' => 'fas fa-university', 'bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => 'Bank Deposit'],
        'card' => ['icon_class' => 'fas fa-credit-card', 'bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => 'Card Deposit'],
        // Use the brand icon for Apple Pay instead of solid fallback icon.
        'apple' => ['icon_class' => 'fab fa-apple', 'bg' => 'rgba(17, 24, 39, 0.10)', 'color' => 'var(--text-primary)', 'label' => 'Apple Pay Deposit'],
    ];

    $meta = $map[$safeMethod] ?? ['icon_class' => 'fas fa-arrow-down', 'bg' => 'var(--icon-bg-default)', 'color' => 'var(--text-primary)', 'label' => 'Deposit'];

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
        $meta = dashboard_deposit_activity_meta((string)($activity['method'] ?? ''), (string)($activity['status'] ?? ''));
        $allActivitiesPayload[] = [
            'activity_type' => ucfirst((string)($activity['activity_type'] ?? 'Activity')),
            'label' => (string)$meta['label'],
            'status_raw' => (string)($activity['status'] ?? 'unknown'),
            'status_sub' => (string)$meta['sub'],
            'method' => (string)($activity['method'] ?? 'n/a'),
            'icon_class' => (string)($meta['icon_class'] ?? 'fas fa-arrow-down'),
            'amount' => number_format((float)($activity['net_amount'] ?? 0), 2),
            'currency' => strtoupper((string)($activity['currency'] ?? 'GBP')),
            'time_label' => dashboard_activity_time_label($activity['created_at'] ?? null),
            'created_label' => dashboard_activity_datetime_label($activity['created_at'] ?? null),
            'completed_label' => dashboard_activity_datetime_label($activity['completed_at'] ?? null),
        ];
    }
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
                    <div class="balance-label">Total Portfolio Value</div>
                    <div class="balance-amount">
                        <span class="balance-currency">£</span>12,450<span style="color: var(--text-secondary); font-size: 3rem;">.00</span>
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
                                <div class="asset-price">£4,209.50</div>
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
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">This Week <i class="fas fa-chevron-right ms-1"></i></div>
                </div>
                
                <div class="glass-panel text-center" style="padding: 2.5rem 1rem; margin-bottom: 2rem; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">
                    <div style="position: relative; height: 120px; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 10px; opacity: 0.8;">
                        <div style="width: 10%; background: var(--text-secondary); height: 30%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 45%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 20%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--accent); height: 60%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                        <div style="width: 10%; background: var(--accent); height: 85%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 50%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--accent); height: 100%; border-radius: 6px; box-shadow: 0 0 20px var(--accent-glow);"></div>
                    </div>
                    <div class="mt-4">
                        <div style="font-size: 0.95rem; color: var(--text-secondary); font-weight: 500;">Portfolio Performance</div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-top: 5px;">+ £450.20 <span class="text-success" style="font-size: 1rem;">(3.8%)</span></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Activity</h3>
                    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#allActivityModal" style="font-size: 0.85rem; color: var(--accent); font-weight: 600; text-decoration: none; background: transparent; border: none; padding: 0;">See All <i class="fas fa-chevron-right ms-1" style="font-size: 0.75rem;"></i></button>
                </div>
                <div class="list-pro">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <?php
                                $meta = dashboard_deposit_activity_meta((string)($activity['method'] ?? ''), (string)($activity['status'] ?? ''));
                                $currency = strtoupper((string)($activity['currency'] ?? 'GBP'));
                                $amount = number_format((float)($activity['net_amount'] ?? 0), 2);
                                $timeLabel = dashboard_activity_time_label($activity['created_at'] ?? null);
                                $statusSub = $meta['sub'];
                                $activityType = ucfirst((string)($activity['activity_type'] ?? 'Activity'));
                                $createdLabel = dashboard_activity_datetime_label($activity['created_at'] ?? null);
                                $completedLabel = dashboard_activity_datetime_label($activity['completed_at'] ?? null);
                            ?>
                               <div class="asset-row" style="padding: 0.75rem 1rem;" data-bs-toggle="offcanvas" data-bs-target="#activityDetailsModal"
                                 data-activity-type="<?php echo htmlspecialchars($activityType, ENT_QUOTES); ?>"
                                 data-activity-label="<?php echo htmlspecialchars($meta['label'], ENT_QUOTES); ?>"
                                 data-activity-status="<?php echo htmlspecialchars((string)($activity['status'] ?? 'unknown'), ENT_QUOTES); ?>"
                                 data-activity-method="<?php echo htmlspecialchars((string)($activity['method'] ?? 'n/a'), ENT_QUOTES); ?>"
                                   data-activity-icon="<?php echo htmlspecialchars($meta['icon_class'], ENT_QUOTES); ?>"
                                 data-activity-amount="<?php echo htmlspecialchars($amount, ENT_QUOTES); ?>"
                                 data-activity-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES); ?>"
                                 data-activity-created="<?php echo htmlspecialchars($createdLabel, ENT_QUOTES); ?>"
                                 data-activity-completed="<?php echo htmlspecialchars($completedLabel, ENT_QUOTES); ?>"
                                 data-activity-id="<?php echo htmlspecialchars((string)($activity['activity_id'] ?? 'n/a'), ENT_QUOTES); ?>">
                                <div class="asset-icon" style="background: <?php echo htmlspecialchars($meta['bg'], ENT_QUOTES); ?>; color: <?php echo htmlspecialchars($meta['color'], ENT_QUOTES); ?>; width: 40px; height: 40px; font-size: 1.1rem;"><i class="<?php echo htmlspecialchars($meta['icon_class'], ENT_QUOTES); ?>"></i></div>
                                <div class="asset-info">
                                    <div class="asset-name" style="font-size: 0.95rem;"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES); ?></div>
                                    <div class="asset-sub"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES); ?> • <?php echo htmlspecialchars($statusSub, ENT_QUOTES); ?></div>
                                </div>
                                <div class="asset-value">
                                    <div class="asset-price text-success" style="font-size: 0.95rem;">+ <?php echo htmlspecialchars($currency, ENT_QUOTES); ?> <?php echo htmlspecialchars($amount, ENT_QUOTES); ?></div>
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
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-chart-line text-accent me-1"></i> This Week</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="swap-input-box mb-4 text-center" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 2rem 1.5rem; transition: border-color 0.2s;">
                <div style="font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Total Performance</div>
                <div style="font-weight: 700; font-size: 2.5rem; font-family: 'Outfit'; color: var(--text-primary);">+ £450.20</div>
                <div style="font-size: 1.1rem; color: #10b981; font-weight: 600; margin-top: 5px;"><i class="fas fa-arrow-up me-1"></i>3.8%</div>
                
                <div style="position: relative; height: 100px; width: 100%; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; margin-top: 2rem; opacity: 0.9;">
                    <div style="width: 14%; background: var(--text-secondary); height: 30%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 45%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 20%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 60%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 50%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--accent); height: 85%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                    <div style="width: 14%; background: var(--accent); height: 100%; border-radius: 6px; box-shadow: 0 0 20px var(--accent-glow);"></div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h3 class="section-heading mb-0" style="font-size: 1.1rem;">Asset Allocation</h3>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fas fa-pie-chart text-secondary"></i></div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem;">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">Bitcoin</div>
                    </div>
                    <div style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">66%</div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">British Pound</div>
                    </div>
                    <div style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">34%</div>
                </div>

                <div class="progress mt-4" style="height: 12px; border-radius: 100px; background: rgba(255,255,255,0.05);">
                    <div class="progress-bar" role="progressbar" style="width: 66%; background: #f59e0b; border-radius: 100px;"></div>
                    <div class="progress-bar" role="progressbar" style="width: 34%; background: #3b82f6; border-radius: 100px;"></div>
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
        const ALL_ACTIVITIES = <?php echo json_encode($allActivitiesPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let selectedMethod = 'bank';
        let activeCardDepositId = null;
        let activeCardClientSecret = null;
        let activeCardProviderMode = 'mock';
        let stripe = null;
        let stripeElements = null;
        let cardElement = null;
        let renderedActivityCount = 0;
        let isAppendingActivities = false;

        const INITIAL_ACTIVITY_BATCH = 15;
        const NEXT_ACTIVITY_BATCH = 6;

        function setFeedback(id, message, isError = false) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = message;
            el.style.color = isError ? '#ef4444' : 'var(--text-secondary)';
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
                        } else {
                            setFeedback('cardDepositFeedback', `Mock mode: Deposit ${deposit.deposit_id} is ready. Click Pay Securely to complete sandbox settlement.`);
                        }
                        const modal = new bootstrap.Offcanvas(document.getElementById('cardDepositModal'));
                        modal.show();
                    } else if (selectedMethod === 'apple') {
                        document.getElementById('applePayAmountDisplay').innerText = amount;
                        setFeedback('depositFeedback', `Deposit completed: ${deposit.deposit_id}`);
                        const modal = new bootstrap.Modal(document.getElementById('applePayModal'));
                        modal.show();
                    }
                }, 300);
            } catch (err) {
                setFeedback('depositFeedback', err.message || 'Deposit failed.', true);
            }
        }

        async function submitCardDeposit() {
            if (!activeCardDepositId) {
                setFeedback('cardDepositFeedback', 'No active card deposit found.', true);
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

                const cardModalEl = document.getElementById('cardDepositModal');
                const cardModal = bootstrap.Offcanvas.getInstance(cardModalEl);
                if (cardModal) {
                    setTimeout(() => cardModal.hide(), 500);
                }
            } catch (err) {
                setFeedback('cardDepositFeedback', err.message || 'Could not confirm card payment.', true);
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
            row.dataset.activityCurrency = activity.currency || 'GBP';
            row.dataset.activityCreated = activity.created_label || 'N/A';
            row.dataset.activityCompleted = activity.completed_label || 'N/A';

            const iconTone = getIconTone(row.dataset.activityIcon);
            const statusColor = getStatusColor(row.dataset.activityStatus);
            const statusLabel = normalizeStatusLabel(row.dataset.activityStatus);

            row.innerHTML = `
                <div class="asset-icon" style="background: ${iconTone.bg}; color: ${iconTone.color}; width: 40px; height: 40px; font-size: 1.1rem;"><i class="${row.dataset.activityIcon}"></i></div>
                <div class="asset-info">
                    <div class="asset-name" style="font-size: 0.95rem;">${row.dataset.activityLabel}</div>
                    <div class="asset-sub">${activity.time_label || 'Recently'} • <span style="color: ${statusColor}; font-weight: 600;">${statusLabel}</span></div>
                </div>
                <div class="asset-value">
                    <div class="asset-price text-success" style="font-size: 0.95rem;">+ ${row.dataset.activityCurrency} ${row.dataset.activityAmount}</div>
                </div>
            `;

            return row;
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
                    const currency = trigger.getAttribute('data-activity-currency') || 'GBP';
                    const amountEl = document.getElementById('activityDetailsAmount');
                    amountEl.textContent = `+ ${currency} ${amount}`;
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
    </script>
</body>
</html>

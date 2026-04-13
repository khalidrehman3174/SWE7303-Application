<?php
require_once '../includes/init.php';
require_login();

$pageTitle = 'FinPay Pro - Cards';
$activePage = 'cards';

function cards_ensure_schema(mysqli $dbc): void
{
    mysqli_query($dbc, "CREATE TABLE IF NOT EXISTS user_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        card_type ENUM('virtual','physical') NOT NULL,
        card_brand VARCHAR(20) NOT NULL DEFAULT 'visa',
        card_bin CHAR(4) NOT NULL,
        card_last4 CHAR(4) NOT NULL,
        expiry_month TINYINT NOT NULL,
        expiry_year SMALLINT NOT NULL,
        holder_name VARCHAR(120) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        shipping_full_name VARCHAR(120) NULL,
        shipping_address_line1 VARCHAR(140) NULL,
        shipping_address_line2 VARCHAR(140) NULL,
        shipping_city VARCHAR(80) NULL,
        shipping_state VARCHAR(80) NULL,
        shipping_postal_code VARCHAR(30) NULL,
        shipping_country VARCHAR(80) NULL,
        shipping_phone VARCHAR(40) NULL,
        requested_at DATETIME NOT NULL,
        issued_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_user_cards_user_created (user_id, created_at),
        KEY idx_user_cards_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cards_users_has_column(mysqli $dbc, string $column): bool
{
    $safeColumn = mysqli_real_escape_string($dbc, $column);
    $result = mysqli_query($dbc, "SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return ($result instanceof mysqli_result) && mysqli_num_rows($result) > 0;
}

function cards_fetch_user_profile(mysqli $dbc, int $uid): ?array
{
    $profileSql = cards_users_has_column($dbc, 'full_name')
        ? 'SELECT username, full_name FROM users WHERE id = ? LIMIT 1'
        : 'SELECT username, NULL AS full_name FROM users WHERE id = ? LIMIT 1';

    $stmt = mysqli_prepare($dbc, $profileSql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

function cards_fetch_user_cards(mysqli $dbc, int $uid): array
{
    $rows = [];
    $stmt = mysqli_prepare($dbc, 'SELECT * FROM user_cards WHERE user_id = ? ORDER BY created_at DESC');
    if (!$stmt) {
        return $rows;
    }

    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function cards_generate_card_seed(string $type): array
{
    $brand = random_int(0, 1) === 0 ? 'visa' : 'mastercard';
    $bin = $brand === 'visa' ? '4532' : '5291';
    if ($type === 'physical' && $brand === 'visa') {
        $bin = '4413';
    }

    $last4 = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $month = random_int(1, 12);
    $year = (int)date('Y') + random_int(3, 5);

    return [
        'brand' => $brand,
        'bin' => $bin,
        'last4' => $last4,
        'month' => $month,
        'year' => $year,
    ];
}

cards_ensure_schema($dbc);

$currentUserId = (int)$user_id;
$profile = cards_fetch_user_profile($dbc, $currentUserId);

if (!$profile) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$displayHolderName = trim((string)($profile['full_name'] ?? ''));
if ($displayHolderName === '') {
    $displayHolderName = trim((string)($profile['username'] ?? 'USER'));
}
$displayHolderName = strtoupper($displayHolderName);

if (empty($_SESSION['cards_form_token'])) {
    $_SESSION['cards_form_token'] = bin2hex(random_bytes(24));
}
$cardsToken = (string)$_SESSION['cards_form_token'];

$cardsNotice = '';
$cardsNoticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['cards_token']) ? (string)$_POST['cards_token'] : '';
    if (!hash_equals($cardsToken, $postedToken)) {
        $cardsNotice = 'Your session expired. Refresh and try again.';
        $cardsNoticeType = 'danger';
    } else {
        $action = isset($_POST['card_action']) ? (string)$_POST['card_action'] : '';

        if ($action === 'request_virtual') {
            $seed = cards_generate_card_seed('virtual');
            $status = 'active';
            $requestedAt = date('Y-m-d H:i:s');
            $issuedAt = $requestedAt;
            $type = 'virtual';

            $stmt = mysqli_prepare(
                $dbc,
                'INSERT INTO user_cards (user_id, card_type, card_brand, card_bin, card_last4, expiry_month, expiry_year, holder_name, status, requested_at, issued_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    'issssiissss',
                    $currentUserId,
                    $type,
                    $seed['brand'],
                    $seed['bin'],
                    $seed['last4'],
                    $seed['month'],
                    $seed['year'],
                    $displayHolderName,
                    $status,
                    $requestedAt,
                    $issuedAt
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $cardsNotice = 'Virtual card issued and added to your account.';
                $cardsNoticeType = 'success';
            } else {
                $cardsNotice = 'Could not issue a virtual card right now. Try again shortly.';
                $cardsNoticeType = 'danger';
            }
        } elseif ($action === 'request_physical') {
            $shipFullName = trim((string)($_POST['shipping_full_name'] ?? ''));
            $shipAddress1 = trim((string)($_POST['shipping_address_line1'] ?? ''));
            $shipAddress2 = trim((string)($_POST['shipping_address_line2'] ?? ''));
            $shipCity = trim((string)($_POST['shipping_city'] ?? ''));
            $shipState = trim((string)($_POST['shipping_state'] ?? ''));
            $shipPostal = trim((string)($_POST['shipping_postal_code'] ?? ''));
            $shipCountry = trim((string)($_POST['shipping_country'] ?? ''));
            $shipPhone = trim((string)($_POST['shipping_phone'] ?? ''));

            if (
                $shipFullName === '' || $shipAddress1 === '' || $shipCity === '' ||
                $shipState === '' || $shipPostal === '' || $shipCountry === '' || $shipPhone === ''
            ) {
                $cardsNotice = 'Complete all required physical card delivery fields.';
                $cardsNoticeType = 'danger';
            } elseif (
                strlen($shipFullName) > 120 || strlen($shipAddress1) > 140 || strlen($shipAddress2) > 140 ||
                strlen($shipCity) > 80 || strlen($shipState) > 80 || strlen($shipPostal) > 30 ||
                strlen($shipCountry) > 80 || strlen($shipPhone) > 40
            ) {
                $cardsNotice = 'One or more fields are too long. Please shorten the input and submit again.';
                $cardsNoticeType = 'danger';
            } else {
                $seed = cards_generate_card_seed('physical');
                $status = 'processing';
                $requestedAt = date('Y-m-d H:i:s');
                $type = 'physical';
                $issuedAt = null;

                $stmt = mysqli_prepare(
                    $dbc,
                    'INSERT INTO user_cards (user_id, card_type, card_brand, card_bin, card_last4, expiry_month, expiry_year, holder_name, status, shipping_full_name, shipping_address_line1, shipping_address_line2, shipping_city, shipping_state, shipping_postal_code, shipping_country, shipping_phone, requested_at, issued_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        'issssiissssssssssss',
                        $currentUserId,
                        $type,
                        $seed['brand'],
                        $seed['bin'],
                        $seed['last4'],
                        $seed['month'],
                        $seed['year'],
                        $displayHolderName,
                        $status,
                        $shipFullName,
                        $shipAddress1,
                        $shipAddress2,
                        $shipCity,
                        $shipState,
                        $shipPostal,
                        $shipCountry,
                        $shipPhone,
                        $requestedAt,
                        $issuedAt
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $cardsNotice = 'Physical card request submitted successfully.';
                    $cardsNoticeType = 'success';
                } else {
                    $cardsNotice = 'Could not submit your physical card request right now.';
                    $cardsNoticeType = 'danger';
                }
            }
        }
    }
}

$userCards = cards_fetch_user_cards($dbc, $currentUserId);
$hasCards = count($userCards) > 0;

function cards_brand_icon(string $brand): string
{
    return $brand === 'mastercard' ? 'fab fa-cc-mastercard' : 'fab fa-cc-visa';
}

function cards_status_badge(string $status): array
{
    $status = strtolower($status);
    if ($status === 'processing') {
        return ['text' => 'Pending', 'bg' => 'rgba(245, 158, 11, 0.18)', 'color' => '#92400e'];
    }
    if ($status === 'blocked') {
        return ['text' => 'Blocked', 'bg' => 'rgba(239, 68, 68, 0.18)', 'color' => '#b91c1c'];
    }
    return ['text' => 'Active', 'bg' => 'rgba(16, 185, 129, 0.2)', 'color' => '#065f46'];
}
?>
<?php require_once 'templates/head.php'; ?>

<body>

    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">

        <header class="mobile-header">
            <a href="index.php" class="profile-btn" style="text-decoration:none;"><i class="fas fa-chevron-left"></i></a>
            <div style="font-weight: 700; letter-spacing: 1px;">CARDS</div>
            <button type="button" class="profile-btn" style="text-decoration:none;" data-bs-toggle="offcanvas" data-bs-target="#virtualCardRequestCanvas" aria-controls="virtualCardRequestCanvas"><i class="fas fa-plus"></i></button>
        </header>

        <div class="cards-layout pt-lg-4 px-lg-4">

            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Card Management</h2>
                <button type="button" class="btn btn-dark pb-2 pt-2 px-4 rounded-pill" style="background: var(--text-primary); color: var(--bg-body); font-weight: 600; text-decoration:none; border:0;" data-bs-toggle="offcanvas" data-bs-target="#virtualCardRequestCanvas" aria-controls="virtualCardRequestCanvas"><i class="fas fa-plus me-2"></i> Request New Card</button>
            </div>

            <style>
                .cards-empty-state {
                    border: 1px dashed var(--border-light);
                    border-radius: 24px;
                    padding: 2rem 1.4rem;
                    background: linear-gradient(180deg, rgba(255,255,255,0.45) 0%, rgba(255,255,255,0.2) 100%);
                    text-align: center;
                }
                .cards-empty-icon {
                    width: 58px;
                    height: 58px;
                    border-radius: 18px;
                    margin: 0 auto 0.9rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(16, 185, 129, 0.12);
                    color: #059669;
                    font-size: 1.45rem;
                }
                .cards-request-box {
                    background: var(--list-bg);
                    border: 1px solid var(--border-light);
                    border-radius: 20px;
                    padding: 1.1rem;
                    margin-bottom: 1rem;
                }
                .cards-input {
                    width: 100%;
                    border: 1px solid var(--border-light);
                    border-radius: 12px;
                    background: var(--bg-surface);
                    color: var(--text-primary);
                    font-size: 0.92rem;
                    padding: 0.7rem 0.85rem;
                    outline: none;
                }
                .cards-input:focus {
                    border-color: var(--accent);
                    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
                }
                .cards-label {
                    font-size: 0.8rem;
                    color: var(--text-secondary);
                    font-weight: 600;
                    margin-bottom: 0.35rem;
                }
                .cards-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 0.75rem;
                }
                .cards-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    border-radius: 999px;
                    padding: 0.22rem 0.58rem;
                    font-size: 0.72rem;
                    font-weight: 700;
                    letter-spacing: 0.2px;
                }
                .cards-action-btn {
                    width: 100%;
                    border: 0;
                    border-bottom: 1px solid var(--asset-border);
                    background: transparent;
                    color: var(--text-primary);
                    text-align: left;
                    padding: 1.1rem 1.15rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    transition: background-color 0.2s ease;
                }
                .cards-action-btn:last-child {
                    border-bottom: 0;
                }
                .cards-action-btn:hover {
                    background: rgba(16, 185, 129, 0.08);
                }
                .cards-offcanvas {
                    background: var(--bg-body);
                    color: var(--text-primary);
                    border-left: 1px solid var(--border-light);
                    width: 100%;
                }
                .cards-offcanvas .offcanvas-header {
                    border-bottom: 1px solid var(--border-light);
                    padding: 1.1rem 1rem;
                }
                .cards-offcanvas .offcanvas-body {
                    padding: 1rem;
                }
                @media (min-width: 992px) {
                    .cards-offcanvas {
                        width: 460px !important;
                    }
                }
            </style>

            <?php if ($cardsNotice !== ''): ?>
                <div class="alert alert-<?php echo $cardsNoticeType === 'danger' ? 'danger' : 'success'; ?>" style="border-radius:14px; border:1px solid var(--border-light);">
                    <?php echo htmlspecialchars($cardsNotice, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-6">
                    <?php if (!$hasCards): ?>
                        <div class="cards-empty-state">
                            <div class="cards-empty-icon"><i class="fas fa-credit-card"></i></div>
                            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.4rem;">No cards yet</div>
                            <div style="font-size:0.88rem; color:var(--text-secondary); max-width:360px; margin:0 auto;">Request a virtual card for online use or request a physical card for delivery. Your first card appears here automatically.</div>
                        </div>
                    <?php else: ?>
                        <div class="card-carousel-container">
                            <div class="card-carousel">
                                <?php foreach ($userCards as $card): ?>
                                    <?php
                                        $isPhysical = ((string)$card['card_type']) === 'physical';
                                        $badge = cards_status_badge((string)$card['status']);
                                        $monthText = str_pad((string)((int)$card['expiry_month']), 2, '0', STR_PAD_LEFT);
                                        $yearText = substr((string)$card['expiry_year'], -2);
                                        $bin = htmlspecialchars((string)$card['card_bin'], ENT_QUOTES, 'UTF-8');
                                        $last4 = htmlspecialchars((string)$card['card_last4'], ENT_QUOTES, 'UTF-8');
                                        $holder = htmlspecialchars((string)$card['holder_name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="carousel-item-card">
                                        <div class="pro-card-widget <?php echo $isPhysical ? 'physical' : ''; ?>">
                                            <div class="card-inner">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <span class="card-badge" style="display:inline-flex; align-items:center; gap:6px;"><i class="fas <?php echo $isPhysical ? 'fa-wallet' : 'fa-cube'; ?>"></i> <?php echo $isPhysical ? 'Physical' : 'Virtual'; ?></span>
                                                    <span class="cards-chip" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;"><?php echo $badge['text']; ?></span>
                                                </div>
                                                <div>
                                                    <div class="card-number"><?php echo $bin; ?> **** **** <?php echo $last4; ?></div>
                                                    <div class="card-meta">
                                                        <div>
                                                            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase;"><?php echo $holder; ?></div>
                                                            <div style="font-size: 0.9rem; font-weight: 500;"><?php echo $monthText; ?>/<?php echo $yearText; ?><?php if (!$isPhysical): ?> <span class="ms-3 text-secondary">CVV ***</span><?php endif; ?></div>
                                                        </div>
                                                        <div class="text-end">
                                                            <i class="<?php echo cards_brand_icon((string)$card['card_brand']); ?> fs-1" style="color:#fff;"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <button type="button" class="btn-action" style="text-decoration:none; color:var(--text-primary); border:1px solid var(--border-light);" data-bs-toggle="offcanvas" data-bs-target="#virtualCardRequestCanvas" aria-controls="virtualCardRequestCanvas">
                            <i class="fas fa-plus text-primary"></i>
                            <span style="font-size: 0.85rem;">Request Card</span>
                        </button>
                        <div class="btn-action" style="opacity:0.72; cursor:default;">
                            <i class="fas fa-shipping-fast text-info"></i>
                            <span style="font-size: 0.85rem;">Track Delivery</span>
                        </div>
                        <div class="btn-action" style="opacity:0.72; cursor:default;">
                            <i class="fas fa-snowflake text-warning"></i>
                            <span style="font-size: 0.85rem;">Freeze</span>
                        </div>
                        <a href="account-security.php" class="btn-action" style="text-decoration:none; color:var(--text-primary);">
                            <i class="fas fa-shield-alt text-secondary"></i>
                            <span style="font-size: 0.85rem;">Security</span>
                        </a>
                    </div>
                </div>

                <div class="col-lg-6 px-3 px-lg-4" id="card-request">
                    <div class="section-header">Request New Card</div>
                    <div class="settings-list">
                        <button type="button" class="cards-action-btn" data-bs-toggle="offcanvas" data-bs-target="#virtualCardRequestCanvas" aria-controls="virtualCardRequestCanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon" style="background: rgba(16, 185, 129, 0.14); color: #047857;"><i class="fas fa-cube"></i></span>
                                <span>
                                    <span style="display:block; font-weight:700; font-size:0.98rem;">Virtual Card Request</span>
                                    <span style="display:block; font-size:0.83rem; color:var(--text-secondary);">Issue through secure right panel</span>
                                </span>
                            </span>
                            <span class="cards-chip" style="background:rgba(16,185,129,0.14); color:#047857;">Virtual</span>
                        </button>
                        <button type="button" class="cards-action-btn" data-bs-toggle="offcanvas" data-bs-target="#physicalCardRequestCanvas" aria-controls="physicalCardRequestCanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon" style="background: rgba(245,158,11,0.16); color: #92400e;"><i class="fas fa-wallet"></i></span>
                                <span>
                                    <span style="display:block; font-weight:700; font-size:0.98rem;">Physical Card Request</span>
                                    <span style="display:block; font-size:0.83rem; color:var(--text-secondary);">Provide shipping details and submit</span>
                                </span>
                            </span>
                            <span class="cards-chip" style="background:rgba(245,158,11,0.16); color:#92400e;">Physical</span>
                        </button>
                    </div>

                    <div class="section-header" style="margin-top:1.3rem;">Delivery Queue</div>
                    <div class="settings-list">
                        <?php
                            $hasProcessing = false;
                            foreach ($userCards as $card) {
                                if ((string)$card['card_type'] === 'physical' && strtolower((string)$card['status']) === 'processing') {
                                    $hasProcessing = true;
                                    break;
                                }
                            }
                        ?>

                        <?php if (!$hasProcessing): ?>
                            <div class="setting-row">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="setting-icon"><i class="fas fa-truck"></i></div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 1rem;">No pending deliveries</div>
                                        <div style="font-size: 0.84rem; color: var(--text-secondary);">Physical card requests will appear here when submitted.</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($userCards as $card): ?>
                                <?php if ((string)$card['card_type'] !== 'physical' || strtolower((string)$card['status']) !== 'processing') { continue; } ?>
                                <div class="setting-row">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="setting-icon"><i class="fas fa-box"></i></div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 1rem;">Physical Card Ending <?php echo htmlspecialchars((string)$card['card_last4'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div style="font-size: 0.84rem; color: var(--text-secondary);">Requested <?php echo htmlspecialchars((string)$card['requested_at'], ENT_QUOTES, 'UTF-8'); ?> · Pending review</div>
                                        </div>
                                    </div>
                                    <span class="cards-chip" style="background:rgba(245, 158, 11, 0.18); color:#92400e;">Pending</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end cards-offcanvas" tabindex="-1" id="virtualCardRequestCanvas" aria-labelledby="virtualCardRequestCanvasLabel">
            <div class="offcanvas-header">
                <div>
                    <h5 class="offcanvas-title" id="virtualCardRequestCanvasLabel" style="font-weight:700;">Request Virtual Card</h5>
                    <div style="font-size:0.82rem; color:var(--text-secondary);">Issued right away and ready for online payments.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="cards-request-box">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.6rem; margin-bottom:0.8rem;">
                        <div style="font-weight:700;">Virtual Card</div>
                        <span class="cards-chip" style="background:rgba(16,185,129,0.14); color:#047857;">Virtual</span>
                    </div>
                    <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.9rem;">A new secure virtual card will be generated and immediately added to your account.</div>
                    <form method="post">
                        <input type="hidden" name="cards_token" value="<?php echo htmlspecialchars($cardsToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="card_action" value="request_virtual">
                        <button type="submit" class="btn btn-dark w-100" style="border-radius: 12px; padding: 0.74rem 0.95rem; background:var(--text-primary); color:var(--bg-body); font-weight:700; border:0;">
                            <i class="fas fa-cube me-2"></i>Issue Virtual Card
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end cards-offcanvas" tabindex="-1" id="physicalCardRequestCanvas" aria-labelledby="physicalCardRequestCanvasLabel">
            <div class="offcanvas-header">
                <div>
                    <h5 class="offcanvas-title" id="physicalCardRequestCanvasLabel" style="font-weight:700;">Request Physical Card</h5>
                    <div style="font-size:0.82rem; color:var(--text-secondary);">Delivery details required before submission.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form method="post" class="cards-grid">
                    <input type="hidden" name="cards_token" value="<?php echo htmlspecialchars($cardsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="card_action" value="request_physical">

                    <div>
                        <div class="cards-label">Full Name</div>
                        <input class="cards-input" type="text" name="shipping_full_name" maxlength="120" required>
                    </div>
                    <div>
                        <div class="cards-label">Address Line 1</div>
                        <input class="cards-input" type="text" name="shipping_address_line1" maxlength="140" required>
                    </div>
                    <div>
                        <div class="cards-label">Address Line 2 (Optional)</div>
                        <input class="cards-input" type="text" name="shipping_address_line2" maxlength="140">
                    </div>
                    <div>
                        <div class="cards-label">City</div>
                        <input class="cards-input" type="text" name="shipping_city" maxlength="80" required>
                    </div>
                    <div>
                        <div class="cards-label">State / Province</div>
                        <input class="cards-input" type="text" name="shipping_state" maxlength="80" required>
                    </div>
                    <div>
                        <div class="cards-label">Postal Code</div>
                        <input class="cards-input" type="text" name="shipping_postal_code" maxlength="30" required>
                    </div>
                    <div>
                        <div class="cards-label">Country</div>
                        <input class="cards-input" type="text" name="shipping_country" maxlength="80" required>
                    </div>
                    <div>
                        <div class="cards-label">Phone Number</div>
                        <input class="cards-input" type="text" name="shipping_phone" maxlength="40" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100" style="border-radius: 12px; padding: 0.74rem 0.95rem; background:var(--text-primary); color:var(--bg-body); font-weight:700; border:0; margin-top:0.35rem;">
                        <i class="fas fa-wallet me-2"></i>Submit Physical Request
                    </button>
                </form>
            </div>
        </div>

    </main>

    <?php require_once 'templates/bottom_nav.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

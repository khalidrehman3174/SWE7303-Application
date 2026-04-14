<?php
require_once '../includes/init.php';
require_login();

$pageTitle = 'FinPay Pro - Account Security';
$activePage = 'security';

function account_add_column_if_missing(mysqli $dbc, array $existingColumns, string $column, string $definition): void
{
    if (isset($existingColumns[$column])) {
        return;
    }

    @mysqli_query($dbc, 'ALTER TABLE users ADD COLUMN ' . $definition);
}

function account_ensure_user_columns(mysqli $dbc): void
{
    $existingColumns = [];
    $result = mysqli_query($dbc, 'SHOW COLUMNS FROM users');
    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $name = isset($row['Field']) ? (string)$row['Field'] : '';
            if ($name !== '') {
                $existingColumns[$name] = true;
            }
        }
    }

    account_add_column_if_missing($dbc, $existingColumns, 'is_verified', 'is_verified TINYINT(1) NOT NULL DEFAULT 0');
    account_add_column_if_missing($dbc, $existingColumns, 'is_banned', 'is_banned TINYINT(1) NOT NULL DEFAULT 0');
    account_add_column_if_missing($dbc, $existingColumns, 'updated_at', 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    // Keep account settings fields independent from strict column ordering in older schemas.
    account_add_column_if_missing($dbc, $existingColumns, 'full_name', 'full_name VARCHAR(120) NULL');
    account_add_column_if_missing($dbc, $existingColumns, 'phone_number', 'phone_number VARCHAR(40) NULL');
    account_add_column_if_missing($dbc, $existingColumns, 'country', 'country VARCHAR(80) NULL');
    account_add_column_if_missing($dbc, $existingColumns, 'privacy_policy_accepted_at', 'privacy_policy_accepted_at DATETIME NULL');
    account_add_column_if_missing($dbc, $existingColumns, 'terms_conditions_accepted_at', 'terms_conditions_accepted_at DATETIME NULL');
    account_add_column_if_missing($dbc, $existingColumns, 'account_status', "account_status VARCHAR(20) NOT NULL DEFAULT 'active'");
    account_add_column_if_missing($dbc, $existingColumns, 'account_closed_at', 'account_closed_at DATETIME NULL');
}

function account_fetch_user_row(mysqli $dbc, int $uid): ?array
{
    $stmt = mysqli_prepare($dbc, 'SELECT * FROM users WHERE id = ? LIMIT 1');
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

account_ensure_user_columns($dbc);

if (empty($_SESSION['account_settings_token'])) {
    $_SESSION['account_settings_token'] = bin2hex(random_bytes(24));
}
$accountSettingsToken = (string)$_SESSION['account_settings_token'];

$accountNotice = '';
$accountNoticeType = 'success';
$openAccountPanel = '';
$currentUserId = (int)$user_id;
$currentUserRow = account_fetch_user_row($dbc, $currentUserId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $openAccountPanel = isset($_POST['account_panel']) ? (string)$_POST['account_panel'] : '';
    $postedToken = isset($_POST['account_token']) ? (string)$_POST['account_token'] : '';
    if (!hash_equals($accountSettingsToken, $postedToken)) {
        $accountNotice = 'Your session token expired. Refresh the page and try again.';
        $accountNoticeType = 'danger';
    } elseif (!$currentUserRow) {
        $accountNotice = 'Could not load your account. Please log in again.';
        $accountNoticeType = 'danger';
    } else {
        $action = isset($_POST['account_action']) ? (string)$_POST['account_action'] : '';

        if ($action === 'personal_details') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
            $country = trim((string)($_POST['country'] ?? ''));

            if ($fullName === '' || strlen($fullName) > 120) {
                $accountNotice = 'Enter a valid full name (max 120 characters).';
                $accountNoticeType = 'danger';
            } elseif (strlen($phoneNumber) > 40) {
                $accountNotice = 'Phone number is too long.';
                $accountNoticeType = 'danger';
            } elseif (strlen($country) > 80) {
                $accountNotice = 'Country is too long.';
                $accountNoticeType = 'danger';
            } else {
                $stmt = mysqli_prepare(
                    $dbc,
                    'UPDATE users SET full_name = ?, phone_number = ?, country = ? WHERE id = ? LIMIT 1'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssi', $fullName, $phoneNumber, $country, $currentUserId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $accountNotice = 'Personal details updated successfully.';
                    $accountNoticeType = 'success';
                } else {
                    $accountNotice = 'Could not update personal details right now.';
                    $accountNoticeType = 'danger';
                }
            }
        } elseif ($action === 'account_details') {
            $username = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));

            if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
                $accountNotice = 'Username must be 3-32 characters and use letters, numbers, or underscore only.';
                $accountNoticeType = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $accountNotice = 'Enter a valid email address.';
                $accountNoticeType = 'danger';
            } else {
                $duplicateStmt = mysqli_prepare(
                    $dbc,
                    'SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1'
                );

                $hasDuplicate = false;
                if ($duplicateStmt) {
                    mysqli_stmt_bind_param($duplicateStmt, 'ssi', $username, $email, $currentUserId);
                    mysqli_stmt_execute($duplicateStmt);
                    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
                    $hasDuplicate = ($duplicateResult instanceof mysqli_result) && mysqli_num_rows($duplicateResult) > 0;
                    mysqli_stmt_close($duplicateStmt);
                }

                if ($hasDuplicate) {
                    $accountNotice = 'That username or email is already in use.';
                    $accountNoticeType = 'danger';
                } else {
                    $stmt = mysqli_prepare(
                        $dbc,
                        'UPDATE users SET username = ?, email = ? WHERE id = ? LIMIT 1'
                    );
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'ssi', $username, $email, $currentUserId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $accountNotice = 'Account details updated successfully.';
                        $accountNoticeType = 'success';
                    } else {
                        $accountNotice = 'Could not update account details right now.';
                        $accountNoticeType = 'danger';
                    }
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            $storedHash = (string)($currentUserRow['password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $accountNotice = 'Fill out all password fields.';
                $accountNoticeType = 'danger';
            } elseif (!password_verify($currentPassword, $storedHash)) {
                $accountNotice = 'Current password is incorrect.';
                $accountNoticeType = 'danger';
            } elseif (strlen($newPassword) < 8) {
                $accountNotice = 'New password must be at least 8 characters.';
                $accountNoticeType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $accountNotice = 'New password and confirmation do not match.';
                $accountNoticeType = 'danger';
            } elseif (password_verify($newPassword, $storedHash)) {
                $accountNotice = 'New password must be different from current password.';
                $accountNoticeType = 'danger';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare(
                    $dbc,
                    'UPDATE users SET password = ? WHERE id = ? LIMIT 1'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'si', $newHash, $currentUserId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $accountNotice = 'Password updated successfully.';
                    $accountNoticeType = 'success';
                } else {
                    $accountNotice = 'Could not change your password right now.';
                    $accountNoticeType = 'danger';
                }
            }
        } elseif ($action === 'privacy_terms') {
            $acceptPrivacy = isset($_POST['accept_privacy']) ? 1 : 0;
            $acceptTerms = isset($_POST['accept_terms']) ? 1 : 0;

            $privacyAcceptedAt = $acceptPrivacy ? date('Y-m-d H:i:s') : null;
            $termsAcceptedAt = $acceptTerms ? date('Y-m-d H:i:s') : null;

            $stmt = mysqli_prepare(
                $dbc,
                'UPDATE users SET privacy_policy_accepted_at = ?, terms_conditions_accepted_at = ? WHERE id = ? LIMIT 1'
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssi', $privacyAcceptedAt, $termsAcceptedAt, $currentUserId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $accountNotice = 'Privacy and terms preferences saved.';
                $accountNoticeType = 'success';
            } else {
                $accountNotice = 'Could not save privacy preferences right now.';
                $accountNoticeType = 'danger';
            }
        } elseif ($action === 'close_account') {
            $closePassword = (string)($_POST['close_password'] ?? '');
            $closeConfirmText = strtoupper(trim((string)($_POST['close_confirm_text'] ?? '')));
            $storedHash = (string)($currentUserRow['password'] ?? '');

            if ($closeConfirmText !== 'CLOSE ACCOUNT') {
                $accountNotice = 'Type CLOSE ACCOUNT exactly to continue.';
                $accountNoticeType = 'danger';
            } elseif (!password_verify($closePassword, $storedHash)) {
                $accountNotice = 'Password verification failed. Account was not closed.';
                $accountNoticeType = 'danger';
            } else {
                $closedStatus = 'closed';
                $stmt = mysqli_prepare(
                    $dbc,
                    'UPDATE users SET is_banned = 1, account_status = ?, account_closed_at = NOW() WHERE id = ? LIMIT 1'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'si', $closedStatus, $currentUserId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    session_unset();
                    session_destroy();
                    header('Location: ../auth/login.php?account_closed=1');
                    exit;
                } else {
                    $accountNotice = 'Could not close account right now. Please contact support.';
                    $accountNoticeType = 'danger';
                }
            }
        }

        $currentUserRow = account_fetch_user_row($dbc, $currentUserId);
    }
}

if (!$currentUserRow) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$displayFullName = trim((string)($currentUserRow['full_name'] ?? ''));
if ($displayFullName === '') {
    $displayFullName = (string)($currentUserRow['username'] ?? '');
}

$displayPhone = (string)($currentUserRow['phone_number'] ?? '');
$displayCountry = (string)($currentUserRow['country'] ?? '');
$displayUsername = (string)($currentUserRow['username'] ?? '');
$displayEmail = (string)($currentUserRow['email'] ?? '');
$privacyAcceptedAt = (string)($currentUserRow['privacy_policy_accepted_at'] ?? '');
$termsAcceptedAt = (string)($currentUserRow['terms_conditions_accepted_at'] ?? '');
$joinedDate = (string)($currentUserRow['created_at'] ?? '');
$isVerified = (int)($currentUserRow['is_verified'] ?? 0) === 1;
?>
<?php require_once 'templates/head.php'; ?>

<body>

    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">

        <header class="mobile-header">
            <a href="index.php" class="profile-btn" style="text-decoration:none;"><i class="fas fa-chevron-left"></i></a>
            <div style="font-weight: 700; letter-spacing: 1px;">ACCOUNT</div>
            <button type="button" class="profile-btn js-theme-swap" style="text-decoration:none;"><i class="fas fa-moon"></i></button>
        </header>

        <div class="cards-layout pt-lg-4 px-lg-4">

            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">User Account Security</h2>
                <div style="display:flex; align-items:center; gap:0.9rem;">
                    <div style="font-weight:600; color:var(--text-secondary); font-size:0.92rem;">Manage identity, credentials, and consent settings</div>
                    <button type="button" class="btn btn-sm js-theme-swap" style="border-radius:999px; border:1px solid var(--border-light); background:var(--bg-surface); color:var(--text-primary); padding:0.42rem 0.8rem; font-weight:600; display:inline-flex; align-items:center; gap:0.45rem;">
                        <i class="fas fa-moon"></i>
                        <span class="js-theme-swap-text">Dark mode</span>
                    </button>
                </div>
            </div>

            <style>
                .account-card {
                    background: var(--list-bg);
                    border: 1px solid var(--border-light);
                    border-radius: 20px;
                    padding: 1.25rem;
                    margin-bottom: 1rem;
                }
                .account-card-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                    margin-bottom: 0.95rem;
                }
                .account-card-title {
                    font-weight: 700;
                    font-size: 1rem;
                    color: var(--text-primary);
                }
                .account-card-sub {
                    font-size: 0.82rem;
                    color: var(--text-secondary);
                }
                .account-field-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 0.85rem;
                }
                @media (min-width: 992px) {
                    .account-field-grid.two {
                        grid-template-columns: 1fr 1fr;
                    }
                }
                .account-label {
                    font-size: 0.82rem;
                    font-weight: 600;
                    color: var(--text-secondary);
                    margin-bottom: 0.35rem;
                }
                .account-input {
                    width: 100%;
                    border: 1px solid var(--border-light);
                    border-radius: 12px;
                    background: var(--bg-surface);
                    color: var(--text-primary);
                    font-size: 0.92rem;
                    font-family: 'Outfit', sans-serif;
                    padding: 0.7rem 0.82rem;
                    min-height: 42px;
                    outline: none;
                }
                .account-input::placeholder {
                    color: var(--text-secondary);
                }
                .account-input:focus {
                    border-color: var(--accent);
                    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.16);
                    background: var(--bg-surface);
                }
                .account-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 0.74rem;
                    border-radius: 999px;
                    padding: 0.35rem 0.62rem;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                }
                .account-pill.ok {
                    color: #047857;
                    background: rgba(16, 185, 129, 0.14);
                }
                .account-pill.warn {
                    color: #92400e;
                    background: rgba(245, 158, 11, 0.18);
                }
                .account-mini-note {
                    font-size: 0.8rem;
                    color: var(--text-secondary);
                }
                #account-security .settings-list {
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0.48) 100%);
                    border: 1px solid rgba(0, 0, 0, 0.08);
                    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.05);
                }
                #account-security .setting-row {
                    background: transparent;
                }
                .account-action-btn {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    width: 100%;
                    border: 0;
                    border-bottom: 1px solid var(--asset-border);
                    background: transparent;
                    padding: 1.15rem 1.25rem;
                    text-align: left;
                    color: var(--text-primary);
                    transition: background-color 0.2s ease, transform 0.2s ease;
                }
                .account-action-btn:last-child {
                    border-bottom: 0;
                }
                .account-action-btn:hover {
                    background: rgba(16, 185, 129, 0.08);
                }
                .account-action-btn:focus-visible {
                    outline: 0;
                    box-shadow: inset 0 0 0 2px rgba(16, 185, 129, 0.36);
                }
                .account-action-btn .setting-icon {
                    border: 1px solid rgba(0, 0, 0, 0.08);
                    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
                }
                .account-action-btn.personal .setting-icon {
                    background: rgba(14, 165, 233, 0.14);
                    color: #0369a1;
                }
                .account-action-btn.details .setting-icon {
                    background: rgba(99, 102, 241, 0.14);
                    color: #4338ca;
                }
                .account-action-btn.password .setting-icon {
                    background: rgba(245, 158, 11, 0.16);
                    color: #92400e;
                }
                .account-action-btn.privacy .setting-icon {
                    background: rgba(16, 185, 129, 0.16);
                    color: #047857;
                }
                .account-action-btn.close .setting-icon {
                    background: rgba(239, 68, 68, 0.16);
                    color: #b91c1c;
                }
                .account-offcanvas {
                    background: linear-gradient(180deg, rgba(247, 248, 250, 0.98) 0%, rgba(244, 245, 247, 0.98) 100%);
                    border-left: 1px solid var(--border-light);
                }
                .account-offcanvas-head {
                    padding-bottom: 1rem;
                    margin-bottom: 0.25rem;
                    border-bottom: 1px solid var(--border-light);
                    background: transparent;
                    border-radius: 0;
                }
                .account-back-btn {
                    width: 38px;
                    height: 38px;
                    border-radius: 10px;
                    border: 1px solid var(--border-light);
                    background: var(--bg-surface);
                    color: var(--text-primary);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
                }
                .account-back-btn:hover {
                    background: rgba(16, 185, 129, 0.12);
                    color: #047857;
                    transform: translateX(-1px);
                }
                .account-offcanvas-body {
                    padding-top: 0.85rem !important;
                    gap: 1rem;
                }
                .account-offcanvas-form {
                    background: transparent;
                    border: 0;
                    border-radius: 0;
                    padding: 0;
                    box-shadow: none;
                    display: flex;
                    flex-direction: column;
                    gap: 0.9rem;
                }
                .account-form-content {
                    display: grid;
                    gap: 0.85rem;
                }
                .account-form-actions {
                    margin-top: 0.25rem;
                    padding-top: 0.35rem;
                }
                .account-submit-btn {
                    width: 100%;
                    min-height: 44px;
                }
                .account-check-row {
                    display: flex;
                    gap: 10px;
                    align-items: flex-start;
                    font-size: 0.9rem;
                    color: var(--text-primary);
                    line-height: 1.45;
                }
                .account-check-row input[type="checkbox"] {
                    width: 18px;
                    height: 18px;
                    margin-top: 2px;
                    accent-color: var(--accent);
                    cursor: pointer;
                }
            </style>

            <div class="row" id="account-security">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <div class="settings-list">
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-user-shield"></i></div>
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem;">Identity Status</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Verification and account state</div>
                                </div>
                            </div>
                            <?php if ($isVerified): ?>
                                <span class="account-pill ok"><i class="fas fa-check-circle"></i> VERIFIED</span>
                            <?php else: ?>
                                <span class="account-pill warn"><i class="fas fa-clock"></i> PENDING</span>
                            <?php endif; ?>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem;">Profile Name</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($displayFullName, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem;">Login Email</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-calendar-alt"></i></div>
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem;">Joined</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($joinedDate !== '' ? date('d M Y', strtotime($joinedDate)) : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 px-3 px-lg-4">
                    <?php if ($accountNotice !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($accountNoticeType, ENT_QUOTES, 'UTF-8'); ?> py-2 mb-3" style="border-radius:12px; font-size:0.9rem;">
                            <?php echo htmlspecialchars($accountNotice, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="section-header">Account Actions</div>
                    <div class="settings-list">
                        <button type="button" class="account-action-btn personal" data-bs-toggle="offcanvas" data-bs-target="#accountPersonalOffcanvas" aria-controls="accountPersonalOffcanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon"><i class="fas fa-id-card"></i></span>
                                <span>
                                    <span style="font-weight: 600; font-size: 1rem; display:block;">Personal Details</span>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Full name, phone, and country</span>
                                </span>
                            </span>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </button>

                        <button type="button" class="account-action-btn details" data-bs-toggle="offcanvas" data-bs-target="#accountDetailsOffcanvas" aria-controls="accountDetailsOffcanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon"><i class="fas fa-user-cog"></i></span>
                                <span>
                                    <span style="font-weight: 600; font-size: 1rem; display:block;">Account Details</span>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Username and email identity</span>
                                </span>
                            </span>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </button>

                        <button type="button" class="account-action-btn password" data-bs-toggle="offcanvas" data-bs-target="#accountPasswordOffcanvas" aria-controls="accountPasswordOffcanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon"><i class="fas fa-lock"></i></span>
                                <span>
                                    <span style="font-weight: 600; font-size: 1rem; display:block;">Change Password</span>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Update your account password securely</span>
                                </span>
                            </span>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </button>

                        <button type="button" class="account-action-btn privacy" data-bs-toggle="offcanvas" data-bs-target="#accountPrivacyOffcanvas" aria-controls="accountPrivacyOffcanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon"><i class="fas fa-file-signature"></i></span>
                                <span>
                                    <span style="font-weight: 600; font-size: 1rem; display:block;">Privacy and Terms</span>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Consent and legal preferences</span>
                                </span>
                            </span>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </button>

                        <button type="button" class="account-action-btn close" data-bs-toggle="offcanvas" data-bs-target="#accountCloseOffcanvas" aria-controls="accountCloseOffcanvas">
                            <span class="d-flex align-items-center gap-3">
                                <span class="setting-icon" style="color:#b91c1c;"><i class="fas fa-user-slash"></i></span>
                                <span>
                                    <span style="font-weight: 600; font-size: 1rem; display:block; color:#b91c1c;">Close Account</span>
                                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Disable account and end access</span>
                                </span>
                            </span>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end chat-modal account-offcanvas" tabindex="-1" id="accountPersonalOffcanvas" style="z-index: 10520;">
            <div class="chat-header account-offcanvas-head align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn account-back-btn" aria-label="Close personal details"><i class="fas fa-chevron-left"></i></button>
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem;">Personal Details</div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Profile identity</div>
                    </div>
                </div>
            </div>
            <div class="chat-body account-offcanvas-body">
                <form method="POST" class="account-offcanvas-form" autocomplete="on">
                    <input type="hidden" name="account_action" value="personal_details">
                    <input type="hidden" name="account_panel" value="personal">
                    <input type="hidden" name="account_token" value="<?php echo htmlspecialchars($accountSettingsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="account-form-content account-field-grid">
                        <div>
                            <label class="account-label" for="full_name">Full Name</label>
                            <input id="full_name" name="full_name" class="account-input" type="text" maxlength="120" value="<?php echo htmlspecialchars($displayFullName, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label class="account-label" for="phone_number">Phone Number</label>
                            <input id="phone_number" name="phone_number" class="account-input" type="text" maxlength="40" value="<?php echo htmlspecialchars($displayPhone, ENT_QUOTES, 'UTF-8'); ?>" placeholder="+44 7xxx xxx xxx">
                        </div>
                        <div>
                            <label class="account-label" for="country">Country</label>
                            <input id="country" name="country" class="account-input" type="text" maxlength="80" value="<?php echo htmlspecialchars($displayCountry, ENT_QUOTES, 'UTF-8'); ?>" placeholder="United Kingdom">
                        </div>
                    </div>
                    <div class="account-form-actions">
                        <button type="submit" class="btn-pro btn-pro-primary account-submit-btn"><i class="fas fa-save"></i> Save Personal Details</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="offcanvas offcanvas-end chat-modal account-offcanvas" tabindex="-1" id="accountDetailsOffcanvas" style="z-index: 10520;">
            <div class="chat-header account-offcanvas-head align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn account-back-btn" aria-label="Close account details"><i class="fas fa-chevron-left"></i></button>
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem;">Account Details</div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Login identity</div>
                    </div>
                </div>
            </div>
            <div class="chat-body account-offcanvas-body">
                <form method="POST" class="account-offcanvas-form" autocomplete="on">
                    <input type="hidden" name="account_action" value="account_details">
                    <input type="hidden" name="account_panel" value="details">
                    <input type="hidden" name="account_token" value="<?php echo htmlspecialchars($accountSettingsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="account-form-content account-field-grid">
                        <div>
                            <label class="account-label" for="username">Username</label>
                            <input id="username" name="username" class="account-input" type="text" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" value="<?php echo htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label class="account-label" for="email">Email Address</label>
                            <input id="email" name="email" class="account-input" type="email" maxlength="190" value="<?php echo htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label class="account-label">User ID</label>
                            <input class="account-input" type="text" value="<?php echo htmlspecialchars((string)$currentUserId, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                    </div>
                    <div class="account-form-actions">
                        <button type="submit" class="btn-pro btn-pro-primary account-submit-btn"><i class="fas fa-user-pen"></i> Save Account Details</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="offcanvas offcanvas-end chat-modal account-offcanvas" tabindex="-1" id="accountPasswordOffcanvas" style="z-index: 10520;">
            <div class="chat-header account-offcanvas-head align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn account-back-btn" aria-label="Close password form"><i class="fas fa-chevron-left"></i></button>
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem;">Change Password</div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Credential security</div>
                    </div>
                </div>
            </div>
            <div class="chat-body account-offcanvas-body">
                <form method="POST" class="account-offcanvas-form" autocomplete="off">
                    <input type="hidden" name="account_action" value="change_password">
                    <input type="hidden" name="account_panel" value="password">
                    <input type="hidden" name="account_token" value="<?php echo htmlspecialchars($accountSettingsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="account-form-content">
                        <div>
                            <label class="account-label" for="current_password">Current Password</label>
                            <input id="current_password" name="current_password" class="account-input" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="account-field-grid">
                            <div>
                                <label class="account-label" for="new_password">New Password</label>
                                <input id="new_password" name="new_password" class="account-input" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div>
                                <label class="account-label" for="confirm_password">Confirm New Password</label>
                                <input id="confirm_password" name="confirm_password" class="account-input" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>
                    </div>
                    <div class="account-form-actions">
                        <button type="submit" class="btn-pro btn-pro-primary account-submit-btn"><i class="fas fa-lock"></i> Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="offcanvas offcanvas-end chat-modal account-offcanvas" tabindex="-1" id="accountPrivacyOffcanvas" style="z-index: 10520;">
            <div class="chat-header account-offcanvas-head align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn account-back-btn" aria-label="Close privacy form"><i class="fas fa-chevron-left"></i></button>
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem;">Privacy and Terms</div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Legal preferences</div>
                    </div>
                </div>
            </div>
            <div class="chat-body account-offcanvas-body">
                <form method="POST" class="account-offcanvas-form" autocomplete="off">
                    <input type="hidden" name="account_action" value="privacy_terms">
                    <input type="hidden" name="account_panel" value="privacy">
                    <input type="hidden" name="account_token" value="<?php echo htmlspecialchars($accountSettingsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="account-check-row">
                        <input type="checkbox" name="accept_privacy" <?php echo $privacyAcceptedAt !== '' ? 'checked' : ''; ?>>
                        <span>I have read and accept the <a href="../privacy.php" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                    </label>
                    <label class="account-check-row">
                        <input type="checkbox" name="accept_terms" <?php echo $termsAcceptedAt !== '' ? 'checked' : ''; ?>>
                        <span>I have read and accept the <a href="../terms.php" target="_blank" rel="noopener">Terms and Conditions</a>.</span>
                    </label>
                    <div class="account-mini-note">
                        Privacy accepted: <?php echo htmlspecialchars($privacyAcceptedAt !== '' ? $privacyAcceptedAt : 'Not accepted yet', ENT_QUOTES, 'UTF-8'); ?><br>
                        Terms accepted: <?php echo htmlspecialchars($termsAcceptedAt !== '' ? $termsAcceptedAt : 'Not accepted yet', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="account-form-actions">
                        <button type="submit" class="btn-pro btn-pro-primary account-submit-btn"><i class="fas fa-file-signature"></i> Save Privacy and Terms</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="offcanvas offcanvas-end chat-modal account-offcanvas" tabindex="-1" id="accountCloseOffcanvas" style="z-index: 10520;">
            <div class="chat-header account-offcanvas-head align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" data-bs-dismiss="offcanvas" class="transfer-back-btn account-back-btn" aria-label="Close account closure form"><i class="fas fa-chevron-left"></i></button>
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem; color:#b91c1c;">Close Account</div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px;">Irreversible action</div>
                    </div>
                </div>
            </div>
            <div class="chat-body account-offcanvas-body">
                <form method="POST" class="account-offcanvas-form" autocomplete="off">
                    <input type="hidden" name="account_action" value="close_account">
                    <input type="hidden" name="account_panel" value="close">
                    <input type="hidden" name="account_token" value="<?php echo htmlspecialchars($accountSettingsToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="account-form-content">
                        <div class="account-mini-note" style="color:#b91c1c; font-weight:600;">
                            Type CLOSE ACCOUNT and enter your password to permanently close this profile.
                        </div>
                        <div class="account-field-grid">
                            <div>
                                <label class="account-label" for="close_confirm_text">Confirmation Text</label>
                                <input id="close_confirm_text" name="close_confirm_text" class="account-input" type="text" placeholder="CLOSE ACCOUNT" required>
                            </div>
                            <div>
                                <label class="account-label" for="close_password">Password</label>
                                <input id="close_password" name="close_password" class="account-input" type="password" autocomplete="current-password" required>
                            </div>
                        </div>
                    </div>
                    <div class="account-form-actions">
                        <button type="submit" class="btn-pro account-submit-btn" style="background:#b91c1c; color:#fff;" onclick="return confirm('Are you sure you want to close your account? This will log you out immediately.');"><i class="fas fa-user-slash"></i> Close Account</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var panelKey = <?php echo json_encode($openAccountPanel, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            if (!panelKey || !window.bootstrap || !window.bootstrap.Offcanvas) {
                return;
            }

            var panelMap = {
                personal: 'accountPersonalOffcanvas',
                details: 'accountDetailsOffcanvas',
                password: 'accountPasswordOffcanvas',
                privacy: 'accountPrivacyOffcanvas',
                close: 'accountCloseOffcanvas'
            };

            var targetId = panelMap[panelKey] || '';
            if (!targetId) {
                return;
            }

            var node = document.getElementById(targetId);
            if (!node) {
                return;
            }

            var instance = window.bootstrap.Offcanvas.getOrCreateInstance(node);
            instance.show();
        });
    </script>

    <?php require_once 'templates/bottom_nav.php'; ?>

</body>
</html>

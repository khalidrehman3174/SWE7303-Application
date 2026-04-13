<?php
$pageTitle = 'FinPay Pro - Payments';
$activePage = 'payments';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../includes/db_connect.php';

$userId = (int)$_SESSION['user_id'];
$contactMessage = '';
$contactMessageType = 'success';
$formRecipientName = '';
$formSortCode = '';
$formAccountNumber = '';
$recipientNameMaxLength = 80;

mysqli_query($dbc, "CREATE TABLE IF NOT EXISTS payment_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipient_name VARCHAR(120) NOT NULL,
    sort_code VARCHAR(8) NOT NULL,
    account_number VARCHAR(8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at)
)");

mysqli_query($dbc, "CREATE TABLE IF NOT EXISTS payment_contact_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contact_id INT NOT NULL,
    direction ENUM('sent','received') NOT NULL DEFAULT 'sent',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_contact_created (user_id, contact_id, created_at)
)");

$contacts = [];
$stmt = mysqli_prepare($dbc, 'SELECT id, recipient_name, sort_code, account_number, created_at FROM payment_contacts WHERE user_id = ? ORDER BY created_at DESC LIMIT 500');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $contacts[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

$contactHistories = [];
$contactIdList = array_map(static fn($c) => (int)$c['id'], $contacts);
if (!empty($contactIdList)) {
    $contactIdList = array_values(array_unique(array_filter($contactIdList, static fn($id) => $id > 0)));
}

if (!empty($contactIdList)) {
    $placeholders = implode(',', array_fill(0, count($contactIdList), '?'));
    $txSql = "SELECT contact_id, direction, amount, note, created_at
              FROM payment_contact_transactions
              WHERE user_id = ? AND contact_id IN ($placeholders)
              ORDER BY created_at DESC
              LIMIT 1000";
    $txStmt = mysqli_prepare($dbc, $txSql);

    if ($txStmt) {
        $types = 'i' . str_repeat('i', count($contactIdList));
        $params = array_merge([$userId], $contactIdList);
        $bindRefs = [];
        foreach ($params as $k => $v) {
            $bindRefs[$k] = &$params[$k];
        }

        mysqli_stmt_bind_param($txStmt, $types, ...$bindRefs);
        mysqli_stmt_execute($txStmt);
        $txResult = mysqli_stmt_get_result($txStmt);
        if ($txResult) {
            while ($tx = mysqli_fetch_assoc($txResult)) {
                $cid = (int)($tx['contact_id'] ?? 0);
                if (!isset($contactHistories[$cid])) {
                    $contactHistories[$cid] = [];
                }
                if (count($contactHistories[$cid]) >= 40) {
                    continue;
                }

                $amount = (float)($tx['amount'] ?? 0);
                $direction = (($tx['direction'] ?? 'sent') === 'received') ? 'received' : 'sent';
                $contactHistories[$cid][] = [
                    'direction' => $direction,
                    'amount' => '£' . number_format($amount, 2),
                    'note' => (string)($tx['note'] ?? ''),
                    'timestamp' => (string)($tx['created_at'] ?? ''),
                    'time' => date('d M, H:i', strtotime((string)($tx['created_at'] ?? 'now'))),
                ];
            }
        }
        mysqli_stmt_close($txStmt);
    }
}

$contactsJsonMap = [];
foreach ($contacts as $contact) {
    $cid = (int)($contact['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }

    $name = (string)($contact['recipient_name'] ?? 'Contact');
    $contactsJsonMap[$cid] = [
        'id' => $cid,
        'name' => $name,
        'handle' => '@' . strtolower(preg_replace('/\s+/', '', $name)),
        'initials' => payments_contact_initials($name),
        'sortCode' => (string)($contact['sort_code'] ?? ''),
        'accountNumber' => (string)($contact['account_number'] ?? ''),
        'history' => $contactHistories[$cid] ?? [],
    ];
}

function payments_contact_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    if (!$parts || count($parts) === 0) {
        return 'U';
    }
    $first = strtoupper(substr($parts[0], 0, 1));
    $last = count($parts) > 1 ? strtoupper(substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . $last;
}

function payments_contact_time_label(?string $createdAt): string
{
    if (empty($createdAt)) {
        return 'Recently';
    }

    $createdTs = strtotime($createdAt);
    if ($createdTs === false) {
        return 'Recently';
    }

    $today = strtotime(date('Y-m-d'));
    $entryDay = strtotime(date('Y-m-d', $createdTs));
    if ($entryDay === $today) {
        return 'Today';
    }
    if ($entryDay === strtotime('-1 day', $today)) {
        return 'Yesterday';
    }

    return date('d M', $createdTs);
}

require_once 'templates/head.php';
?>

<body>

    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">
        
        <header class="mobile-header">
            <div class="profile-btn"><i class="fas fa-qrcode"></i></div>
            <div style="font-weight: 700; letter-spacing: 1px;">PAYMENTS</div>
            <div class="profile-btn" data-bs-toggle="modal" data-bs-target="#newPaymentModal"><i class="fas fa-plus"></i></div>
        </header>

        <!-- Desktop Title -->
        <div class="d-none d-lg-flex justify-content-between align-items-center pt-5 px-lg-5 pb-2">
            <h2 class="fw-bold mb-0" style="font-family: 'Outfit';">Payments Center</h2>
            <div style="font-size: 0.95rem; color: var(--text-secondary); font-weight: 500;"><i class="fas fa-shield-alt text-success me-2"></i>Bank-grade Security</div>
        </div>

        <div class="content-grid px-lg-5 mt-lg-3">
            
            <!-- Left Panel -->
            <div class="panel-left">

                <div id="paymentsFlashMessage" class="mx-3 mx-lg-0 mb-3" style="<?php echo $contactMessage !== '' ? '' : 'display: none;'; ?> padding: 0.75rem 1rem; border-radius: 14px; border: 1px solid <?php echo $contactMessageType === 'success' ? 'rgba(16, 185, 129, 0.35)' : 'rgba(239, 68, 68, 0.35)'; ?>; background: <?php echo $contactMessageType === 'success' ? 'rgba(16, 185, 129, 0.10)' : 'rgba(239, 68, 68, 0.10)'; ?>; color: <?php echo $contactMessageType === 'success' ? '#10b981' : '#ef4444'; ?>; font-weight: 600; font-size: 0.9rem;">
                    <?php echo htmlspecialchars($contactMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <!-- Premium Master Search Bar -->
                <div class="glass-panel mx-3 mx-lg-0 mb-4 mb-lg-5 d-flex align-items-center" style="padding: 8px 8px 8px 20px; border-radius: 100px;">
                    <i class="fas fa-search" style="color: var(--text-secondary); font-size: 1.1rem;"></i>
                    <input type="text" id="paymentsSearchInput" placeholder="Search payments..." style="flex: 1; min-width: 0; background: transparent; border: none; color: var(--text-primary); font-family: 'Outfit', sans-serif; font-size: 1rem; outline: none; padding-left: 12px; padding-right: 12px;">
                    <button class="btn-pro btn-pro-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newPaymentModal" style="flex: 0 0 auto; padding: 10px 24px; border-radius: 100px; font-size: 0.95rem;"><i class="fas fa-plus"></i> New</button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 px-3 px-lg-0">
                    <h3 class="section-heading mb-0">Recent Activity</h3>
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;">See All <i class="fas fa-chevron-right ms-1"></i></div>
                </div>
                
                <!-- Vertical Chat/Send List inside Glass Panel -->
                <div class="glass-panel mx-3 mx-lg-0 mb-5" style="border-radius: 24px; padding: 1rem 1.5rem;">
                    
                    <!-- Send to New -->
                    <div class="asset-row px-0" data-bs-toggle="modal" data-bs-target="#newPaymentModal" style="border-radius: 0; padding-bottom: 1rem !important; padding-top: 0.5rem !important; border-bottom: 1px solid var(--border-light);">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--list-bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--accent); border: 1px dashed var(--accent);"><i class="fas fa-plus"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1.05rem; color: var(--accent);">Send to New Contact</div>
                        </div>
                    </div>

                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $idx => $contact): ?>
                            <?php
                                $initials = payments_contact_initials((string)$contact['recipient_name']);
                                $sortCode = (string)$contact['sort_code'];
                                $accountNumber = (string)$contact['account_number'];
                                $formattedSort = substr($sortCode, 0, 2) . '-' . substr($sortCode, 2, 2) . '-' . substr($sortCode, 4, 2);
                                $maskedAccount = '****' . substr($accountNumber, -4);
                                $timeLabel = payments_contact_time_label($contact['created_at'] ?? null);
                                $isLast = $idx === count($contacts) - 1;
                                $contactId = (int)($contact['id'] ?? 0);
                                $latestTx = ($contactId > 0 && isset($contactHistories[$contactId]) && !empty($contactHistories[$contactId]))
                                    ? $contactHistories[$contactId][0]
                                    : null;
                                $latestDirection = (string)($latestTx['direction'] ?? '');
                                $latestAmount = (string)($latestTx['amount'] ?? '');
                                $latestTimestamp = (string)($latestTx['timestamp'] ?? '');

                                if ($latestTx) {
                                    $activitySubline = $latestDirection === 'received'
                                        ? ('You received ' . $latestAmount)
                                        : ('You sent ' . $latestAmount);
                                    $displayTimeLabel = payments_contact_time_label($latestTimestamp !== '' ? $latestTimestamp : ($contact['created_at'] ?? null));
                                } else {
                                    $activitySubline = 'Sort ' . $formattedSort . ' • ' . $maskedAccount;
                                    $displayTimeLabel = $timeLabel;
                                }
                            ?>
                            <div class="asset-row px-0 contact-row contact-search-item" data-bs-toggle="offcanvas" data-bs-target="#chatPaymentModal" data-contact-name="<?php echo htmlspecialchars((string)$contact['recipient_name'], ENT_QUOTES, 'UTF-8'); ?>" data-contact-handle="@<?php echo htmlspecialchars(strtolower(str_replace(' ', '', (string)$contact['recipient_name'])), ENT_QUOTES, 'UTF-8'); ?>" data-search-text="<?php echo htmlspecialchars(strtolower((string)$contact['recipient_name'] . ' ' . $activitySubline . ' ' . $formattedSort . ' ' . $maskedAccount), ENT_QUOTES, 'UTF-8'); ?>" data-contact-id="<?php echo (int)$contact['id']; ?>" style="border-radius: 0; padding: 1rem 0 !important; <?php echo $isLast ? 'border: none;' : 'border-bottom: 1px solid var(--border-light);'; ?>">
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(59,130,246,0.15); color: #3b82f6; display:flex; align-items:center; justify-content:center; font-weight: 700; font-size: 0.95rem;"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="asset-info ml-3">
                                    <div class="asset-name" style="font-size: 1.05rem;"><?php echo htmlspecialchars((string)$contact['recipient_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="asset-sub"><?php echo htmlspecialchars($activitySubline, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="asset-value text-end" style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                    <div class="asset-sub" style="font-size: 0.8rem; margin-top: 0;"><?php echo htmlspecialchars($displayTimeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="asset-row px-0" style="border-radius: 0; padding-top: 1rem !important; padding-bottom: 0.5rem !important; border: none; cursor: default;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--list-bg); color: var(--text-secondary); display:flex; align-items:center; justify-content:center;"><i class="fas fa-user-plus"></i></div>
                            <div class="asset-info ml-3">
                                <div class="asset-name" style="font-size: 1.05rem;">No contacts yet</div>
                                <div class="asset-sub">Add your first contact to start sending payments.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="paymentsNoResults" class="asset-row px-0" style="display: none; border-radius: 0; padding-top: 1rem !important; padding-bottom: 0.5rem !important; border: none; cursor: default;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--list-bg); color: var(--text-secondary); display:flex; align-items:center; justify-content:center;"><i class="fas fa-search"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1.05rem;">No matching contacts</div>
                            <div class="asset-sub">Try a different name or bank detail search.</div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button id="loadMoreContactsBtn" type="button" class="btn-pro w-100" style="display: none; background: var(--list-bg); color: var(--text-primary); border: 1px solid var(--border-light);">Load More Contacts</button>
                    </div>

                </div>
                
            </div>

            <!-- Right Panel: Upcoming Payments -->
            <div class="panel-right px-3 px-lg-0 mt-2 mt-lg-0">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Upcoming</h3>
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;">Manage <i class="fas fa-cog ms-1"></i></div>
                </div>

                <div class="glass-panel" style="padding: 1.5rem; border-radius: 24px; margin-bottom: 3rem;">
                    
                    <div class="asset-row px-0 pt-0" style="padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); border-radius: 0;">
                        <div class="asset-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i class="fab fa-spotify"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 1rem;">Spotify Premium</div>
                            <div class="asset-sub">Direct Debit • Tomorrow</div>
                        </div>
                        <div class="asset-value">
                            <div class="asset-price" style="font-size: 1.05rem;">£10.99</div>
                        </div>
                    </div>

                    <div class="asset-row px-0" style="padding: 1rem 0; border-bottom: 1px solid var(--border-light); border-radius: 0;">
                        <div class="asset-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i class="fas fa-bolt"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 1rem;">Electricity Bill</div>
                            <div class="asset-sub">Direct Debit • 28th March</div>
                        </div>
                        <div class="asset-value">
                            <div class="asset-price" style="font-size: 1.05rem;">£48.20</div>
                        </div>
                    </div>

                    <!-- Add New Sub -->
                    <div class="asset-row px-0 pb-0" style="padding-top: 1rem; border-bottom: none; border-radius: 0;">
                        <div class="asset-icon" style="background: var(--list-bg); color: var(--text-secondary); width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i class="fas fa-plus"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 1rem; color: var(--text-primary);">Add Scheduled Payment</div>
                        </div>
                        <div class="asset-value">
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </main>

    <?php require_once 'templates/bottom_nav.php'; ?>

    <!-- Chat Payment Modal (Offcanvas) -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="chatPaymentModal">
        <div class="chat-header">
            <div data-bs-dismiss="offcanvas" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface-light); transition: background 0.2s;"><i class="fas fa-chevron-down" style="transform: rotate(90deg);"></i></div>
            <div id="chatContactAvatar" style="width: 44px; height: 44px; border-radius: 14px; background: rgba(59,130,246,0.15); color:#3b82f6; display:flex; align-items:center; justify-content:center; font-weight: 700; font-size: 0.9rem;">AL</div>
            <div>
                <div id="chatContactName" style="font-weight: 700; font-size: 1.05rem;">Contact</div>
                <div id="chatContactHandle" style="font-size: 0.8rem; color: var(--text-secondary);">@contact</div>
            </div>
            <div class="dropdown" style="margin-left: auto;">
                <button type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--border-light); background: var(--bg-surface-light); color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center;"><i class="fas fa-ellipsis-h"></i></button>
                <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: 1px solid var(--border-light);">
                    <li><button type="button" id="chatDropdownSoundToggle" class="dropdown-item">Disable Sound</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" id="chatDropdownEditContact" class="dropdown-item">Edit Contact</button></li>
                    <li><button type="button" id="chatDropdownDeleteContact" class="dropdown-item text-danger">Delete Contact</button></li>
                </ul>
            </div>
        </div>
        
        <div class="chat-body" id="chatHistoryBox"></div>

        <div class="chat-footer">
            <div id="offcanvasPaymentMessage" style="display: none; opacity: 0; margin-bottom: 0.45rem; padding: 0.42rem 0.62rem; border-radius: 9px; font-size: 0.76rem; font-weight: 500; letter-spacing: 0.1px; transition: opacity 180ms ease;"></div>
            <div id="chatAvailableBalance" style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.45rem;">Available: £0.00</div>
            <input type="text" id="chatAmountInput" class="chat-amount" placeholder="£ 0.00" inputmode="decimal">
            <div style="display: flex; gap: 10px; align-items: stretch;">
                <div class="search-wrap" style="flex: 1;">
                    <input type="text" id="chatNoteInput" placeholder="Add a note..." maxlength="255" style="padding-left: 20px; border-radius: 20px; background: var(--list-bg);">
                </div>
                <button id="sendPaymentBtn" class="btn-pro btn-pro-primary" style="flex: 0 0 auto; width: 56px; border-radius: 20px; padding: 0;"><i class="fas fa-arrow-up"></i></button>
            </div>
        </div>
    </div>

    <!-- New Payment Modal (Bank Details) -->
    <div class="modal fade" id="newPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 24px; color: var(--text-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.5rem;">
                    <h5 class="modal-title fw-bold" style="font-family: 'Outfit', sans-serif;">New Bank Transfer</h5>
                    <div data-bs-dismiss="modal" style="cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--list-bg); transition: background 0.2s;"><i class="fas fa-times"></i></div>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <form method="POST" action="" id="newPaymentForm" novalidate data-no-loading>
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Recipient Name</label>
                        <input type="text" name="recipient_name" value="<?php echo htmlspecialchars($formRecipientName, ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="e.g. John Doe" maxlength="<?php echo (int)$recipientNameMaxLength; ?>" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                        <div class="text-secondary" style="font-size: 0.75rem; margin-top: 6px;">Maximum <?php echo (int)$recipientNameMaxLength; ?> characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Sort Code</label>
                        <input type="text" name="sort_code" value="<?php echo htmlspecialchars($formSortCode, ENT_QUOTES, 'UTF-8'); ?>" class="form-control sort-code-input" placeholder="00-00-00" inputmode="numeric" maxlength="8" minlength="8" pattern="\d{2}-\d{2}-\d{2}" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif; letter-spacing: 2px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Account Number</label>
                        <input type="text" name="account_number" value="<?php echo htmlspecialchars($formAccountNumber, ENT_QUOTES, 'UTF-8'); ?>" class="form-control account-number-input" placeholder="12345678" inputmode="numeric" maxlength="8" minlength="8" pattern="\d{8}" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif; letter-spacing: 2px;">
                    </div>
                    <button type="submit" class="btn-pro btn-pro-primary w-100">Add Contact</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Contact Modal -->
    <div class="modal fade" id="editContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 24px; color: var(--text-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.5rem;">
                    <h5 class="modal-title fw-bold" style="font-family: 'Outfit', sans-serif;">Edit Contact</h5>
                    <div data-bs-dismiss="modal" style="cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--list-bg); transition: background 0.2s;"><i class="fas fa-times"></i></div>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <form method="POST" action="" id="editContactForm" novalidate data-no-loading>
                    <input type="hidden" name="contact_id" id="editContactId" value="0">
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Recipient Name</label>
                        <input type="text" id="editRecipientName" name="recipient_name" class="form-control" placeholder="e.g. John Doe" maxlength="<?php echo (int)$recipientNameMaxLength; ?>" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                        <div class="text-secondary" style="font-size: 0.75rem; margin-top: 6px;">Maximum <?php echo (int)$recipientNameMaxLength; ?> characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Sort Code</label>
                        <input type="text" id="editSortCode" name="sort_code" class="form-control sort-code-input" placeholder="00-00-00" inputmode="numeric" maxlength="8" minlength="8" pattern="\d{2}-\d{2}-\d{2}" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif; letter-spacing: 2px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-secondary" style="font-size: 0.85rem; font-weight: 500;">Account Number</label>
                        <input type="text" id="editAccountNumber" name="account_number" class="form-control account-number-input" placeholder="12345678" inputmode="numeric" maxlength="8" minlength="8" pattern="\d{8}" required style="background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px; color: var(--text-primary); font-family: 'Outfit', sans-serif; letter-spacing: 2px;">
                    </div>
                    <button type="submit" class="btn-pro btn-pro-primary w-100">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Contact Confirmation Modal -->
    <div class="modal fade" id="deleteContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 24px; color: var(--text-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-light); padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title fw-bold" style="font-family: 'Outfit', sans-serif;">Delete Contact</h5>
                    <div data-bs-dismiss="modal" style="cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--list-bg); transition: background 0.2s;"><i class="fas fa-times"></i></div>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <p style="margin-bottom: 0.6rem; color: var(--text-primary);">Remove <strong id="deleteContactNameText">this contact</strong> from your saved list?</p>
                    <p style="margin: 0 0 1.25rem; color: var(--text-secondary); font-size: 0.9rem;">This only deletes the saved contact and does not affect previous payments.</p>
                    <form method="POST" action="" id="deleteContactForm" data-no-loading>
                        <input type="hidden" name="contact_id" id="deleteContactId" value="0">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="button" class="btn-pro" data-bs-dismiss="modal" style="background: var(--list-bg); color: var(--text-primary); border: 1px solid var(--border-light);">Cancel</button>
                            <button type="submit" class="btn-pro" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35);">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let CONTACT_DATA = <?php echo json_encode($contactsJsonMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        let currentContactId = 0;

        const chatModal = document.getElementById('chatPaymentModal');
        const chatHistoryBox = document.getElementById('chatHistoryBox');
        const chatContactName = document.getElementById('chatContactName');
        const chatContactHandle = document.getElementById('chatContactHandle');
        const chatContactAvatar = document.getElementById('chatContactAvatar');

        const chatDropdownEditContact = document.getElementById('chatDropdownEditContact');
        const chatDropdownDeleteContact = document.getElementById('chatDropdownDeleteContact');
        const chatDropdownSoundToggle = document.getElementById('chatDropdownSoundToggle');

        const editModalEl = document.getElementById('editContactModal');
        const deleteModalEl = document.getElementById('deleteContactModal');
        const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;

        const editContactId = document.getElementById('editContactId');
        const editRecipientName = document.getElementById('editRecipientName');
        const editSortCode = document.getElementById('editSortCode');
        const editAccountNumber = document.getElementById('editAccountNumber');

        const deleteContactId = document.getElementById('deleteContactId');
        const deleteContactNameText = document.getElementById('deleteContactNameText');

        const paymentsSearchInput = document.getElementById('paymentsSearchInput');
        const paymentsNoResults = document.getElementById('paymentsNoResults');
        const loadMoreContactsBtn = document.getElementById('loadMoreContactsBtn');
        const chatAmountInput = document.getElementById('chatAmountInput');
        const chatNoteInput = document.getElementById('chatNoteInput');
        const sendPaymentBtn = document.getElementById('sendPaymentBtn');
        const chatAvailableBalance = document.getElementById('chatAvailableBalance');
        const offcanvasPaymentMessage = document.getElementById('offcanvasPaymentMessage');
        const paymentsFlashMessage = document.getElementById('paymentsFlashMessage');
        const newPaymentForm = document.getElementById('newPaymentForm');
        const editContactForm = document.getElementById('editContactForm');
        const deleteContactForm = document.getElementById('deleteContactForm');
        const contactItems = Array.from(document.querySelectorAll('.contact-search-item'));
        const addContactApiUrl = '../api/v1/payments/contacts/create.php';
        const updateContactApiUrl = '../api/v1/payments/contacts/update.php';
        const deleteContactApiUrl = '../api/v1/payments/contacts/delete.php';
        const sendPaymentApiUrl = '../api/v1/payments/contacts/send.php';
        const listContactsApiUrl = '../api/v1/payments/contacts/list.php?limit=500';
        const globalNotify = (window.finpayNotify && typeof window.finpayNotify === 'function')
            ? window.finpayNotify
            : null;

        const CONTACTS_PAGE_SIZE = 12;
        let visibleLimit = CONTACTS_PAGE_SIZE;
        let availableGbpBalance = 0;
        let paymentSoundEnabled = true;
        let offcanvasMessageTimer = null;

        function loadPaymentSoundPreference() {
            try {
                const raw = localStorage.getItem('finpay_payments_sound_enabled');
                paymentSoundEnabled = raw !== '0';
            } catch (e) {
                paymentSoundEnabled = true;
            }
        }

        function savePaymentSoundPreference() {
            try {
                localStorage.setItem('finpay_payments_sound_enabled', paymentSoundEnabled ? '1' : '0');
            } catch (e) {
            }
        }

        function updateSoundToggleLabel() {
            if (!chatDropdownSoundToggle) {
                return;
            }
            chatDropdownSoundToggle.textContent = paymentSoundEnabled ? 'Disable Sound' : 'Enable Sound';
        }

        function escapeHtml(input) {
            return String(input || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function formatSortCode(value) {
            const digits = String(value || '').replace(/\D/g, '').slice(0, 6);
            if (digits.length <= 2) return digits;
            if (digits.length <= 4) return `${digits.slice(0, 2)}-${digits.slice(2)}`;
            return `${digits.slice(0, 2)}-${digits.slice(2, 4)}-${digits.slice(4)}`;
        }

        function normalizeAccountNumber(value) {
            return String(value || '').replace(/\D/g, '').slice(0, 8);
        }

        function normalizeMoneyInput(value) {
            const clean = String(value || '').replace(/[^0-9.]/g, '');
            const parts = clean.split('.');
            const integerPart = (parts[0] || '0').replace(/^0+(\d)/, '$1');
            const decimalPart = parts.length > 1 ? parts.slice(1).join('').slice(0, 2) : '';
            return decimalPart.length > 0 ? `${integerPart}.${decimalPart}` : integerPart;
        }

        function parseAmount(value) {
            const normalized = normalizeMoneyInput(value);
            const parsed = Number(normalized);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function updateAvailableBalanceDisplay() {
            if (chatAvailableBalance) {
                chatAvailableBalance.textContent = `Available: £${Number(availableGbpBalance || 0).toFixed(2)}`;
            }
        }

        function formatTimestampLabel(timestamp) {
            if (!timestamp) {
                return 'Recently';
            }

            const dt = new Date(timestamp);
            if (Number.isNaN(dt.getTime())) {
                return 'Recently';
            }

            const now = new Date();
            const startNow = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const startDt = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
            const diffDays = Math.round((startNow.getTime() - startDt.getTime()) / 86400000);

            if (diffDays === 0) {
                return dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            }
            if (diffDays === 1) {
                return 'Yesterday';
            }

            return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
        }

        function formatTimestampForChat(timestamp) {
            if (!timestamp) {
                return '';
            }

            const dt = new Date(timestamp);
            if (Number.isNaN(dt.getTime())) {
                return '';
            }

            return dt.toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        function wireInputFormatting() {
            document.querySelectorAll('.sort-code-input').forEach((input) => {
                input.value = formatSortCode(input.value);
                input.addEventListener('input', () => {
                    input.value = formatSortCode(input.value);
                });
            });

            document.querySelectorAll('.account-number-input').forEach((input) => {
                input.value = normalizeAccountNumber(input.value);
                input.addEventListener('input', () => {
                    input.value = normalizeAccountNumber(input.value);
                });
            });
        }

        function setFlashMessage(type, message) {
            if (!paymentsFlashMessage) {
                return;
            }

            const isSuccess = type === 'success';
            paymentsFlashMessage.style.display = '';
            paymentsFlashMessage.style.border = isSuccess ? '1px solid rgba(16, 185, 129, 0.35)' : '1px solid rgba(239, 68, 68, 0.35)';
            paymentsFlashMessage.style.background = isSuccess ? 'rgba(16, 185, 129, 0.10)' : 'rgba(239, 68, 68, 0.10)';
            paymentsFlashMessage.style.color = isSuccess ? '#10b981' : '#ef4444';
            paymentsFlashMessage.textContent = message || '';
        }

        function setOffcanvasMessage(type, message) {
            if (!offcanvasPaymentMessage) {
                return;
            }

            if (offcanvasMessageTimer) {
                clearTimeout(offcanvasMessageTimer);
                offcanvasMessageTimer = null;
            }

            if (!message) {
                offcanvasPaymentMessage.style.opacity = '0';
                offcanvasMessageTimer = setTimeout(() => {
                    offcanvasPaymentMessage.style.display = 'none';
                    offcanvasPaymentMessage.textContent = '';
                }, 190);
                return;
            }

            const isSuccess = type === 'success';
            offcanvasPaymentMessage.style.display = '';
            offcanvasPaymentMessage.style.opacity = '1';
            offcanvasPaymentMessage.style.border = isSuccess ? '1px solid rgba(16, 185, 129, 0.22)' : '1px solid rgba(239, 68, 68, 0.22)';
            offcanvasPaymentMessage.style.background = isSuccess ? 'rgba(16, 185, 129, 0.06)' : 'rgba(239, 68, 68, 0.06)';
            offcanvasPaymentMessage.style.color = isSuccess ? '#0f8f66' : '#c24141';
            offcanvasPaymentMessage.textContent = message;

            offcanvasMessageTimer = setTimeout(() => {
                offcanvasPaymentMessage.style.opacity = '0';
                setTimeout(() => {
                    offcanvasPaymentMessage.style.display = 'none';
                }, 190);
            }, 2600);
        }

        function playPaymentSuccessSound() {
            if (!paymentSoundEnabled) {
                return;
            }

            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) {
                    return;
                }

                const audioCtx = new AudioContextClass();
                const now = audioCtx.currentTime;
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(740, now);
                oscillator.frequency.exponentialRampToValueAtTime(980, now + 0.12);

                gainNode.gain.setValueAtTime(0.0001, now);
                gainNode.gain.exponentialRampToValueAtTime(0.10, now + 0.02);
                gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);

                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.24);
            } catch (e) {
            }
        }

        function playPaymentSuccessVisual() {
            const targets = [chatContactAvatar, chatAvailableBalance, offcanvasPaymentMessage].filter(Boolean);
            targets.forEach((el) => {
                if (!el || typeof el.animate !== 'function') {
                    return;
                }

                el.animate(
                    [
                        { transform: 'scale(1)', filter: 'brightness(1)' },
                        { transform: 'scale(1.04)', filter: 'brightness(1.15)' },
                        { transform: 'scale(1)', filter: 'brightness(1)' },
                    ],
                    {
                        duration: 320,
                        easing: 'ease-out',
                    }
                );
            });
        }

        async function syncContactsFromApi() {
            try {
                const response = await fetch(listContactsApiUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const result = await response.json();
                if (!result || !result.success || !result.data || !Array.isArray(result.data.contacts)) {
                    return;
                }

                const nextMap = {};
                result.data.contacts.forEach((contact) => {
                    const id = Number(contact.id || 0);
                    if (id <= 0) {
                        return;
                    }

                    nextMap[String(id)] = {
                        id,
                        name: contact.name || 'Contact',
                        handle: contact.handle || '@contact',
                        initials: contact.initials || 'C',
                        sortCode: contact.sort_code || '',
                        accountNumber: contact.account_number || '',
                        history: Array.isArray(contact.history) ? contact.history : [],
                    };
                });

                if (Object.keys(nextMap).length === 0) {
                    return;
                }

                CONTACT_DATA = nextMap;
                if (result.data.wallet && typeof result.data.wallet.amount !== 'undefined') {
                    availableGbpBalance = Number(result.data.wallet.amount || 0);
                    updateAvailableBalanceDisplay();
                }

                if (currentContactId && CONTACT_DATA[String(currentContactId)]) {
                    applyContactToOffcanvas(currentContactId);
                }
                updateContactRowsFromData();
            } catch (error) {
            }
        }

        function renderContactHistory(contactId) {
            if (!chatHistoryBox) return;
            const data = CONTACT_DATA[String(contactId)] || null;
            const history = data && Array.isArray(data.history) ? data.history : [];

            if (!data || history.length === 0) {
                chatHistoryBox.innerHTML = `
                    <div class="text-center" style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 2rem;">
                        No transaction history yet for this contact.
                    </div>
                `;
                chatHistoryBox.scrollTop = chatHistoryBox.scrollHeight;
                return;
            }

            const rows = [...history].reverse().map((item) => {
                const direction = item.direction === 'received' ? 'received' : 'sent';
                const bubbleClass = direction === 'received' ? 'received' : 'sent';
                const title = direction === 'received' ? 'Received' : 'Paid';
                const noteText = item.note ? `<div style="color: var(--text-secondary); margin-top: 4px;">${escapeHtml(item.note)}</div>` : '';
                const timeText = formatTimestampForChat(item.timestamp || item.time || '');

                return `
                    <div class="chat-bubble ${bubbleClass}">
                        <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 2px;">${escapeHtml(item.amount || '£0.00')}</div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">${title} • ${escapeHtml(timeText || item.time || '')}</div>
                        ${noteText}
                    </div>
                `;
            }).join('');

            chatHistoryBox.innerHTML = rows;
        }

        function scrollPaymentChatToLatest(smooth = false) {
            if (!chatHistoryBox) {
                return;
            }

            requestAnimationFrame(() => {
                if (smooth && typeof chatHistoryBox.scrollTo === 'function') {
                    chatHistoryBox.scrollTo({
                        top: chatHistoryBox.scrollHeight,
                        behavior: 'smooth',
                    });
                    return;
                }

                chatHistoryBox.scrollTop = chatHistoryBox.scrollHeight;
            });
        }

        function applyContactToOffcanvas(contactId) {
            const data = CONTACT_DATA[String(contactId)] || null;
            if (!data) return;

            currentContactId = Number(contactId) || 0;
            if (chatContactName) chatContactName.textContent = data.name || 'Contact';
            if (chatContactHandle) chatContactHandle.textContent = data.handle || '@contact';
            if (chatContactAvatar) chatContactAvatar.textContent = (data.initials || 'C').toUpperCase();
            renderContactHistory(currentContactId);
            scrollPaymentChatToLatest();
            setOffcanvasMessage('', '');
        }

        function openEditForCurrentContact() {
            const data = CONTACT_DATA[String(currentContactId)] || null;
            if (!data || !editModal) return;

            if (editContactId) editContactId.value = String(data.id || 0);
            if (editRecipientName) editRecipientName.value = data.name || '';
            if (editSortCode) editSortCode.value = formatSortCode(data.sortCode || '');
            if (editAccountNumber) editAccountNumber.value = normalizeAccountNumber(data.accountNumber || '');

            editModal.show();
        }

        function openDeleteForCurrentContact() {
            const data = CONTACT_DATA[String(currentContactId)] || null;
            if (!data || !deleteModal) return;

            if (deleteContactId) deleteContactId.value = String(data.id || 0);
            if (deleteContactNameText) deleteContactNameText.textContent = data.name || 'this contact';

            deleteModal.show();
        }

        function filterAndPaginateContacts() {
            const query = (paymentsSearchInput?.value || '').trim().toLowerCase();
            const searching = query.length > 0;

            let matched = 0;
            let shown = 0;

            contactItems.forEach((item) => {
                const text = (item.getAttribute('data-search-text') || '').toLowerCase();
                const isMatch = !searching || text.includes(query);
                if (!isMatch) {
                    item.style.display = 'none';
                    return;
                }

                matched += 1;
                const canShow = searching ? true : shown < visibleLimit;
                item.style.display = canShow ? '' : 'none';
                if (canShow) shown += 1;
            });

            if (paymentsNoResults) {
                paymentsNoResults.style.display = contactItems.length > 0 && matched === 0 ? '' : 'none';
            }

            if (loadMoreContactsBtn) {
                const hasMore = !searching && matched > shown;
                loadMoreContactsBtn.style.display = hasMore ? '' : 'none';
            }
        }

        function updateContactRowsFromData() {
            contactItems.forEach((row) => {
                const contactId = String(Number(row.getAttribute('data-contact-id') || 0));
                const data = CONTACT_DATA[contactId];
                if (!data || !Array.isArray(data.history) || data.history.length === 0) {
                    return;
                }

                const latest = data.history[0];
                const direction = latest.direction === 'received' ? 'received' : 'sent';
                const amountText = latest.amount || '£0.00';
                const summary = direction === 'received' ? `You received ${amountText}` : `You sent ${amountText}`;
                const timeLabel = formatTimestampLabel(latest.timestamp || latest.time || '');

                const subEl = row.querySelector('.asset-info .asset-sub');
                const rightTimeEl = row.querySelector('.asset-value .asset-sub');
                if (subEl) {
                    subEl.textContent = summary;
                }
                if (rightTimeEl) {
                    rightTimeEl.textContent = timeLabel;
                }

                const name = row.getAttribute('data-contact-name') || '';
                row.setAttribute('data-search-text', `${name} ${summary}`.toLowerCase());
            });
        }

        if (chatModal) {
            chatModal.addEventListener('shown.bs.offcanvas', () => {
                scrollPaymentChatToLatest();
            });
        }

        document.querySelectorAll('.contact-row').forEach((row) => {
            row.addEventListener('click', () => {
                const contactId = Number(row.getAttribute('data-contact-id') || 0);
                applyContactToOffcanvas(contactId);
            });
        });

        if (paymentsSearchInput) {
            paymentsSearchInput.addEventListener('input', () => {
                visibleLimit = CONTACTS_PAGE_SIZE;
                filterAndPaginateContacts();
            });
        }

        if (loadMoreContactsBtn) {
            loadMoreContactsBtn.addEventListener('click', () => {
                visibleLimit += CONTACTS_PAGE_SIZE;
                filterAndPaginateContacts();
            });
        }

        if (chatDropdownEditContact) {
            chatDropdownEditContact.addEventListener('click', (event) => {
                event.preventDefault();
                openEditForCurrentContact();
            });
        }

        if (chatDropdownDeleteContact) {
            chatDropdownDeleteContact.addEventListener('click', (event) => {
                event.preventDefault();
                openDeleteForCurrentContact();
            });
        }

        if (chatDropdownSoundToggle) {
            chatDropdownSoundToggle.addEventListener('click', (event) => {
                event.preventDefault();
                paymentSoundEnabled = !paymentSoundEnabled;
                savePaymentSoundPreference();
                updateSoundToggleLabel();
                setOffcanvasMessage('success', paymentSoundEnabled ? 'Payment sound enabled.' : 'Payment sound disabled.');
            });
        }

        if (chatAmountInput) {
            chatAmountInput.addEventListener('input', () => {
                chatAmountInput.value = normalizeMoneyInput(chatAmountInput.value);
            });
        }

        if (sendPaymentBtn) {
            sendPaymentBtn.addEventListener('click', async (event) => {
                event.preventDefault();

                if (!currentContactId) {
                    setOffcanvasMessage('error', 'Select a contact before sending payment.');
                    if (globalNotify) {
                        globalNotify('Select a contact before sending payment.', { type: 'warning', title: 'Payment Validation' });
                    }
                    return;
                }

                const amount = parseAmount(chatAmountInput ? chatAmountInput.value : '0');
                const note = chatNoteInput ? chatNoteInput.value.trim() : '';

                if (amount <= 0) {
                    setOffcanvasMessage('error', 'Enter a valid amount greater than zero.');
                    if (globalNotify) {
                        globalNotify('Enter a valid amount greater than zero.', { type: 'warning', title: 'Payment Validation' });
                    }
                    return;
                }

                if (amount > Number(availableGbpBalance || 0)) {
                    setOffcanvasMessage('error', 'Insufficient GBP balance for this payment.');
                    if (globalNotify) {
                        globalNotify('Insufficient GBP balance for this payment.', { type: 'error', title: 'Payment Declined' });
                    }
                    return;
                }

                sendPaymentBtn.disabled = true;
                const previousHtml = sendPaymentBtn.innerHTML;
                sendPaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const response = await fetch(sendPaymentApiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            contact_id: currentContactId,
                            amount,
                            note,
                        }),
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        const failMessage = result && result.message ? result.message : 'Failed to send payment.';
                        setOffcanvasMessage('error', failMessage);
                        if (globalNotify) {
                            globalNotify(failMessage, { type: 'error', title: 'Payment Failed' });
                        }
                        window.dispatchEvent(new CustomEvent('finpay:activity', {
                            detail: {
                                kind: 'error',
                                title: 'Payment',
                                important: true,
                            }
                        }));
                        return;
                    }

                    const payment = result.data && result.data.payment ? result.data.payment : null;
                    const balance = result.data && result.data.balance ? result.data.balance : null;

                    if (payment && CONTACT_DATA[String(currentContactId)]) {
                        const contact = CONTACT_DATA[String(currentContactId)];
                        if (!Array.isArray(contact.history)) {
                            contact.history = [];
                        }
                        contact.history.unshift({
                            direction: 'sent',
                            amount: payment.amount_formatted || `£${amount.toFixed(2)}`,
                            note: payment.note || '',
                            timestamp: payment.timestamp || new Date().toISOString(),
                            time: payment.time || '',
                        });
                        if (contact.history.length > 40) {
                            contact.history = contact.history.slice(0, 40);
                        }
                    }

                    if (balance && typeof balance.amount !== 'undefined') {
                        availableGbpBalance = Number(balance.amount || 0);
                        updateAvailableBalanceDisplay();
                    }

                    if (chatAmountInput) {
                        chatAmountInput.value = '';
                    }
                    if (chatNoteInput) {
                        chatNoteInput.value = '';
                    }

                    renderContactHistory(currentContactId);
                    scrollPaymentChatToLatest(true);
                    updateContactRowsFromData();
                    setOffcanvasMessage('success', result.message || 'Payment sent successfully.');
                    playPaymentSuccessVisual();
                    playPaymentSuccessSound();
                    if (globalNotify) {
                        globalNotify(result.message || 'Payment sent successfully.', { type: 'success', title: 'Payment Sent' });
                    }
                    window.dispatchEvent(new CustomEvent('finpay:activity', {
                        detail: {
                            kind: 'success',
                            title: 'Payment',
                            message: 'Contact payment completed successfully.',
                            important: true,
                        }
                    }));
                } catch (error) {
                    setOffcanvasMessage('error', 'Network error while sending payment. Please try again.');
                    if (globalNotify) {
                        globalNotify('Network error while sending payment. Please try again.', { type: 'error', title: 'Payment Failed' });
                    }
                    window.dispatchEvent(new CustomEvent('finpay:activity', {
                        detail: {
                            kind: 'error',
                            title: 'Payment',
                            important: true,
                        }
                    }));
                } finally {
                    sendPaymentBtn.disabled = false;
                    sendPaymentBtn.innerHTML = previousHtml;
                }
            });
        }

        if (newPaymentForm) {
            newPaymentForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const nameInput = newPaymentForm.querySelector('input[name="recipient_name"]');
                const sortCodeInput = newPaymentForm.querySelector('input[name="sort_code"]');
                const accountNumberInput = newPaymentForm.querySelector('input[name="account_number"]');
                const submitButton = newPaymentForm.querySelector('button[type="submit"]');

                if (!nameInput || !sortCodeInput || !accountNumberInput || !submitButton) {
                    setFlashMessage('error', 'Could not prepare contact form submission.');
                    return;
                }

                sortCodeInput.value = formatSortCode(sortCodeInput.value);
                accountNumberInput.value = normalizeAccountNumber(accountNumberInput.value);

                if (!newPaymentForm.checkValidity()) {
                    newPaymentForm.reportValidity();
                    return;
                }

                submitButton.disabled = true;
                const previousLabel = submitButton.textContent;
                submitButton.textContent = 'Adding...';

                try {
                    const response = await fetch(addContactApiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            recipient_name: nameInput.value.trim(),
                            sort_code: sortCodeInput.value,
                            account_number: accountNumberInput.value,
                        }),
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        const failMessage = result && result.message ? result.message : 'Failed to add contact.';
                        setFlashMessage('error', failMessage);
                        if (globalNotify) {
                            globalNotify(failMessage, { type: 'error', title: 'Contact Save Failed' });
                        }
                        return;
                    }

                    sessionStorage.setItem('paymentsFlashMessage', JSON.stringify({
                        type: 'success',
                        message: result.message || 'Contact added successfully.',
                    }));
                    window.location.reload();
                } catch (error) {
                    setFlashMessage('error', 'Network error while adding contact. Please try again.');
                    if (globalNotify) {
                        globalNotify('Network error while adding contact. Please try again.', { type: 'error', title: 'Contact Save Failed' });
                    }
                } finally {
                    submitButton.disabled = false;
                    submitButton.textContent = previousLabel;
                }
            });
        }

        if (editContactForm) {
            editContactForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const idInput = editContactForm.querySelector('input[name="contact_id"]');
                const nameInput = editContactForm.querySelector('input[name="recipient_name"]');
                const sortCodeInput = editContactForm.querySelector('input[name="sort_code"]');
                const accountNumberInput = editContactForm.querySelector('input[name="account_number"]');
                const submitButton = editContactForm.querySelector('button[type="submit"]');

                if (!idInput || !nameInput || !sortCodeInput || !accountNumberInput || !submitButton) {
                    setFlashMessage('error', 'Could not prepare contact update submission.');
                    return;
                }

                sortCodeInput.value = formatSortCode(sortCodeInput.value);
                accountNumberInput.value = normalizeAccountNumber(accountNumberInput.value);

                if (!editContactForm.checkValidity()) {
                    editContactForm.reportValidity();
                    return;
                }

                submitButton.disabled = true;
                const previousLabel = submitButton.textContent;
                submitButton.textContent = 'Saving...';

                try {
                    const response = await fetch(updateContactApiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            contact_id: Number(idInput.value || 0),
                            recipient_name: nameInput.value.trim(),
                            sort_code: sortCodeInput.value,
                            account_number: accountNumberInput.value,
                        }),
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        const failMessage = result && result.message ? result.message : 'Failed to update contact.';
                        setFlashMessage('error', failMessage);
                        return;
                    }

                    sessionStorage.setItem('paymentsFlashMessage', JSON.stringify({
                        type: 'success',
                        message: result.message || 'Contact updated successfully.',
                    }));
                    window.location.reload();
                } catch (error) {
                    setFlashMessage('error', 'Network error while updating contact. Please try again.');
                } finally {
                    submitButton.disabled = false;
                    submitButton.textContent = previousLabel;
                }
            });
        }

        if (deleteContactForm) {
            deleteContactForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const idInput = deleteContactForm.querySelector('input[name="contact_id"]');
                const submitButton = deleteContactForm.querySelector('button[type="submit"]');
                if (!idInput || !submitButton) {
                    setFlashMessage('error', 'Could not prepare contact delete submission.');
                    return;
                }

                submitButton.disabled = true;
                const previousLabel = submitButton.textContent;
                submitButton.textContent = 'Deleting...';

                try {
                    const response = await fetch(deleteContactApiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            contact_id: Number(idInput.value || 0),
                        }),
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        const failMessage = result && result.message ? result.message : 'Failed to delete contact.';
                        setFlashMessage('error', failMessage);
                        return;
                    }

                    sessionStorage.setItem('paymentsFlashMessage', JSON.stringify({
                        type: 'success',
                        message: result.message || 'Contact removed successfully.',
                    }));
                    window.location.reload();
                } catch (error) {
                    setFlashMessage('error', 'Network error while deleting contact. Please try again.');
                } finally {
                    submitButton.disabled = false;
                    submitButton.textContent = previousLabel;
                }
            });
        }

        const savedFlash = sessionStorage.getItem('paymentsFlashMessage');
        if (savedFlash) {
            try {
                const parsed = JSON.parse(savedFlash);
                if (parsed && parsed.message) {
                    setFlashMessage(parsed.type === 'success' ? 'success' : 'error', parsed.message);
                }
            } catch (e) {
            }
            sessionStorage.removeItem('paymentsFlashMessage');
        }

        loadPaymentSoundPreference();
        updateSoundToggleLabel();
        wireInputFormatting();
        updateAvailableBalanceDisplay();
        updateContactRowsFromData();
        filterAndPaginateContacts();

        const firstVisibleContact = contactItems.find((item) => item.style.display !== 'none');
        if (firstVisibleContact) {
            const contactId = Number(firstVisibleContact.getAttribute('data-contact-id') || 0);
            applyContactToOffcanvas(contactId);
        } else {
            renderContactHistory(0);
        }

        syncContactsFromApi();
    </script>
</body>
</html>

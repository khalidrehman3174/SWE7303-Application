<?php

require_once __DIR__ . '/../../../includes/available_balance.php';

const PAYMENT_CONTACTS_MAX_RECIPIENT_NAME_LENGTH = 80;

function payment_contacts_ensure_schema(mysqli $dbc): void
{
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
}

function payment_contacts_ensure_transactions_schema(mysqli $dbc): void
{
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
}

function payment_contacts_ensure_withdrawals_schema(mysqli $dbc): void
{
    mysqli_query($dbc, "CREATE TABLE IF NOT EXISTS withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(20,8) NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'GBP',
        status VARCHAR(30) NOT NULL DEFAULT 'completed',
        method VARCHAR(50) DEFAULT 'contact_payment',
        reference VARCHAR(80) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_withdrawals_user_created (user_id, created_at),
        INDEX idx_withdrawals_status_currency (status, currency)
    )");
}

function payment_contacts_get_wallet_balance(mysqli $dbc, int $userId, string $symbol = 'GBP'): float
{
    $stmt = mysqli_prepare($dbc, 'SELECT balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1');
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (float)($row['balance'] ?? 0.0);
}

function payment_contacts_normalize_sort_code(string $raw): string
{
    return substr(preg_replace('/[^0-9]/', '', $raw), 0, 6);
}

function payment_contacts_normalize_account_number(string $raw): string
{
    return substr(preg_replace('/[^0-9]/', '', $raw), 0, 8);
}

function payment_contacts_create(mysqli $dbc, int $userId, string $recipientName, string $sortCodeRaw, string $accountNumberRaw): array
{
    payment_contacts_ensure_schema($dbc);

    $name = trim($recipientName);
    $sortCode = payment_contacts_normalize_sort_code($sortCodeRaw);
    $accountNumber = payment_contacts_normalize_account_number($accountNumberRaw);

    if ($name === '' || mb_strlen($name) > PAYMENT_CONTACTS_MAX_RECIPIENT_NAME_LENGTH) {
        return [
            'ok' => false,
            'code' => 'invalid_recipient_name',
            'message' => 'Recipient name must be between 1 and ' . PAYMENT_CONTACTS_MAX_RECIPIENT_NAME_LENGTH . ' characters.',
        ];
    }

    if (strlen($sortCode) !== 6) {
        return [
            'ok' => false,
            'code' => 'invalid_sort_code',
            'message' => 'Sort code must be 6 digits.',
        ];
    }

    if (strlen($accountNumber) !== 8) {
        return [
            'ok' => false,
            'code' => 'invalid_account_number',
            'message' => 'Account number must be 8 digits.',
        ];
    }

    $duplicateStmt = mysqli_prepare(
        $dbc,
        'SELECT id FROM payment_contacts WHERE user_id = ? AND sort_code = ? AND account_number = ? LIMIT 1'
    );

    if (!$duplicateStmt) {
        return [
            'ok' => false,
            'code' => 'duplicate_check_failed',
            'message' => 'Could not validate contact uniqueness.',
        ];
    }

    mysqli_stmt_bind_param($duplicateStmt, 'iss', $userId, $sortCode, $accountNumber);
    mysqli_stmt_execute($duplicateStmt);
    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
    $isDuplicate = $duplicateResult && mysqli_num_rows($duplicateResult) > 0;
    mysqli_stmt_close($duplicateStmt);

    if ($isDuplicate) {
        return [
            'ok' => false,
            'code' => 'contact_exists',
            'message' => 'This bank account is already saved in your contacts.',
        ];
    }

    $insertStmt = mysqli_prepare(
        $dbc,
        'INSERT INTO payment_contacts (user_id, recipient_name, sort_code, account_number) VALUES (?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        return [
            'ok' => false,
            'code' => 'insert_prepare_failed',
            'message' => 'Could not prepare contact save query.',
        ];
    }

    mysqli_stmt_bind_param($insertStmt, 'isss', $userId, $name, $sortCode, $accountNumber);
    $ok = mysqli_stmt_execute($insertStmt);
    $insertId = (int)mysqli_insert_id($dbc);
    mysqli_stmt_close($insertStmt);

    if (!$ok) {
        return [
            'ok' => false,
            'code' => 'insert_failed',
            'message' => 'Could not save contact. Please try again.',
        ];
    }

    return [
        'ok' => true,
        'code' => 'contact_created',
        'message' => 'Contact added successfully.',
        'contact' => [
            'id' => $insertId,
            'recipient_name' => $name,
            'sort_code' => substr($sortCode, 0, 2) . '-' . substr($sortCode, 2, 2) . '-' . substr($sortCode, 4, 2),
            'account_number_masked' => '****' . substr($accountNumber, -4),
            'created_at' => date('c'),
        ],
    ];
}

function payment_contacts_update(mysqli $dbc, int $userId, int $contactId, string $recipientName, string $sortCodeRaw, string $accountNumberRaw): array
{
    payment_contacts_ensure_schema($dbc);

    if ($contactId <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_contact_id',
            'message' => 'Invalid contact selected for update.',
        ];
    }

    $name = trim($recipientName);
    $sortCode = payment_contacts_normalize_sort_code($sortCodeRaw);
    $accountNumber = payment_contacts_normalize_account_number($accountNumberRaw);

    if ($name === '' || mb_strlen($name) > PAYMENT_CONTACTS_MAX_RECIPIENT_NAME_LENGTH) {
        return [
            'ok' => false,
            'code' => 'invalid_recipient_name',
            'message' => 'Recipient name must be between 1 and ' . PAYMENT_CONTACTS_MAX_RECIPIENT_NAME_LENGTH . ' characters.',
        ];
    }

    if (strlen($sortCode) !== 6) {
        return [
            'ok' => false,
            'code' => 'invalid_sort_code',
            'message' => 'Sort code must be 6 digits.',
        ];
    }

    if (strlen($accountNumber) !== 8) {
        return [
            'ok' => false,
            'code' => 'invalid_account_number',
            'message' => 'Account number must be 8 digits.',
        ];
    }

    $duplicateStmt = mysqli_prepare(
        $dbc,
        'SELECT id FROM payment_contacts WHERE user_id = ? AND sort_code = ? AND account_number = ? AND id <> ? LIMIT 1'
    );

    if (!$duplicateStmt) {
        return [
            'ok' => false,
            'code' => 'duplicate_check_failed',
            'message' => 'Could not validate contact uniqueness.',
        ];
    }

    mysqli_stmt_bind_param($duplicateStmt, 'issi', $userId, $sortCode, $accountNumber, $contactId);
    mysqli_stmt_execute($duplicateStmt);
    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
    $isDuplicate = $duplicateResult && mysqli_num_rows($duplicateResult) > 0;
    mysqli_stmt_close($duplicateStmt);

    if ($isDuplicate) {
        return [
            'ok' => false,
            'code' => 'contact_exists',
            'message' => 'Another contact already uses this bank account.',
        ];
    }

    $updateStmt = mysqli_prepare(
        $dbc,
        'UPDATE payment_contacts SET recipient_name = ?, sort_code = ?, account_number = ? WHERE id = ? AND user_id = ? LIMIT 1'
    );

    if (!$updateStmt) {
        return [
            'ok' => false,
            'code' => 'update_prepare_failed',
            'message' => 'Could not prepare contact update query.',
        ];
    }

    mysqli_stmt_bind_param($updateStmt, 'sssii', $name, $sortCode, $accountNumber, $contactId, $userId);
    mysqli_stmt_execute($updateStmt);
    $affected = mysqli_stmt_affected_rows($updateStmt);
    mysqli_stmt_close($updateStmt);

    if ($affected < 0) {
        return [
            'ok' => false,
            'code' => 'update_failed',
            'message' => 'Could not update contact. Please try again.',
        ];
    }

    return [
        'ok' => true,
        'code' => 'contact_updated',
        'message' => 'Contact updated successfully.',
        'contact' => [
            'id' => $contactId,
            'recipient_name' => $name,
            'sort_code' => substr($sortCode, 0, 2) . '-' . substr($sortCode, 2, 2) . '-' . substr($sortCode, 4, 2),
            'account_number_masked' => '****' . substr($accountNumber, -4),
            'updated_at' => date('c'),
        ],
    ];
}

function payment_contacts_delete(mysqli $dbc, int $userId, int $contactId): array
{
    payment_contacts_ensure_schema($dbc);

    if ($contactId <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_contact_id',
            'message' => 'Invalid contact selected for deletion.',
        ];
    }

    $deleteStmt = mysqli_prepare($dbc, 'DELETE FROM payment_contacts WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$deleteStmt) {
        return [
            'ok' => false,
            'code' => 'delete_prepare_failed',
            'message' => 'Could not prepare contact delete query.',
        ];
    }

    mysqli_stmt_bind_param($deleteStmt, 'ii', $contactId, $userId);
    mysqli_stmt_execute($deleteStmt);
    $affected = mysqli_stmt_affected_rows($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($affected <= 0) {
        return [
            'ok' => false,
            'code' => 'contact_not_found',
            'message' => 'Contact not found or already removed.',
        ];
    }

    return [
        'ok' => true,
        'code' => 'contact_deleted',
        'message' => 'Contact removed successfully.',
    ];
}

function payment_contacts_send_payment(mysqli $dbc, int $userId, int $contactId, $amountRaw, string $note): array
{
    payment_contacts_ensure_schema($dbc);
    payment_contacts_ensure_transactions_schema($dbc);
    payment_contacts_ensure_withdrawals_schema($dbc);

    if ($contactId <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_contact_id',
            'message' => 'Select a valid contact to send payment.',
        ];
    }

    $amount = round((float)$amountRaw, 2);
    if ($amount <= 0) {
        return [
            'ok' => false,
            'code' => 'invalid_amount',
            'message' => 'Enter a valid amount greater than zero.',
        ];
    }

    if ($amount > 10000000) {
        return [
            'ok' => false,
            'code' => 'amount_too_large',
            'message' => 'Amount exceeds allowed transfer limit.',
        ];
    }

    $availableBalancePayload = finpay_get_available_balance_gbp($dbc, $userId);
    $availableAmount = (float)($availableBalancePayload['amount'] ?? 0.0);
    $availableSource = (string)($availableBalancePayload['source'] ?? 'none');

    if ($availableAmount < $amount) {
        return [
            'ok' => false,
            'code' => 'insufficient_balance',
            'message' => 'Insufficient GBP balance for this payment.',
        ];
    }

    $cleanNote = trim(mb_substr($note, 0, 255));

    $contactStmt = mysqli_prepare($dbc, 'SELECT id, recipient_name FROM payment_contacts WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$contactStmt) {
        return [
            'ok' => false,
            'code' => 'contact_lookup_failed',
            'message' => 'Could not verify recipient contact.',
        ];
    }
    mysqli_stmt_bind_param($contactStmt, 'ii', $contactId, $userId);
    mysqli_stmt_execute($contactStmt);
    $contactResult = mysqli_stmt_get_result($contactStmt);
    $contactRow = $contactResult ? mysqli_fetch_assoc($contactResult) : null;
    mysqli_stmt_close($contactStmt);

    if (!$contactRow) {
        return [
            'ok' => false,
            'code' => 'contact_not_found',
            'message' => 'Selected contact was not found.',
        ];
    }

    mysqli_begin_transaction($dbc);

    try {
        if (strpos($availableSource, 'wallet') === 0) {
            $walletStmt = mysqli_prepare($dbc, 'SELECT id, balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1 FOR UPDATE');
            if (!$walletStmt) {
                throw new RuntimeException('wallet_lookup_prepare_failed');
            }

            $symbol = 'GBP';
            mysqli_stmt_bind_param($walletStmt, 'is', $userId, $symbol);
            mysqli_stmt_execute($walletStmt);
            $walletResult = mysqli_stmt_get_result($walletStmt);
            $walletRow = $walletResult ? mysqli_fetch_assoc($walletResult) : null;
            mysqli_stmt_close($walletStmt);

            if (!$walletRow || (float)$walletRow['balance'] < $amount) {
                mysqli_rollback($dbc);
                return [
                    'ok' => false,
                    'code' => 'insufficient_balance',
                    'message' => 'Insufficient GBP balance for this payment.',
                ];
            }

            $walletId = (int)$walletRow['id'];
            $debitStmt = mysqli_prepare($dbc, 'UPDATE wallets SET balance = balance - ? WHERE id = ? LIMIT 1');
            if (!$debitStmt) {
                throw new RuntimeException('wallet_debit_prepare_failed');
            }

            mysqli_stmt_bind_param($debitStmt, 'di', $amount, $walletId);
            mysqli_stmt_execute($debitStmt);
            mysqli_stmt_close($debitStmt);
        }

        $txStmt = mysqli_prepare($dbc, 'INSERT INTO payment_contact_transactions (user_id, contact_id, direction, amount, note) VALUES (?, ?, ?, ?, ?)');
        if (!$txStmt) {
            throw new RuntimeException('transaction_insert_prepare_failed');
        }
        $direction = 'sent';
        mysqli_stmt_bind_param($txStmt, 'iisds', $userId, $contactId, $direction, $amount, $cleanNote);
        mysqli_stmt_execute($txStmt);
        $transactionId = (int)mysqli_insert_id($dbc);
        mysqli_stmt_close($txStmt);
        $txTimestampIso = date('c');

        $ref = 'CP-' . $userId . '-' . $contactId . '-' . $transactionId;
        $completedAt = date('Y-m-d H:i:s');
        $withdrawStmt = mysqli_prepare(
            $dbc,
            'INSERT INTO withdrawals (user_id, amount, currency, status, method, reference, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$withdrawStmt) {
            throw new RuntimeException('withdrawal_insert_prepare_failed');
        }

        $currency = 'GBP';
        $status = 'completed';
        $method = 'contact_payment';
        mysqli_stmt_bind_param($withdrawStmt, 'idsssss', $userId, $amount, $currency, $status, $method, $ref, $completedAt);
        mysqli_stmt_execute($withdrawStmt);
        mysqli_stmt_close($withdrawStmt);

        mysqli_commit($dbc);

        $availableBalance = finpay_get_available_balance_gbp($dbc, $userId);
        $newBalance = (float)($availableBalance['amount'] ?? 0.0);

        return [
            'ok' => true,
            'code' => 'payment_sent',
            'message' => 'Payment sent successfully.',
            'payment' => [
                'contact_id' => $contactId,
                'contact_name' => (string)($contactRow['recipient_name'] ?? 'Contact'),
                'amount' => number_format($amount, 2, '.', ''),
                'amount_formatted' => '£' . number_format($amount, 2),
                'direction' => 'sent',
                'note' => $cleanNote,
                'timestamp' => $txTimestampIso,
                'time' => date('d M, H:i'),
            ],
            'balance' => [
                'symbol' => 'GBP',
                'amount' => $newBalance,
                'formatted' => number_format($newBalance, 2, '.', ''),
            ],
        ];
    } catch (Throwable $e) {
        mysqli_rollback($dbc);
        return [
            'ok' => false,
            'code' => 'payment_failed',
            'message' => 'Could not process payment. Please try again.',
        ];
    }
}

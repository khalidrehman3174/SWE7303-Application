<?php

function deposit_repo_ensure_schema(mysqli $dbc): void
{
    $sql = "CREATE TABLE IF NOT EXISTS deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        deposit_id VARCHAR(64) NOT NULL UNIQUE,
        public_id VARCHAR(64) DEFAULT NULL,
        user_id INT NOT NULL,
        method ENUM('bank', 'card', 'apple') NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'GBP',
        amount DECIMAL(15,2) NOT NULL,
        fee_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(15,2) NOT NULL,
        status VARCHAR(32) NOT NULL,
        reference_code VARCHAR(64) DEFAULT NULL,
        provider VARCHAR(32) NOT NULL DEFAULT 'sandbox',
        external_reference VARCHAR(255) DEFAULT NULL,
        idempotency_key VARCHAR(64) NOT NULL,
        metadata_json TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_status (status),
        INDEX idx_external_reference (external_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    mysqli_query($dbc, $sql);

    // Backfill columns for older installs where deposits table already existed.
    $requiredColumns = [
        'deposit_id' => "ALTER TABLE deposits ADD COLUMN deposit_id VARCHAR(64) NULL AFTER id",
        'public_id' => "ALTER TABLE deposits ADD COLUMN public_id VARCHAR(64) NULL AFTER deposit_id",
        'provider' => "ALTER TABLE deposits ADD COLUMN provider VARCHAR(32) NOT NULL DEFAULT 'sandbox' AFTER status",
        'reference_code' => "ALTER TABLE deposits ADD COLUMN reference_code VARCHAR(64) NULL AFTER status",
        'external_reference' => "ALTER TABLE deposits ADD COLUMN external_reference VARCHAR(255) DEFAULT NULL AFTER provider",
        'idempotency_key' => "ALTER TABLE deposits ADD COLUMN idempotency_key VARCHAR(64) NOT NULL DEFAULT '' AFTER external_reference",
        'metadata_json' => "ALTER TABLE deposits ADD COLUMN metadata_json TEXT NULL AFTER idempotency_key",
        'fee_amount' => "ALTER TABLE deposits ADD COLUMN fee_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER amount",
        'net_amount' => "ALTER TABLE deposits ADD COLUMN net_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER fee_amount",
        'updated_at' => "ALTER TABLE deposits ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        'completed_at' => "ALTER TABLE deposits ADD COLUMN completed_at TIMESTAMP NULL AFTER updated_at",
    ];

    foreach ($requiredColumns as $column => $alterSql) {
        if (!deposit_repo_column_exists($dbc, 'deposits', $column)) {
            mysqli_query($dbc, $alterSql);
        }
    }

    if (!deposit_repo_index_exists($dbc, 'deposits', 'idx_external_reference')) {
        mysqli_query($dbc, 'ALTER TABLE deposits ADD INDEX idx_external_reference (external_reference)');
    }

    // Normalize legacy rows/columns so new API queries can always use deposit_id/net_amount.
    if (deposit_repo_column_exists($dbc, 'deposits', 'deposit_id')) {
        mysqli_query($dbc, "UPDATE deposits SET deposit_id = public_id WHERE (deposit_id IS NULL OR deposit_id = '') AND public_id IS NOT NULL AND public_id <> ''");
        mysqli_query($dbc, "UPDATE deposits SET deposit_id = CONCAT('dep_legacy_', id) WHERE deposit_id IS NULL OR deposit_id = ''");
    }

    if (deposit_repo_column_exists($dbc, 'deposits', 'public_id')) {
        mysqli_query($dbc, "UPDATE deposits SET public_id = deposit_id WHERE (public_id IS NULL OR public_id = '') AND deposit_id IS NOT NULL AND deposit_id <> ''");
    }

    if (deposit_repo_column_exists($dbc, 'deposits', 'reference_code')) {
        mysqli_query($dbc, "UPDATE deposits SET reference_code = deposit_id WHERE (reference_code IS NULL OR reference_code = '') AND deposit_id IS NOT NULL AND deposit_id <> ''");
    }

    if (deposit_repo_column_exists($dbc, 'deposits', 'net_amount')) {
        mysqli_query($dbc, 'UPDATE deposits SET net_amount = amount WHERE net_amount = 0');
    }

    $webhookSql = "CREATE TABLE IF NOT EXISTS stripe_webhook_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) NOT NULL UNIQUE,
        event_type VARCHAR(100) NOT NULL,
        external_reference VARCHAR(255) DEFAULT NULL,
        payload_json LONGTEXT NULL,
        processed TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        INDEX idx_external_reference (external_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    mysqli_query($dbc, $webhookSql);
}

function deposit_repo_column_exists(mysqli $dbc, string $tableName, string $columnName): bool
{
    $safeTable = mysqli_real_escape_string($dbc, $tableName);
    $safeColumn = mysqli_real_escape_string($dbc, $columnName);
    $query = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
    $result = mysqli_query($dbc, $query);
    if (!$result) {
        return false;
    }

    return mysqli_num_rows($result) > 0;
}

function deposit_repo_index_exists(mysqli $dbc, string $tableName, string $indexName): bool
{
    $safeTable = mysqli_real_escape_string($dbc, $tableName);
    $safeIndex = mysqli_real_escape_string($dbc, $indexName);
    $query = "SHOW INDEX FROM `$safeTable` WHERE Key_name = '$safeIndex'";
    $result = mysqli_query($dbc, $query);
    if (!$result) {
        return false;
    }

    return mysqli_num_rows($result) > 0;
}

function deposit_repo_find_webhook_event(mysqli $dbc, string $eventId): ?array
{
    $sql = "SELECT * FROM stripe_webhook_events WHERE event_id = ? LIMIT 1";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $eventId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function deposit_repo_create_webhook_event(mysqli $dbc, string $eventId, string $eventType, ?string $externalReference, string $payloadJson): bool
{
    $sql = "INSERT INTO stripe_webhook_events (event_id, event_type, external_reference, payload_json, processed) VALUES (?, ?, ?, ?, 0)";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ssss', $eventId, $eventType, $externalReference, $payloadJson);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_mark_webhook_processed(mysqli $dbc, string $eventId): bool
{
    $sql = "UPDATE stripe_webhook_events SET processed = 1, processed_at = NOW() WHERE event_id = ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $eventId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_create(mysqli $dbc, array $row): bool
{
    $sql = "INSERT INTO deposits (
        deposit_id, public_id, user_id, method, currency, amount, fee_amount, net_amount, status,
        reference_code, provider, external_reference, idempotency_key, metadata_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    $metadataJson = isset($row['metadata']) ? json_encode($row['metadata']) : null;
    $publicId = $row['deposit_id'];
    $referenceCode = $row['deposit_id'];
    mysqli_stmt_bind_param(
        $stmt,
        'ssissdddssssss',
        $row['deposit_id'],
        $publicId,
        $row['user_id'],
        $row['method'],
        $row['currency'],
        $row['amount'],
        $row['fee_amount'],
        $row['net_amount'],
        $row['status'],
        $referenceCode,
        $row['provider'],
        $row['external_reference'],
        $row['idempotency_key'],
        $metadataJson
    );

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_find_by_deposit_id(mysqli $dbc, string $depositId): ?array
{
    $sql = "SELECT * FROM deposits WHERE deposit_id = ? LIMIT 1";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $depositId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function deposit_repo_find_by_external_reference(mysqli $dbc, string $externalReference): ?array
{
    $sql = "SELECT * FROM deposits WHERE external_reference = ? LIMIT 1";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $externalReference);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function deposit_repo_find_by_user_and_key(mysqli $dbc, int $userId, string $idempotencyKey): ?array
{
    $sql = "SELECT * FROM deposits WHERE user_id = ? AND idempotency_key = ? ORDER BY id DESC LIMIT 1";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $idempotencyKey);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function deposit_repo_update_status(mysqli $dbc, string $depositId, string $status, ?string $completedAt = null): bool
{
    if ($completedAt !== null) {
        $sql = "UPDATE deposits SET status = ?, completed_at = ? WHERE deposit_id = ?";
        $stmt = mysqli_prepare($dbc, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'sss', $status, $completedAt, $depositId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    $sql = "UPDATE deposits SET status = ? WHERE deposit_id = ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $status, $depositId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_find_wallet(mysqli $dbc, int $userId, string $symbol): ?array
{
    $sql = "SELECT id, balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function deposit_repo_create_wallet(mysqli $dbc, int $userId, string $symbol): bool
{
    $sql = "INSERT INTO wallets (user_id, symbol, balance) VALUES (?, ?, 0)";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_increment_wallet(mysqli $dbc, int $walletId, float $amount): bool
{
    $sql = "UPDATE wallets SET balance = balance + ? WHERE id = ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'di', $amount, $walletId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function deposit_repo_set_external_reference(mysqli $dbc, string $depositId, string $externalReference, string $provider): bool
{
    $sql = "UPDATE deposits SET external_reference = ?, provider = ? WHERE deposit_id = ?";
    $stmt = mysqli_prepare($dbc, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sss', $externalReference, $provider, $depositId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

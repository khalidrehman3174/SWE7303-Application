<?php
// includes/process_rewards.php
// Included by init.php to lazily process staking rewards on page load.

function process_rewards_table_exists(mysqli $dbc, string $tableName): bool
{
    try {
        $safe = mysqli_real_escape_string($dbc, $tableName);
        $res = mysqli_query($dbc, "SHOW TABLES LIKE '$safe'");
        return ($res instanceof mysqli_result) && mysqli_num_rows($res) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function process_rewards_column_exists(mysqli $dbc, string $tableName, string $columnName): bool
{
    try {
        $safeTable = mysqli_real_escape_string($dbc, $tableName);
        $safeColumn = mysqli_real_escape_string($dbc, $columnName);
        $res = mysqli_query($dbc, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
        return ($res instanceof mysqli_result) && mysqli_num_rows($res) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

if (isset($user_id) && $user_id) {
    $requiredTables = ['user_stakes', 'staking_plans', 'wallets', 'transactions'];
    foreach ($requiredTables as $tableName) {
        if (!process_rewards_table_exists($dbc, $tableName)) {
            return;
        }
    }

    // Check for due payouts. Some databases may have partial staking_plans schema,
    // so we safely map optional columns and provide defaults when absent.
    $apyExpr = process_rewards_column_exists($dbc, 'staking_plans', 'apy')
        ? 'p.apy'
        : '0';
    $assetExpr = process_rewards_column_exists($dbc, 'staking_plans', 'asset_symbol')
        ? 'p.asset_symbol'
        : "'BTC'";
    $planNameExpr = process_rewards_column_exists($dbc, 'staking_plans', 'name')
        ? 'p.name'
        : "'Staking Plan'";

    $safe_uid = (int)$user_id;
    $q_due = "SELECT s.*, {$apyExpr} AS apy, {$assetExpr} AS asset_symbol, {$planNameExpr} AS plan_name
              FROM user_stakes s
              JOIN staking_plans p ON s.plan_id = p.id
              WHERE s.user_id = {$safe_uid}
              AND s.status = 'active'
              AND s.next_payout <= NOW()";
              
    $r_due = mysqli_query($dbc, $q_due);
    
    if ($r_due && mysqli_num_rows($r_due) > 0) {
        while ($stake = mysqli_fetch_assoc($r_due)) {
            $stake_id = $stake['id'];
            $asset = $stake['asset_symbol'];
            $amount = (float)$stake['amount'];
            $apy = (float)$stake['apy'];
            
            // Calculate Daily Interest
            // Formula: Amount * (APY / 100) / 365
            // If strictly daily compounding, slightly different, but this is standard standard simple daily yield.
            $daily_profit = $amount * ($apy / 100) / 365;
            
            mysqli_begin_transaction($dbc);
            try {
                // 1. Credit Profit to Wallet
                // Check if wallet exists
                $chk_wal = mysqli_query($dbc, "SELECT id FROM wallets WHERE user_id = $user_id AND symbol = '$asset'");
                if (mysqli_num_rows($chk_wal) > 0) {
                    mysqli_query($dbc, "UPDATE wallets SET balance = balance + $daily_profit WHERE user_id = $user_id AND symbol = '$asset'");
                } else {
                    mysqli_query($dbc, "INSERT INTO wallets (user_id, symbol, balance) VALUES ($user_id, '$asset', $daily_profit)");
                }
                
                // 2. Log Profit Transaction
                $desc = "Staking Reward (" . $stake['plan_name'] . ")";
                mysqli_query($dbc, "INSERT INTO transactions (user_id, type, symbol, amount, status, description) 
                                    VALUES ($user_id, 'stake', '$asset', $daily_profit, 'completed', '$desc')");
                                    
                // 3. Update Next Payout (+1 Day)
                $update_q = "UPDATE user_stakes SET next_payout = DATE_ADD(next_payout, INTERVAL 1 DAY) WHERE id = $stake_id";
                
                // 4. Check Maturity (End Date)
                // If expire date exists AND next_payout (now incremented? No, check if NOW > end_date)
                // Actually if today is the last day.
                if ($stake['end_date'] && strtotime($stake['end_date']) <= time()) {
                     // Plan Completed. Return Principal.
                     // Credit Principal
                     mysqli_query($dbc, "UPDATE wallets SET balance = balance + $amount WHERE user_id = $user_id AND symbol = '$asset'");
                     
                     // Log Principal Return
                     $desc_pr = "Staking Principal Return (" . $stake['plan_name'] . ")";
                     mysqli_query($dbc, "INSERT INTO transactions (user_id, type, symbol, amount, status, description) 
                                         VALUES ($user_id, 'unstake', '$asset', $amount, 'completed', '$desc_pr')");
                                         
                     // Mark Completed
                     $update_q = "UPDATE user_stakes SET status = 'completed', next_payout = NULL WHERE id = $stake_id";
                }
                
                mysqli_query($dbc, $update_q);
                
                mysqli_commit($dbc);
            } catch (Exception $e) {
                mysqli_rollback($dbc);
                // Log error silently or continue
            }
        }
    }
}
?>

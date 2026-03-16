<?php
// init.php
// Include this at the top of every page

session_start();
require_once 'db_connect.php';

// Global Settings
$app_name = 'FinPay';
$page_title = $app_name; // Default title

// Initialize user variables from session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// User Data Array (Global)
$user = null;
$user_assets = []; // Global assets array

if ($user_id) {
    // 1. Fetch User Details
    $query_user = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1"; // minimal sanitization for purely numeric session id
    // Ideally use prepared statements, but for this step strict int casting is okay
    $safe_uid = (int)$user_id;
    $result_user = mysqli_query($dbc, "SELECT * FROM users WHERE id = $safe_uid");

    if ($result_user && mysqli_num_rows($result_user) > 0) {
        $user_row = mysqli_fetch_assoc($result_user);
        
        // 2. Fetch User Wallets

        // Self-Healing: Ensure default wallets exist (e.g. USDC added later)
        $defaults_check = ['USDC']; 
        foreach ($defaults_check as $dsym) {
             $chk = mysqli_query($dbc, "SELECT id FROM wallets WHERE user_id = $safe_uid AND symbol = '$dsym'");
             if (mysqli_num_rows($chk) == 0) {
                 mysqli_query($dbc, "INSERT INTO wallets (user_id, symbol, balance) VALUES ($safe_uid, '$dsym', 0.0000)");
             }
        }

        $query_wallets = "SELECT * FROM wallets WHERE user_id = $safe_uid";
        $result_wallets = mysqli_query($dbc, $query_wallets);
        
        $total_balance_usdt = 0;
        
        while ($row = mysqli_fetch_assoc($result_wallets)) {
            $coin_names = [
                'BTC' => 'Bitcoin',
                'ETH' => 'Ethereum', 
                'USDT' => 'Tether USD',
                'USDC' => 'USD Coin',
                'BNB' => 'BNB',
                'SOL' => 'Solana',
                'XRP' => 'XRP',
                'ADA' => 'Cardano',
                'DOGE' => 'Dogecoin',
                'TRX' => 'TRON',
                'LTC' => 'Litecoin',
                // Metals
                'XAU' => 'Gold',
                'XAG' => 'Silver',
                'XPT' => 'Platinum'
            ];

            $asset_data = [
                'symbol' => $row['symbol'],
                'name'   => isset($coin_names[$row['symbol']]) ? $coin_names[$row['symbol']] : $row['symbol'],
                'amount' => (float)$row['balance'],
                'value'  => 0, // JS will calculate this using live API, or PHP can calc if price known
                'color'  => '#6c757d'
            ];
            
            // Segregate Metals vs Crypto
            if (in_array($row['symbol'], ['XAU', 'XAG', 'XPT'])) {
                $gold_assets[] = $asset_data;
                // Add to total gold balance calc if needed here, but usually done in gold/index.php
            } else {
                $user_assets[] = $asset_data;
            }
            
            // If we have USDT/USDC, track liquid stable balance
            if ($row['symbol'] === 'USDT') {
                $total_balance_usdt += (float)$row['balance'];
            }
        }

        // Populate Global User Object
        $user = [
            'id' => $user_row['id'],
            'username' => $user_row['username'],
            'email' => $user_row['email'],
            'role' => $user_row['role'],
            'is_verified' => (bool)$user_row['is_verified'],
            'balance_usdt' => $total_balance_usdt, // This is just the USDT wallet balance
            // Real portfolio total value will be calculated by JS on the frontend
            'joining_date' => $user_row['created_at']
        ];
        
        // Lazy-Process Staking Rewards
        require_once 'process_rewards.php';
    }
}

// Fallback: If no DB data found (e.g. fresh install with empty DB but 'logged in' session), 
// ensure variables are safe to prevent errors.
if (!$user && $user_id) {
    // Session exists but DB user missing? Logout.
    session_destroy();
    header("Location: login.php");
    exit();
}

// Helper to enforce login
function require_login() {
    global $user_id;
    if (!$user_id) {
        header("Location: login.php");
        exit();
    }
}

// -----------------------------------------
// GLOBAL SETTINGS & MAINTENANCE MDOE
// -----------------------------------------
$settings = [];
if (isset($dbc)) {
    $set_q = mysqli_query($dbc, "SELECT * FROM settings");
    while ($row = mysqli_fetch_assoc($set_q)) {
        $settings[$row['key_name']] = $row['value'];
    }
}

// Maintenance Logic
if (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1') {
    // Determine if user is admin
    $is_admin = (isset($user) && $user['role'] === 'admin');
    
    // Allowed scripts (Login, Logout, and Admin directory usually safe if protected)
    // We allow login.php so admins can login. user_login via api? 
    $current_script = basename($_SERVER['PHP_SELF']);
    
    // Check if we are in admin folder? 
    // This init.php is in /includes/ so use logic:
    // If not admin AND not on login/logout page -> SHOW MAINTENANCE
    if (!$is_admin && !in_array($current_script, ['login.php', 'logout.php', 'admin_login.php'])) {
        // Simple Maintenance Page
        http_response_code(503);
        die('
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>System Maintenance</title>
                <style>
                    body { background: #000; color: #fff; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                    .content { text-align: center; max-width: 500px; padding: 20px; }
                    h1 { color: #00d26a; margin-bottom: 20px; }
                    p { color: #98a2b3; line-height: 1.6; }
                    .btn { display: inline-block; margin-top: 30px; padding: 10px 20px; border: 1px solid #333; color: #fff; text-decoration: none; border-radius: 5px; transition: all 0.2s; }
                    .btn:hover { background: #111; border-color: #555; }
                </style>
            </head>
            <body>
                <div class="content">
                    <h1>Under Maintenance</h1>
                    <p>We are currently improving our systems to serve you better. Access is temporarily restricted. Please check back shortly.</p>
                    <a href="login.php" class="btn">Admin Login</a>
                </div>
            </body>
            </html>
        ');
    }
}

// Ensure Gold Transactions Table Exists (Self-Healing)
if ($user_id) {
    $tbl_check = "CREATE TABLE IF NOT EXISTS gold_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('buy', 'sell') NOT NULL,
        asset_symbol VARCHAR(10) NOT NULL,
        amount DECIMAL(20, 8) NOT NULL,
        price_at_transaction DECIMAL(20, 8) NOT NULL,
        total_usd_value DECIMAL(20, 8) NOT NULL,
        status VARCHAR(20) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (asset_symbol)
    )";
    mysqli_query($dbc, $tbl_check);

    // Self-Healing: Add recovery_phrase_hash to users if missing
    $col_check = mysqli_query($dbc, "SHOW COLUMNS FROM users LIKE 'recovery_phrase_hash'");
    if (mysqli_num_rows($col_check) == 0) {
        mysqli_query($dbc, "ALTER TABLE users ADD COLUMN recovery_phrase_hash VARCHAR(255) DEFAULT NULL AFTER password");
    }
}

// Helper: Clean Inputs
function clean_input($data) {
    global $dbc;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($dbc) {
        $data = mysqli_real_escape_string($dbc, $data);
    }
    return $data;
}
?>

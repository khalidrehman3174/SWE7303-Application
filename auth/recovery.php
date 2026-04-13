<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

$step = 1; // 1: Enter Phrase, 2: Reset Password
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_phrase'])) {
        $email = clean_input($_POST['email']);
        $phrase = trim($_POST['phrase']);
        
        // Find user
        $query = "SELECT id, recovery_phrase_hash FROM users WHERE email = '$email' AND recovery_phrase_hash IS NOT NULL LIMIT 1";
        $result = mysqli_query($dbc, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($phrase, $row['recovery_phrase_hash'])) {
                // Success
                $step = 2;
                $_SESSION['recovery_uid'] = $row['id'];
                $_SESSION['recovery_token'] = md5(uniqid(rand(), true)); // Simple session token protection
            } else {
                $error = "Invalid recovery phrase.";
            }
        } else {
            $error = "User not found or recovery not set up.";
        }
    } elseif (isset($_POST['reset_password'])) {
        $step = 2; // Stay on step 2 if error
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_pass) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif (isset($_SESSION['recovery_uid'])) {
            // Update Password
            $uid = $_SESSION['recovery_uid'];
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            
            $update = mysqli_query($dbc, "UPDATE users SET password = '$hashed' WHERE id = $uid");
            
            if ($update) {
                // Clear session
                unset($_SESSION['recovery_uid']);
                unset($_SESSION['recovery_token']);
                $success = "Password updated successfully. <a href='login.php'>Login now</a>";
                $step = 3; // Finished
            } else {
                $error = "Database error: " . mysqli_error($dbc);
            }
        } else {
            $error = "Session expired. Please start over.";
            $step = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0b0f">
    <title>Account Recovery - <?php echo $app_name; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth-modern.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/site-polish.css?v=1.0">
</head>
<body class="auth-body">

    <div class="auth-background"></div>

    <a href="login.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Login
    </a>

    <div class="auth-container">
        <div class="auth-card animate-up">
            
            <?php if ($step === 1): ?>
                <div class="auth-header">
                    <div class="logo-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3 class="auth-title">Account Recovery</h3>
                    <p class="auth-subtitle">Enter your 12-word phrase to restore access</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="modern-form-group">
                        <label class="modern-label">Email Address</label>
                        <div class="modern-input-wrapper">
                            <input type="email" name="email" class="modern-input" placeholder="name@example.com" required>
                            <i class="fas fa-envelope modern-input-icon"></i>
                        </div>
                    </div>

                    <div class="modern-form-group">
                        <label class="modern-label">Recovery Phrase</label>
                        <div class="modern-input-wrapper">
                            <textarea name="phrase" class="modern-input" rows="3" placeholder="word1 word2 word3 ..." required style="height: auto; padding-top: 12px;"></textarea>
                            <i class="fas fa-quote-left modern-input-icon" style="top: 15px;"></i>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size: 12px;">Separate words with spaces.</small>
                    </div>

                    <button type="submit" name="verify_phrase" class="modern-btn mt-3">
                        Verify Phrase <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="auth-header">
                    <div class="logo-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3 class="auth-title">Reset Password</h3>
                    <p class="auth-subtitle">Create a new strong password</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="modern-form-group">
                        <label class="modern-label">New Password</label>
                        <div class="modern-input-wrapper">
                            <input type="password" name="new_password" class="modern-input" placeholder="New password" required>
                            <i class="fas fa-lock modern-input-icon"></i>
                        </div>
                    </div>

                    <div class="modern-form-group">
                        <label class="modern-label">Confirm Password</label>
                        <div class="modern-input-wrapper">
                            <input type="password" name="confirm_password" class="modern-input" placeholder="Confirm password" required>
                            <i class="fas fa-check-circle modern-input-icon"></i>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="modern-btn mt-3">
                        Update Password <i class="fas fa-save ms-2"></i>
                    </button>
                </form>

            <?php elseif ($step === 3): ?>
                <div class="text-center py-5">
                    <div class="mb-4 text-success">
                        <i class="fas fa-check-circle fa-4x"></i>
                    </div>
                    <h3 class="auth-title mb-3">Success!</h3>
                    <p class="text-white-50 mb-4">Your password has been reset successfully.</p>
                    <a href="login.php" class="modern-btn w-auto px-5 d-inline-flex">
                        Login Now
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <script src="../assets/js/platform-loading.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

// Temporarily disconnected: We would normally require the user to be logged in and checking if they are verified.
// require_login();
// if ($user['is_verified']) {
//     header("Location: ../user/mobile.php");
//     exit;
// }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_code = clean_input($_POST['verification_code']);

    if (empty($verification_code)) {
        $error = "Please enter the verification code sent to your email.";
    } else {
        // Placeholder for future verification logic:
        // $query = "SELECT id FROM user_verifications WHERE user_id = '{$user['id']}' AND code = '$verification_code' LIMIT 1";
        // ... if valid, update users table SET is_verified = 1
        
        $success = "Email successfully verified! (Placeholder)";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0b0f">
    <title>Verify Email - <?php echo $app_name; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth-modern.css?v=1.1">
</head>

<body class="auth-body">

    <div class="auth-background"></div>
    <div class="auth-glow-orb"></div>

    <a href="../index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>

    <div class="auth-container">
        
        <div class="auth-card animate-up">
            <div class="auth-header">
                <div class="logo-icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <h3 class="auth-title">Verify Your Email</h3>
                <p class="auth-subtitle">Enter the 6-digit code we sent to your email address.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-4" style="background: rgba(240, 68, 56, 0.1); border: 1px solid rgba(240, 68, 56, 0.2); color: #ff6b6b;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 small mb-4" style="background: rgba(18, 183, 106, 0.1); border: 1px solid rgba(18, 183, 106, 0.2); color: #12b76a;">
                    <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="modern-form-group">
                    <label class="modern-label">Verification Code</label>
                    <div class="modern-input-wrapper">
                        <input type="text" name="verification_code" class="modern-input" placeholder="Enter code" maxlength="6" style="letter-spacing: 4px; font-weight: bold; text-align: center;" required>
                        <i class="fas fa-shield-alt modern-input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="modern-btn mt-4">
                    Verify Email <i class="fas fa-check ms-2" style="font-size: 14px;"></i>
                </button>
            </form>

            <div class="auth-links mt-4">
                Didn't receive the code? 
                <a href="#" class="auth-link-highlight">Resend Code</a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

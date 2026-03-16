<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: ../user/mobile.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = clean_input($_POST['fullname']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password']; // Don't clean password, hash it
    
    // Basic Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } else {
        // Check if email exists
        $check = mysqli_query($dbc, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Email already registered.";
        } else {
            // Create User
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $username = explode(' ', $fullname)[0]; // Simple username derivation
            
            // Random referral code
            $ref_code = strtoupper(substr(md5(uniqid()), 0, 8));
            
            $query = "INSERT INTO users (username, email, password, referral_code, role, is_verified) 
                      VALUES ('$username', '$email', '$hashed', '$ref_code', 'user', 0)";
            
            if (mysqli_query($dbc, $query)) {
                $new_id = mysqli_insert_id($dbc);
                
                // Notification Hook
                $notif_msg = "New user registration: $username ($email)";
                mysqli_query($dbc, "INSERT INTO admin_notifications (type, message, link, created_at) VALUES ('signup', '$notif_msg', 'users.php', NOW())");

                // Create Default Wallets (USDT, BTC, ETH)
                $defaults = ['USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'SOL', 'XRP'];
                foreach ($defaults as $sym) {
                    mysqli_query($dbc, "INSERT INTO wallets (user_id, symbol, balance) VALUES ($new_id, '$sym', 0.0000)");
                }

                // Start Session
                $_SESSION['user_id'] = $new_id;
                header("Location: ../user/mobile.php"); // TODO: Connect to verify_email.php in the future
                exit;
            } else {
                $error = "Registration failed: " . mysqli_error($dbc);
            }
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
    <title>Sign Up - <?php echo $app_name; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <!-- Keep Bootstrap for Grid/Utilities but rely on auth-modern.css for theme -->
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
                    <i class="fas fa-layer-group"></i>
                </div>
                <h3 class="auth-title">Create Account</h3>
                <p class="auth-subtitle">Join millions of users on <?php echo $app_name; ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-4" style="background: rgba(240, 68, 56, 0.1); border: 1px solid rgba(240, 68, 56, 0.2); color: #ff6b6b;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="modern-form-group">
                    <label class="modern-label">Full Name</label>
                    <div class="modern-input-wrapper">
                        <input type="text" name="fullname" class="modern-input" placeholder="John Doe" required>
                        <i class="fas fa-user modern-input-icon"></i>
                    </div>
                </div>

                <div class="modern-form-group">
                    <label class="modern-label">Email Address</label>
                    <div class="modern-input-wrapper">
                        <input type="email" name="email" class="modern-input" placeholder="name@example.com" required>
                        <i class="fas fa-envelope modern-input-icon"></i>
                    </div>
                </div>

                <div class="modern-form-group">
                    <label class="modern-label">Password</label>
                    <div class="modern-input-wrapper">
                        <input type="password" name="password" class="modern-input" placeholder="Create a strong password" required>
                        <i class="fas fa-lock modern-input-icon"></i>
                    </div>
                </div>

                <div class="form-check mb-4 ps-1">
                    <div class="d-flex align-items-center gap-2">
                        <input class="form-check-input shadow-none mt-0"
                            type="checkbox" id="termsCheck" required style="cursor: pointer; width: 16px; height: 16px; background-color: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);">
                        <label class="form-check-label small text-white-50" for="termsCheck" style="font-size: 13px;">
                            I agree to the <a href="../terms.php" class="auth-link-highlight ms-0">Terms</a> & <a
                                href="../privacy.php" class="auth-link-highlight ms-0">Privacy</a>
                        </label>
                    </div>
                </div>

                <button type="submit" class="modern-btn">
                    Create Account <i class="fas fa-arrow-right ms-2" style="font-size: 14px;"></i>
                </button>
            </form>

            <div class="auth-links">
                Already have an account? 
                <a href="login.php" class="auth-link-highlight">Log In</a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

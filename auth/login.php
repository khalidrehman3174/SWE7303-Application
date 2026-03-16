<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

// If already logged in
// If already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($user['role']) && $user['role'] === 'admin') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../user/mobile.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        $query = "SELECT id, password FROM users WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($dbc, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            // Verify hash
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                
                // Fetch role since it wasn't in the initial SELECT
                $role_q = mysqli_query($dbc, "SELECT role FROM users WHERE id = " . $row['id']);
                $role = mysqli_fetch_assoc($role_q)['role'];
                
                if ($role === 'admin') {
                    header("Location: ../admin/index.php");
                } else {
                    header("Location: ../user/mobile.php");
                }
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
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
    <title>Log In - <?php echo $app_name; ?></title>
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
                <h3 class="auth-title">Welcome Back</h3>
                <p class="auth-subtitle">Log in to manage your portfolio</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-4" style="background: rgba(240, 68, 56, 0.1); border: 1px solid rgba(240, 68, 56, 0.2); color: #ff6b6b;">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="modern-form-group">
                    <label class="modern-label">Email Address</label>
                    <div class="modern-input-wrapper">
                        <input type="email" name="email" class="modern-input" placeholder="name@example.com" required>
                        <i class="fas fa-envelope modern-input-icon"></i>
                    </div>
                </div>

                <div class="modern-form-group">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="modern-label mb-0">Password</label>
                    </div>
                    <div class="modern-input-wrapper">
                        <input type="password" name="password" class="modern-input" placeholder="Enter password" required>
                        <i class="fas fa-lock modern-input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="modern-btn">
                    Log In <i class="fas fa-arrow-right ms-2" style="font-size: 14px;"></i>
                </button>
            </form>

            <div class="auth-links">
                Don't have an account? 
                <a href="signup.php" class="auth-link-highlight">Create Account</a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

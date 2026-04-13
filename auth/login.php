<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

// Redirect if session exists
if (isset($_SESSION['user_id'])) {
    header("Location: ../user/index.php");
    exit;
}

$error = '';

if (isset($_GET['account_closed']) && $_GET['account_closed'] === '1') {
    $error = 'Your account has been closed. Contact support if this was not requested by you.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $safe_email = mysqli_real_escape_string($dbc, $email);
        $result = mysqli_query($dbc, "SELECT id, password, is_banned FROM users WHERE email = '$safe_email' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if ((int)($row['is_banned'] ?? 0) === 1) {
                $error = 'This account is closed or restricted. Please contact support.';
            } elseif (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                header("Location: ../user/index.php");
                exit;
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Log In — FinPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/site-polish.css?v=1.0">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --black: #09090b;
            --white: #ffffff;
            --gray-50: #fafafa;
            --gray-100: #f4f5f7;
            --gray-200: #e4e4e7;
            --gray-400: #a1a1aa;
            --gray-600: #52525b;
            --gray-800: #1f2937;
            --accent: #00d26a;
            --accent-dim: rgba(0,210,106,0.12);
            --accent-glow: rgba(0,210,106,0.4);
            --danger: #ef4444;
            --danger-dim: rgba(239,68,68,0.08);
        }

        html, body { height: 100%; font-family: 'Outfit', sans-serif; -webkit-font-smoothing: antialiased; background-image: radial-gradient(ellipse at 50% 0%, rgba(16,185,129,0.07) 0%, transparent 55%), radial-gradient(ellipse at 90% 0%, rgba(16,185,129,0.04) 0%, transparent 45%); }

        /* ── Layout ── */
        .auth-shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* ── Left Brand Panel ── */
        .brand-panel {
            background: var(--black);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            overflow: hidden;
        }

        /* Mesh glow */
        .brand-panel::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 70%;
            height: 70%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 65%);
            filter: blur(60px);
            animation: floatGlow 8s ease-in-out infinite alternate;
            pointer-events: none;
        }
        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 50%;
            height: 50%;
            background: radial-gradient(circle, rgba(99,102,241,0.25) 0%, transparent 65%);
            filter: blur(60px);
            pointer-events: none;
        }

        @keyframes floatGlow {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(5%, 8%) scale(1.1); }
        }

        .brand-logo {
            position: relative;
            z-index: 2;
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--white);
            letter-spacing: -0.5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-logo-dot {
            width: 10px;
            height: 10px;
            background: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent-glow);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }
        .brand-headline {
            font-size: clamp(2.2rem, 3.5vw, 3.2rem);
            font-weight: 900;
            color: var(--white);
            line-height: 1.1;
            letter-spacing: -1.5px;
            margin-bottom: 1.5rem;
        }
        .brand-headline em {
            font-style: normal;
            color: var(--accent);
        }
        .brand-subtext {
            color: var(--gray-400);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.6;
            max-width: 380px;
        }

        /* Social Proof Widget */
        .proof-strip {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .proof-stat {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .proof-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .proof-label { font-size: 0.8rem; color: var(--gray-400); font-weight: 500; }
        .proof-value { font-size: 1rem; color: var(--white); font-weight: 700; }

        /* ── Right Form Panel ── */
        .form-panel {
            background: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            overflow-y: auto;
        }
        .auth-form-layout {
            width: 100%;
            max-width: 400px;
        }

        .form-eyebrow {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        .form-title {
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: -1px;
            color: var(--black);
            margin-bottom: 0.4rem;
        }
        .form-subtitle {
            font-size: 0.95rem;
            color: var(--gray-400);
            font-weight: 500;
            margin-bottom: 2.5rem;
        }

        /* Input Groups */
        .field {
            margin-bottom: 1.25rem;
        }
        .field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 8px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .field input {
            width: 100%;
            padding: 13px 16px 13px 44px;
            border: 1.5px solid var(--gray-200);
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--black);
            background: var(--gray-50);
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .field input:focus {
            border-color: var(--black);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(0,0,0,0.04);
        }
        .field input:focus + i, .input-wrap:focus-within i {
            color: var(--black);
        }
        /* Fix icon layering */
        .input-wrap input { position: relative; }
        .input-wrap i { z-index: 1; pointer-events: none; }

        /* Keep the login surface integrated instead of floating like a separate card */
        .auth-form-layout {
            background: transparent;
            border: 0;
            box-shadow: none;
            padding: 0;
            backdrop-filter: none;
        }
        .auth-form-layout:hover {
            box-shadow: none;
            transform: none;
        }

        /* Error */
        .auth-error {
            background: var(--danger-dim);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        /* Submit Button */
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: var(--black);
            color: var(--white);
            border: none;
            border-radius: 100px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            margin-top: 0.75rem;
        }
        .btn-auth::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 100%);
        }
        .btn-auth:hover {
            transform: scale(0.985);
            background: #1c1c1e;
            box-shadow: 0 8px 30px rgba(0,0,0,0.18);
        }
        .btn-auth:active { transform: scale(0.975); }

        /* Footer Links */
        .form-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-400);
        }
        .form-footer a {
            color: var(--black);
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
        }
        .form-footer a:hover { text-decoration: underline; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.5rem 0;
            color: var(--gray-400);
            font-size: 0.8rem;
            font-weight: 600;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        /* Mobile: stack vertically */
        @media (max-width: 768px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .form-panel { padding: 2rem 1.5rem; justify-content: flex-start; padding-top: 4rem; min-height: 100vh; }
            .mobile-logo {
                display: flex !important;
                align-items: center;
                gap: 8px;
                font-size: 1.2rem;
                font-weight: 900;
                color: var(--black);
                letter-spacing: -0.5px;
                margin-bottom: 2.5rem;
            }
        }

        .mobile-logo { display: none; }
        .back-home {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray-600);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            z-index: 99;
        }
        .back-home:hover { color: var(--black); }

        /* Shimmer animation on load */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .auth-form-layout { animation: slideUp 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both; }
    </style>
</head>
<body>

    <a href="../index.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Home
    </a>

    <div class="auth-shell">

        <!-- ── Left Brand Panel ── -->
        <div class="brand-panel">
            <a href="../index.php" class="brand-logo">
                <div class="brand-logo-dot"></div>
                finpay
            </a>

            <div class="brand-content">
                <h2 class="brand-headline">Your money,<br><em>always moving</em><br>forward.</h2>
                <p class="brand-subtext">The smartest way to manage, grow, and move your money. From crypto to fiat, FinPay handles it all.</p>
            </div>

            <div class="proof-strip">
                <div class="proof-stat">
                    <div class="proof-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="proof-label">Active Users</div>
                        <div class="proof-value">1M+ and growing</div>
                    </div>
                </div>
                <div class="proof-stat">
                    <div class="proof-icon"><i class="fas fa-star"></i></div>
                    <div>
                        <div class="proof-label">App Store Rating</div>
                        <div class="proof-value">4.9 / 5.0 ★</div>
                    </div>
                </div>
                <div class="proof-stat">
                    <div class="proof-icon"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="proof-label">Security</div>
                        <div class="proof-value">Bank-grade encrypted</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right Form Panel ── -->
        <div class="form-panel">
            <div class="auth-form-layout">

                <!-- Mobile-only logo -->
                <div class="mobile-logo">
                    <div style="width:10px;height:10px;background:#00d26a;border-radius:50%;"></div>
                    finpay
                </div>

                <div class="form-eyebrow">Welcome back</div>
                <h1 class="form-title">Log in to FinPay</h1>
                <p class="form-subtitle">Enter your credentials to access your account.</p>

                <?php if ($error): ?>
                    <div class="auth-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="on">

                    <div class="field">
                        <label for="email">Email address</label>
                        <div class="input-wrap">
                            <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <i class="far fa-envelope"></i>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>

                </form>

                <div class="form-footer">
                    Don't have an account?<a href="signup.php">Create one</a>
                </div>

            </div>
        </div>

    </div>

    <script src="../assets/js/platform-loading.js"></script>

</body>
</html>

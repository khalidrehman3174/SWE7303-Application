<?php
require_once '../includes/db_connect.php';
require_once '../includes/init.php';

// Redirect if session exists
if (isset($_SESSION['user_id'])) {
    header("Location: ../user/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = clean_input($_POST['fullname']);
    $email    = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $safe_email = mysqli_real_escape_string($dbc, $email);
        $check = mysqli_query($dbc, "SELECT id FROM users WHERE email = '$safe_email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "An account with that email already exists.";
        } else {
            $hashed   = password_hash($password, PASSWORD_DEFAULT);
            $username = explode(' ', $fullname)[0];
            $ref_code = strtoupper(substr(md5(uniqid()), 0, 8));
            
            $query = "INSERT INTO users (username, email, password, referral_code, role, is_verified) 
                      VALUES ('$username', '$safe_email', '$hashed', '$ref_code', 'user', 0)";
            
            if (mysqli_query($dbc, $query)) {
                $new_id = mysqli_insert_id($dbc);
                $notif_msg = mysqli_real_escape_string($dbc, "New user registration: $username ($email)");
                mysqli_query($dbc, "INSERT INTO admin_notifications (type, message, link, created_at) VALUES ('signup', '$notif_msg', 'users.php', NOW())");
                $defaults = ['USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'SOL', 'XRP'];
                foreach ($defaults as $sym) {
                    mysqli_query($dbc, "INSERT INTO wallets (user_id, symbol, balance) VALUES ($new_id, '$sym', 0.0000)");
                }
                $_SESSION['user_id'] = $new_id;
                header("Location: ../user/index.php");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Create Account — FinPay</title>
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
        .brand-content { position: relative; z-index: 2; }
        .brand-headline {
            font-size: clamp(2.2rem, 3.5vw, 3.2rem);
            font-weight: 900;
            color: var(--white);
            line-height: 1.1;
            letter-spacing: -1.5px;
            margin-bottom: 1.5rem;
        }
        .brand-headline em { font-style: normal; color: var(--accent); }
        .brand-subtext {
            color: var(--gray-400);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.6;
            max-width: 380px;
        }

        /* Feature Checklist */
        .feature-list {
            position: relative;
            z-index: 2;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--gray-400);
            font-size: 0.95rem;
            font-weight: 500;
        }
        .feature-list li i {
            color: var(--accent);
            font-size: 0.9rem;
            flex-shrink: 0;
        }

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
        .form-box {
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
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

        .field { margin-bottom: 1.15rem; }
        .field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-wrap i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            transition: color 0.2s;
            pointer-events: none;
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
        .input-wrap:focus-within i.field-icon { color: var(--black); }

        .form-box:hover {
            box-shadow: none;
            transform: none;
        }

        /* Password Strength */
        .pw-strength {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        .pw-bar {
            flex: 1;
            height: 3px;
            border-radius: 100px;
            background: var(--gray-200);
            transition: background 0.3s;
        }
        .pw-strength-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gray-400);
            margin-top: 5px;
            text-align: right;
        }

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

        /* Terms checkbox custom */
        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .terms-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--gray-200);
            border-radius: 6px;
            cursor: pointer;
            accent-color: var(--black);
            flex-shrink: 0;
            margin-top: 2px;
        }
        .terms-row label {
            font-size: 0.85rem;
            color: var(--gray-400);
            line-height: 1.5;
            font-weight: 500;
            cursor: pointer;
        }
        .terms-row label a { color: var(--black); font-weight: 700; text-decoration: none; }
        .terms-row label a:hover { text-decoration: underline; }

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

        @media (max-width: 768px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .form-panel { padding: 2rem 1.5rem; justify-content: flex-start; padding-top: 4rem; min-height: 100vh; }
            .mobile-logo { display: flex !important; align-items: center; gap: 8px; font-size: 1.2rem; font-weight: 900; color: var(--black); letter-spacing: -0.5px; margin-bottom: 2.5rem; }
        }
        .mobile-logo { display: none; }
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
                <h2 class="brand-headline">Everything you<br>need to <em>take<br>control</em> of money.</h2>
                <p class="brand-subtext">Join over a million people who've moved to a smarter way to bank, save, and invest.</p>
            </div>

            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Instant account, no paperwork</li>
                <li><i class="fas fa-check-circle"></i> Buy, sell & hold 100+ cryptocurrencies</li>
                <li><i class="fas fa-check-circle"></i> Virtual & physical debit cards</li>
                <li><i class="fas fa-check-circle"></i> Bank-grade security & encryption</li>
            </ul>
        </div>

        <!-- ── Right Form Panel ── -->
        <div class="form-panel">
            <div class="form-box">

                <div class="mobile-logo">
                    <div style="width:10px;height:10px;background:#00d26a;border-radius:50%;"></div>
                    finpay
                </div>

                <div class="form-eyebrow">Get started free</div>
                <h1 class="form-title">Create your account</h1>
                <p class="form-subtitle">Takes less than 2 minutes. No credit card needed.</p>

                <?php if ($error): ?>
                    <div class="auth-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="on">

                    <div class="field">
                        <label for="fullname">Full name</label>
                        <div class="input-wrap">
                            <input type="text" id="fullname" name="fullname" placeholder="John Doe" required autocomplete="name" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                            <i class="far fa-user field-icon"></i>
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email address</label>
                        <div class="input-wrap">
                            <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <i class="far fa-envelope field-icon"></i>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password" minlength="8" oninput="checkStrength(this.value)">
                            <i class="fas fa-lock field-icon"></i>
                        </div>
                        <div class="pw-strength" id="pwStrength">
                            <div class="pw-bar" id="bar1"></div>
                            <div class="pw-bar" id="bar2"></div>
                            <div class="pw-bar" id="bar3"></div>
                            <div class="pw-bar" id="bar4"></div>
                        </div>
                        <div class="pw-strength-label" id="pwLabel"></div>
                    </div>

                    <div class="terms-row">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            I agree to the <a href="../terms.php" target="_blank">Terms of Service</a> and <a href="../privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn-auth">
                        Create Account <i class="fas fa-arrow-right"></i>
                    </button>

                </form>

                <div class="form-footer">
                    Already have an account?<a href="login.php">Log in</a>
                </div>

            </div>
        </div>

    </div>

    <script>
        // Password strength meter
        function checkStrength(val) {
            const bars   = [bar1, bar2, bar3, bar4];
            const label  = document.getElementById('pwLabel');
            let score = 0;
            if (val.length >= 8)  score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const colors = ['', '#ef4444', '#f97316', '#eab308', '#00d26a'];
            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

            bars.forEach((b, i) => {
                b.style.background = i < score ? colors[score] : '';
            });
            label.textContent = val.length > 0 ? labels[score] : '';
            label.style.color = colors[score];
        }
    </script>

</body>
</html>

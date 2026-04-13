<?php
require_once '../includes/init.php';
require_once '../includes/wordlist.php';

require_login();

// If user already has a recovery phrase, redirect to dashboard
if (!empty($user_row['recovery_phrase_hash'])) {
    header("Location: ../user/mobile.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_save'])) {
        $phrase = $_POST['phrase'];
        // Validate phrase (simple check)
        $words = explode(' ', $phrase);
        if (count($words) !== 12) {
            $error = "Invalid phrase structure.";
        } else {
            // Hash the phrase
            $hashed_phrase = password_hash($phrase, PASSWORD_DEFAULT);
            $uid = $user['id'];
            
            $update = mysqli_query($dbc, "UPDATE users SET recovery_phrase_hash = '$hashed_phrase' WHERE id = $uid");
            
            if ($update) {
                header("Location: ../user/mobile.php");
                exit;
            } else {
                $error = "Database error: " . mysqli_error($dbc);
            }
        }
    }
}

// Generate new phrase
// Shuffle wordlist and pick first 12
shuffle($wordlist);
$generated_words = array_slice($wordlist, 0, 12);
$generated_phrase = implode(' ', $generated_words);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0b0f">
    <title>Setup Recovery - <?php echo $app_name; ?></title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth-modern.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/site-polish.css?v=1.0">
    <style>
        .word-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .word-chip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            font-size: 14px;
            color: #0f172a;
            position: relative;
        }
        .word-number {
            position: absolute;
            top: 2px;
            left: 5px;
            font-size: 9px;
            color: var(--primary-brand);
            opacity: 0.7;
        }
        .blur-overlay {
            position: absolute;
            inset: 0;
            background: rgba(248, 250, 252, 0.92);
            border: 1px solid #e2e8f0;
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 12px;
            z-index: 10;
            transition: opacity 0.3s;
        }
        .reveal-btn {
            background: #111827;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body class="auth-body">

    <div class="auth-background"></div>

    <div class="auth-container">
        <div class="auth-card animate-up">
            <div class="text-center mb-4">
                <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--primary-brand);"></i>
                <h3 class="auth-title">Secure Your Account</h3>
                <p class="auth-subtitle">Write down these 12 words manually. <br> <span class="text-danger">Do not take a screenshot!</span></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="position-relative">
                <div class="blur-overlay" id="blurLayer">
                    <i class="fas fa-eye-slash fa-2x mb-2 text-muted"></i>
                    <button class="reveal-btn" onclick="revealPhrase()">Click to Reveal Phrase</button>
                </div>
                
                <div class="word-grid" id="wordGrid">
                    <?php foreach ($generated_words as $index => $word): ?>
                        <div class="word-chip">
                            <span class="word-number"><?php echo $index + 1; ?></span>
                            <?php echo $word; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="alert alert-warning py-2 small" style="background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.2); color: #ffc107;">
                <i class="fas fa-exclamation-triangle me-1"></i> If you lose these words, you will lose access to your account forever. We cannot help you recover them.
            </div>

            <form method="POST" onsubmit="return confirmAction()">
                <input type="hidden" name="phrase" value="<?php echo $generated_phrase; ?>">
                
                <div class="form-check mb-4">
                    <input class="form-check-input shadow-none" type="checkbox" id="savedCheck" required style="cursor: pointer; background-color: #fff; border-color: #cbd5e1;">
                    <label class="form-check-label small text-muted" for="savedCheck">
                        I have written down my recovery phrase in a safe place.
                    </label>
                </div>

                <button type="submit" name="confirm_save" class="modern-btn">
                    I Have Saved It <i class="fas fa-check-circle ms-2"></i>
                </button>
            </form>

        </div>
    </div>

    <script>
        function revealPhrase() {
            document.getElementById('blurLayer').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('blurLayer').style.display = 'none';
            }, 300);
        }

        function confirmAction() {
            return confirm("Are you sure you have saved these words? You cannot view them again.");
        }
    </script>
    <script src="../assets/js/platform-loading.js"></script>
</body>
</html>

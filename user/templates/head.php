<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if(!isset($pageTitle)) $pageTitle = 'FinPay Pro';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ===== CENTRAL BRAND DESIGN SYSTEM (User App) =====
         * Mirrors homepage --brand-accent: #10b981 (emerald green)
         * Background uses soft neutral to match homepage white body.
         * ====================================================== */
        :root {
            --brand-accent:       #10b981;
            --brand-accent-glow:  rgba(16,185,129,0.15);
            --bg-body: #f4f5f7;
            --bg-surface: #ffffff;
            --bg-surface-light: #f9fafb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --accent: #10b981;
            --accent-glow: rgba(16,185,129,0.12);
            --border-light: rgba(0, 0, 0, 0.08);
            --sidebar-width: 280px;
            --glass-grad: linear-gradient(180deg, rgba(255,255,255,0.7) 0%, rgba(255,255,255,0.4) 100%);
            --sidebar-bg: rgba(255, 255, 255, 0.85);
            --hover-bg: rgba(0, 0, 0, 0.03);
            --active-bg: rgba(0, 0, 0, 0.06);
            --header-bg: rgba(244, 245, 247, 0.6);
            --btn-primary-bg: #111827;
            --btn-primary-color: #ffffff;
            --list-bg: rgba(0, 0, 0, 0.02);
            --asset-hover: rgba(0, 0, 0, 0.03);
            --asset-border: rgba(0, 0, 0, 0.05);
            --icon-bg-default: rgba(0, 0, 0, 0.05);
            --bottom-nav-bg: rgba(255, 255, 255, 0.95);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-body: #050507;
                --bg-surface: #101014;
                --bg-surface-light: #1a1a20;
                --text-primary: #ffffff;
                --text-secondary: #8a8d93;
                --accent: #10b981;
                --accent-glow: rgba(16, 185, 129, 0.2);
                --border-light: rgba(255, 255, 255, 0.08);
                --glass-grad: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);
                --sidebar-bg: rgba(16, 16, 20, 0.6);
                --hover-bg: rgba(255, 255, 255, 0.03);
                --active-bg: rgba(255, 255, 255, 0.05);
                --header-bg: rgba(5, 5, 7, 0.5);
                --btn-primary-bg: #ffffff;
                --btn-primary-color: #050507;
                --list-bg: rgba(255, 255, 255, 0.02);
                --asset-hover: rgba(255, 255, 255, 0.04);
                --asset-border: rgba(255, 255, 255, 0.03);
                --icon-bg-default: rgba(255, 255, 255, 0.08);
                --bottom-nav-bg: rgba(10, 10, 14, 0.85);
            }
        }

        /* Body: same subtle green glow as homepage — unified brand feel */
        body { background-color: var(--bg-body); color: var(--text-primary); font-family: 'Outfit', sans-serif; -webkit-font-smoothing: antialiased; margin: 0; padding: 0; overflow-x: hidden; background-image: radial-gradient(ellipse at 50% 0%, rgba(16,185,129,0.07) 0%, transparent 55%), radial-gradient(ellipse at 90% 0%, rgba(16,185,129,0.04) 0%, transparent 45%); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 10px; }

        .glass-panel { background: var(--glass-grad); backdrop-filter: blur(20px); border: 1px solid var(--border-light); border-radius: 24px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .sidebar { display: none; width: var(--sidebar-width); background: var(--sidebar-bg); backdrop-filter: blur(20px); border-right: 1px solid var(--border-light); flex-direction: column; position: fixed; height: 100vh; left: 0; top: 0; z-index: 100; overflow-y: auto; }
        .brand { padding: 2.5rem 2rem; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.5px; display: flex; align-items: center; gap: 12px; }
        .brand-icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--accent), #00b35a); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px var(--accent-glow); }
        .sidebar-menu { list-style: none; padding: 0 1rem; margin: 0; flex: 1; }
        .nav-link-pro { display: flex; align-items: center; gap: 16px; padding: 1rem 1.25rem; color: var(--text-secondary); font-weight: 500; font-size: 1.05rem; border-radius: 16px; margin-bottom: 0.5rem; transition: all 0.2s ease; text-decoration: none; }
        .nav-link-pro:hover { color: var(--text-primary); background: var(--hover-bg); }
        .nav-link-pro.active { color: var(--text-primary); background: var(--active-bg); border: 1px solid var(--border-light); }
        .nav-link-pro i { font-size: 1.2rem; width: 24px; text-align: center; }

        .main-content { padding-bottom: 90px; }
        @media (min-width: 992px) {
            .sidebar { display: flex; }
            .bottom-nav { display: none !important; }
            .main-content { padding-left: var(--sidebar-width); padding-bottom: 0; min-height: 100vh; }
            .mobile-header { display: none !important; }
            .content-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2.5rem; padding: 1.5rem 3rem 3rem; }
        }

        .mobile-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: transparent; }
        .profile-btn { width: 44px; height: 44px; border-radius: 14px; background: transparent; border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s; color: var(--text-primary); text-decoration: none; }
        .profile-btn:hover { background: var(--hover-bg); }

        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--bottom-nav-bg); backdrop-filter: blur(20px); border-top: 1px solid var(--border-light); display: flex; justify-content: space-around; padding: 1rem 0; z-index: 1000; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; gap: 6px; color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; font-weight: 500; }
        .bottom-nav-item.active { color: var(--text-primary); }
        .bottom-nav-item i { font-size: 1.3rem; transition: transform 0.2s; }
        .bottom-nav-item.active i { color: var(--accent); transform: translateY(-2px); }

        .btn-pro { flex: 1; padding: 1rem; border-radius: 16px; font-family: 'Outfit', sans-serif; font-weight: 600; font-size: 1rem; border: none; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: all 0.2s ease; }
        .btn-pro-primary { background: var(--btn-primary-bg); color: var(--btn-primary-color); }
        .btn-pro-primary:hover { opacity: 0.9; transform: scale(0.98); }
        .btn-pro-secondary { background: var(--list-bg); color: var(--text-primary); border: 1px solid var(--border-light); }
        .btn-pro-secondary:hover { background: var(--hover-bg); }

        .list-pro { background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 20px; padding: 0.5rem; }
        .asset-row { display: flex; align-items: center; padding: 1rem; border-radius: 14px; transition: background 0.2s; cursor: pointer; }
        .asset-row:hover { background: var(--asset-hover); }
        .asset-row:not(:last-child) { border-bottom: 1px solid var(--asset-border); }
        .asset-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .asset-info { flex: 1; margin-left: 1rem; }
        .asset-name { font-weight: 600; font-size: 1.05rem; }
        .asset-sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 3px; font-weight: 500;}
        .asset-value { text-align: right; }
        .asset-price { font-weight: 700; font-size: 1.05rem; }
        .asset-change { font-size: 0.85rem; margin-top: 3px; font-weight: 600; }

        .pro-card-widget { position: relative; background: linear-gradient(145deg, #111116 0%, #1a1a20 100%); border-radius: 24px; padding: 2.2rem; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 20px 40px rgba(0,0,0,0.4); overflow: hidden; color: #ffffff; aspect-ratio: 1.586 / 1; display: flex; flex-direction: column; justify-content: space-between; }
        .pro-card-widget.physical { background: linear-gradient(145deg, #0f172a 0%, #1e1e24 100%); }
        .pro-card-widget::before { content: ''; position: absolute; top: -50%; right: -20%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(0,210,106,0.3) 0%, transparent 70%); filter: blur(40px); z-index: 0; pointer-events: none; }
        .pro-card-widget.physical::before { background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%); }
        .card-inner { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .card-chip { width: 45px; height: 35px; background: linear-gradient(135deg, #e5e5e5 0%, #a3a3a3 100%); border-radius: 6px; opacity: 0.9; position: relative; overflow: hidden; }
        .card-number { font-family: 'Outfit', monospace; font-size: 1.4rem; letter-spacing: 4px; font-weight: 500; margin-bottom: 1.5rem; color: rgba(255,255,255,0.9); }
        .card-badge { display: inline-block; padding: 4px 12px; background: rgba(255,255,255,0.1); border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}
        .card-meta { display: flex; justify-content: space-between; align-items: flex-end; }
        
        .card-carousel-container { overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; margin-bottom: 2rem; }
        .card-carousel-container::-webkit-scrollbar { display: none; }
        .card-carousel { display: flex; gap: 2rem; padding-bottom: 1rem; width: max-content; }
        @media (min-width: 992px) { .card-carousel { gap: 3rem; } .carousel-item-card { min-width: 450px; } }

        .search-wrap { position: relative; width: 100%; }
        .search-wrap input { width: 100%; padding: 14px 14px 14px 45px; border-radius: 16px; border: 1px solid var(--border-light); background: var(--list-bg); color: var(--text-primary); outline: none; font-family: 'Outfit', sans-serif; font-weight: 500; }
        .search-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .search-wrap input:focus { border-color: var(--accent); background: var(--bg-surface); }
        .grid-header { font-size: 1.1rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; padding: 0 1.5rem; }
        @media (min-width: 992px) { .grid-header { padding: 0; } }

        .contact-avatar { width: 60px; height: 60px; border-radius: 20px; object-fit: cover; border: 1px solid var(--border-light); transition: transform 0.2s; }
        .contact-item { text-align: center; min-width: 72px; cursor: pointer; }
        .contact-item:hover .contact-avatar { transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .contact-name { font-size: 0.8rem; font-weight: 500; margin-top: 8px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 72px;}
        
        .chat-modal { background: var(--bg-body); color: var(--text-primary); border-left: none; }
        .chat-modal.offcanvas-end { width: 100vw; }
        @media (min-width: 992px) { .chat-modal.offcanvas-end { width: 420px !important; border-left: 1px solid var(--border-light); } }
        .chat-header { padding: 1.5rem; border-bottom: 1px solid var(--border-light); background: transparent; backdrop-filter: blur(25px); display: flex; align-items: center; gap: 15px; position: sticky; top: 0; z-index: 10; }
        .chat-body { padding: 1.5rem; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; }
        .chat-bubble { max-width: 80%; padding: 1rem 1.25rem; border-radius: 20px; font-size: 0.95rem; }
        .chat-bubble.received { background: var(--list-bg); border: 1px solid var(--border-light); align-self: flex-start; border-bottom-left-radius: 4px; }
        .chat-bubble.sent { background: var(--btn-primary-bg); color: var(--btn-primary-color); align-self: flex-end; border-bottom-right-radius: 4px; }
        .chat-footer { padding: 1.5rem; border-top: 1px solid var(--border-light); background: var(--bg-surface); display: flex; flex-direction: column; gap: 15px; }
        .chat-amount { font-size: 3.5rem; font-weight: 800; font-family: 'Outfit', sans-serif; text-align: center; width: 100%; background: transparent; border: none; color: var(--text-primary); outline: none; letter-spacing: -1px; padding: 1rem 0; }
        .chat-amount::placeholder { color: var(--text-secondary); opacity: 0.3; }

        .balance-hero { padding: 1rem 1.5rem 2.5rem; text-align: center; }
        .balance-label { font-size: 0.95rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px; }
        .balance-amount { font-size: 4rem; font-weight: 800; font-family: 'Outfit', sans-serif; display: flex; align-items: flex-start; justify-content: center; gap: 5px; line-height: 1; margin-bottom: 2rem; }
        .balance-currency { font-size: 2rem; color: var(--text-secondary); padding-top: 0.5rem; }
        .action-grid { display: flex; gap: 1rem; margin-top: 1.5rem; }

        .quick-actions { display: flex; gap: 1rem; padding: 0 1.5rem; margin-bottom: 2rem; }
        @media (min-width: 992px) { .quick-actions { padding: 0; } }
        .btn-action { flex: 1; padding: 1rem; background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 16px; color: var(--text-primary); font-weight: 600; display: flex; flex-direction: column; align-items: center; gap: 8px; cursor: pointer; transition: background 0.2s; }
        .btn-action:hover { background: var(--asset-hover); }
        .btn-action i { font-size: 1.4rem; color: var(--text-primary); }

        .apple-pay-btn { background: #000; color: #fff; border-radius: 100px; padding: 1rem; display: flex; justify-content: center; align-items: center; gap: 8px; width: 100%; font-weight: 600; font-size: 1.1rem; border: 1px solid #333; margin-bottom: 2rem; cursor: pointer; }
        .apple-pay-btn i { font-size: 1.4rem; }

        .settings-list { background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 20px; overflow: hidden; margin-bottom: 2rem; }
        .setting-row { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; transition: background 0.2s; border-bottom: 1px solid var(--asset-border); }
        .setting-row:last-child { border-bottom: none; }
        .setting-icon { width: 40px; height: 40px; border-radius: 12px; background: var(--icon-bg-default); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--text-primary); }
        .feature-toggle { position: relative; display: inline-block; width: 48px; height: 26px; }
        .feature-toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-secondary); transition: .3s; border-radius: 34px; opacity: 0.5;}
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--accent); opacity: 1; }
        input:checked + .slider:before { transform: translateX(22px); }

        .section-heading { font-weight: 700; font-size: 1.2rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

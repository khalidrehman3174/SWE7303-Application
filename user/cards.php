<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Manage Cards - FinPay Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Light Mode Default */
            --bg-body: #f4f5f7;
            --bg-surface: #ffffff;
            --bg-surface-light: #f9fafb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --accent: #00d26a; 
            --accent-glow: rgba(0, 210, 106, 0.15);
            --border-light: rgba(0, 0, 0, 0.08);
            --sidebar-width: 280px;

            /* Adaptive values for Light Mode */
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
                /* Dark Mode Override */
                --bg-body: #050507;
                --bg-surface: #101014;
                --bg-surface-light: #1a1a20;
                --text-primary: #ffffff;
                --text-secondary: #8a8d93;
                --accent: #00d26a; 
                --accent-glow: rgba(0, 210, 106, 0.2);
                --border-light: rgba(255, 255, 255, 0.08);

                /* Adaptive values for Dark Mode */
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

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            -webkit-font-smoothing: antialiased;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 50% 0%, var(--accent-glow), transparent 50%);
        }

        /* Essential Layout Shell (shared with index.php) */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 10px; }

        .glass-panel {
            background: var(--glass-grad);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease;
        }

        .sidebar {
            display: none;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-light);
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .brand {
            padding: 2.5rem 2rem;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent), #00b35a);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .sidebar-menu { list-style: none; padding: 0 1rem; margin: 0; flex: 1; }
        .nav-link-pro {
            display: flex; align-items: center; gap: 16px; padding: 1rem 1.25rem;
            color: var(--text-secondary); font-weight: 500; font-size: 1.05rem;
            border-radius: 16px; margin-bottom: 0.5rem; transition: all 0.2s ease; text-decoration: none;
        }
        .nav-link-pro:hover { color: var(--text-primary); background: var(--hover-bg); }
        .nav-link-pro.active { color: var(--text-primary); background: var(--active-bg); border: 1px solid var(--border-light); }
        .nav-link-pro i { font-size: 1.2rem; width: 24px; text-align: center; }

        .main-content { padding-bottom: 90px; }

        @media (min-width: 992px) {
            .sidebar { display: flex; }
            .bottom-nav { display: none !important; }
            .main-content { padding-left: var(--sidebar-width); padding-bottom: 0; min-height: 100vh; }
            .mobile-header { display: none !important; }
            .cards-layout { padding: 3rem; max-width: 1400px; margin: 0 auto; }
        }

        .mobile-header {
            display: flex; justify-content: space-between; align-items: center; padding: 1.5rem;
            background: transparent;
        }

        .profile-btn {
            width: 44px; height: 44px; border-radius: 14px; background: transparent;
            border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: background 0.2s;
        }

        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; background: var(--bottom-nav-bg);
            backdrop-filter: blur(20px); border-top: 1px solid var(--border-light);
            display: flex; justify-content: space-around; padding: 1rem 0; z-index: 1000;
        }

        .bottom-nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            color: var(--text-secondary); text-decoration: none; font-size: 0.75rem; font-weight: 500;
        }

        .bottom-nav-item.active { color: var(--text-primary); }
        .bottom-nav-item i { font-size: 1.3rem; transition: transform 0.2s; }
        .bottom-nav-item.active i { color: var(--accent); transform: translateY(-2px); }

        /* CARDS PAGE SPECIFIC STYLES */
        .card-carousel-container {
            margin: 2rem 0;
            position: relative;
            width: 100%;
        }

        .card-carousel {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            gap: 1.5rem;
            padding: 1rem 1.5rem 2rem;
            -ms-overflow-style: none; 
            scrollbar-width: none; 
        }

        .card-carousel::-webkit-scrollbar { display: none; }
        
        .carousel-item-card {
            scroll-snap-align: center;
            min-width: 88%;
            position: relative;
        }

        @media (min-width: 992px) {
            .card-carousel { padding: 1rem 0 3rem; gap: 3rem; }
            .carousel-item-card { min-width: 450px; }
        }

        .pro-card-widget {
            position: relative;
            background: linear-gradient(145deg, #111116 0%, #1a1a20 100%);
            border-radius: 24px;
            padding: 2.2rem;
            border: 1px solid rgba(255,255,255,0.08); /* Always physical metallic edge */
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            overflow: hidden;
            color: #ffffff;
            aspect-ratio: 1.586 / 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pro-card-widget.physical {
            background: linear-gradient(145deg, #0f172a 0%, #1e1e24 100%);
        }

        .pro-card-widget::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(0,210,106,0.3) 0%, transparent 70%);
            filter: blur(40px);
            z-index: 0; pointer-events: none;
        }
        
        .pro-card-widget.physical::before {
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%);
        }

        .card-inner { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .card-chip { width: 45px; height: 35px; background: linear-gradient(135deg, #e5e5e5 0%, #a3a3a3 100%); border-radius: 6px; opacity: 0.9; }
        .card-number { font-family: 'Outfit', monospace; font-size: 1.4rem; letter-spacing: 4px; font-weight: 500; color: rgba(255,255,255,0.9); }
        .card-badge { display: inline-block; padding: 4px 12px; background: rgba(255,255,255,0.1); border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 992px) { .quick-actions { padding: 0; } }

        .btn-action {
            flex: 1;
            padding: 1rem;
            background: var(--list-bg);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-action:hover { background: var(--asset-hover); }
        .btn-action i { font-size: 1.4rem; color: var(--text-primary); }

        .apple-pay-btn {
            background: #000;
            color: #fff;
            border-radius: 100px;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            font-weight: 600;
            font-size: 1.1rem;
            border: 1px solid #333;
            margin-bottom: 2rem;
            cursor: pointer;
        }
        
        .apple-pay-btn i { font-size: 1.4rem; }

        .settings-list {
            background: var(--list-bg);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            transition: background 0.2s;
            border-bottom: 1px solid var(--asset-border);
        }

        .setting-row:last-child { border-bottom: none; }
        .setting-icon {
            width: 40px; height: 40px; border-radius: 12px; background: var(--icon-bg-default);
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--text-primary);
        }

        /* Toggle styling */
        .feature-toggle { position: relative; display: inline-block; width: 48px; height: 26px; }
        .feature-toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-secondary); transition: .3s; border-radius: 34px; opacity: 0.5;}
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--accent); opacity: 1; }
        input:checked + .slider:before { transform: translateX(22px); }
        
        .section-header { font-size: 1.1rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; padding: 0 1.5rem; }
        @media (min-width: 992px) { .section-header { padding: 0; } }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-bolt text-dark fs-6"></i></div>
            FinPay
        </div>
        <nav class="sidebar-menu">
            <a href="index.php" class="nav-link-pro"><i class="fas fa-layer-group"></i> Dashboard</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-paper-plane"></i> Payments</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-wallet"></i> Assets</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-exchange-alt"></i> Swap</a>
            <a href="cards.php" class="nav-link-pro active"><i class="fas fa-credit-card"></i> Cards</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-shield-alt"></i> Security</a>
        </nav>
        <div style="padding: 2rem 1.5rem;">
            <div class="glass-panel" style="padding: 1rem; display: flex; align-items: center; gap: 15px;">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=00d26a&color=fff&bold=true" alt="User" style="width: 40px; border-radius: 12px;">
                <div>
                    <div style="font-weight: 600; font-size: 0.95rem;">John Doe</div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Pro Member</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        
        <header class="mobile-header">
            <div class="profile-btn"><i class="fas fa-chevron-left"></i></div>
            <div style="font-weight: 700; letter-spacing: 1px;">CARDS</div>
            <div class="profile-btn"><i class="fas fa-plus"></i></div>
        </header>

        <div class="cards-layout pt-lg-4 px-lg-4">
            
            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Card Management</h2>
                <button class="btn btn-dark pb-2 pt-2 px-4 rounded-pill" style="background: var(--text-primary); color: var(--bg-body); font-weight: 600;"><i class="fas fa-plus me-2"></i> Get New Card</button>
            </div>

            <div class="row">
                <!-- Left Column (Cards) -->
                <div class="col-lg-6">
                    <div class="card-carousel-container">
                        <div class="card-carousel">
                            <!-- Virtual Card -->
                            <div class="carousel-item-card">
                                <div class="pro-card-widget">
                                    <div class="card-inner">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="card-badge"><i class="fas fa-cube me-1"></i> Virtual</span>
                                            <i class="fas fa-wifi fs-4" style="transform: rotate(90deg); color: rgba(255,255,255,0.6);"></i>
                                        </div>
                                        <div>
                                            <div class="card-number">5412 8842 1923 4092</div>
                                            <div class="card-meta">
                                                <div>
                                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase;">JOHN DOE</div>
                                                    <div style="font-size: 0.9rem; font-weight: 500;">12/28 <span class="ms-3 text-secondary">CVV ***</span></div>
                                                </div>
                                                <div class="text-end">
                                                    <i class="fab fa-cc-visa fs-1" style="color: #fff;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Physical Card -->
                            <div class="carousel-item-card">
                                <div class="pro-card-widget physical">
                                    <div class="card-inner">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="card-badge" style="background: rgba(99, 102, 241, 0.2);"><i class="fas fa-wallet me-1"></i> Physical</span>
                                            <i class="fas fa-wifi fs-4" style="transform: rotate(90deg); color: rgba(255,255,255,0.6);"></i>
                                        </div>
                                        <div>
                                            <div class="card-number">4413 **** **** 9192</div>
                                            <div class="card-meta">
                                                <div>
                                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase;">JOHN DOE</div>
                                                    <div style="font-size: 0.9rem; font-weight: 500;">06/29</div>
                                                </div>
                                                <div class="text-end">
                                                    <i class="fab fa-cc-mastercard fs-1"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <div class="btn-action">
                            <i class="fas fa-eye text-primary"></i>
                            <span style="font-size: 0.85rem;">Show Details</span>
                        </div>
                        <div class="btn-action">
                            <i class="fas fa-snowflake text-info"></i>
                            <span style="font-size: 0.85rem;">Freeze</span>
                        </div>
                        <div class="btn-action">
                            <i class="fas fa-sliders-h text-warning"></i>
                            <span style="font-size: 0.85rem;">Limits</span>
                        </div>
                        <div class="btn-action">
                            <i class="fas fa-cog text-secondary"></i>
                            <span style="font-size: 0.85rem;">Settings</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Settings) -->
                <div class="col-lg-6 px-3 px-lg-4">
                    
                    <div class="apple-pay-btn px-3 d-lg-none">
                        <i class="fab fa-apple"></i> Add to Apple Wallet
                    </div>

                    <div class="section-header">Security Controls</div>
                    <div class="settings-list">
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-globe"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">Online Transactions</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Allow card to be used online</div>
                                </div>
                            </div>
                            <label class="feature-toggle">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-wifi"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">Contactless Payments</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Allow tap-to-pay via POS</div>
                                </div>
                            </div>
                            <label class="feature-toggle">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">Location Security</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Block usage outside your country</div>
                                </div>
                            </div>
                            <label class="feature-toggle">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">ATM Withdrawals</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Cash withdrawal support</div>
                                </div>
                            </div>
                            <label class="feature-toggle">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="section-header">Card Information</div>
                    <div class="settings-list">
                        <div class="setting-row" style="cursor: pointer;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-key"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">View PIN</div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Req. Face ID verification</div>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </div>
                        <div class="setting-row" style="cursor: pointer;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="setting-icon"><i class="fas fa-undo"></i></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">Terminate Card</div>
                                    <div style="font-size: 0.85rem; color: #ef4444;">Permanently delete this card</div>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-secondary"></i>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </main>

    <div class="bottom-nav">
        <a href="index.php" class="bottom-nav-item">
            <i class="fas fa-layer-group"></i>
            <span>Home</span>
        </a>
        <a href="#" class="bottom-nav-item">
            <i class="fas fa-paper-plane"></i>
            <span>Pay</span>
        </a>
        <a href="#" class="bottom-nav-item">
            <i class="fas fa-wallet"></i>
            <span>Assets</span>
        </a>
        <a href="#" class="bottom-nav-item">
            <i class="fas fa-exchange-alt"></i>
            <span>Swap</span>
        </a>
        <a href="#" class="bottom-nav-item active">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
        </a>
    </div>

</body>
</html>

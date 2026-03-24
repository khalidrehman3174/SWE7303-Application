<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>FinPay Pro</title>
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

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 10px; }

        .glass-panel {
            background: var(--glass-grad);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05); /* Lighter shadow universally */
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease;
        }
        
        .glass-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
        }

        /* Sidebar */
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

        .sidebar-menu {
            list-style: none;
            padding: 0 1rem;
            margin: 0;
            flex: 1;
        }

        .nav-link-pro {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 1rem 1.25rem;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 1.05rem;
            border-radius: 16px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .nav-link-pro:hover {
            color: var(--text-primary);
            background: var(--hover-bg);
        }

        .nav-link-pro.active {
            color: var(--text-primary);
            background: var(--active-bg);
            border: 1px solid var(--border-light);
        }
        
        .nav-link-pro i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            padding-bottom: 90px;
        }

        /* Desktop specific */
        @media (min-width: 992px) {
            .sidebar { display: flex; }
            .bottom-nav { display: none !important; }
            .main-content {
                padding-left: var(--sidebar-width);
                padding-bottom: 0;
                min-height: 100vh;
            }
            .content-grid {
                display: grid;
                grid-template-columns: 1.2fr 0.8fr;
                gap: 2.5rem;
                padding: 1.5rem 3rem 3rem;
            }
            .mobile-header { display: none !important; }
        }

        /* Header / Balance */
        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: transparent;
        }

        .profile-btn {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: transparent;
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .profile-btn:hover { background: var(--hover-bg); }

        .balance-hero {
            padding: 3rem 1.5rem 2rem;
        }

        .balance-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .balance-amount {
            font-size: 4.5rem;
            font-weight: 800;
            letter-spacing: -2px;
            line-height: 1;
            display: flex;
            align-items: flex-start;
            gap: 4px;
            color: var(--text-primary);
        }

        .balance-currency {
            font-size: 2.2rem;
            font-weight: 600;
            margin-top: 5px;
            color: var(--text-secondary);
        }

        /* Pro Action Buttons */
        .action-grid {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
        }

        .btn-pro {
            flex: 1;
            padding: 1rem;
            border-radius: 16px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-pro-primary {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-color);
        }
        
        .btn-pro-primary:hover {
            opacity: 0.9;
            transform: scale(0.98);
        }

        .btn-pro-secondary {
            background: var(--bg-surface-light);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }
        
        .btn-pro-secondary:hover {
            background: var(--hover-bg);
        }

        /* Asset Items (Glass List) */
        .section-heading {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-pro {
            background: var(--list-bg);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 0.5rem;
        }

        .asset-row {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 14px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .asset-row:hover {
            background: var(--asset-hover);
        }

        .asset-row:not(:last-child) {
            border-bottom: 1px solid var(--asset-border);
        }

        .asset-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .icon-gbp { background: rgba(0, 82, 255, 0.1); color: #4d88ff; }
        .icon-btc { background: rgba(247, 147, 26, 0.1); color: #f7931a; }
        .icon-vault { background: rgba(0, 210, 106, 0.1); color: var(--accent); }

        .asset-info {
            flex: 1;
            margin-left: 1rem;
        }

        .asset-name { font-weight: 600; font-size: 1.05rem; }
        .asset-sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 3px; font-weight: 500;}
        
        .asset-value { text-align: right; }
        .asset-price { font-weight: 700; font-size: 1.05rem; }
        .asset-change { font-size: 0.85rem; margin-top: 3px; font-weight: 600; }
        
        /* Premium Card Widget */
        .pro-card-widget {
            position: relative;
            background: linear-gradient(145deg, #111116 0%, #1a1a20 100%);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.08); /* Physical cards stay dark-themed everywhere */
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
            margin-bottom: 2rem;
            color: #ffffff;
        }

        .pro-card-widget::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0,210,106,0.3) 0%, transparent 70%);
            filter: blur(40px);
            z-index: 0;
            pointer-events: none;
        }

        .card-inner { position: relative; z-index: 1; }

        .card-chip {
            width: 45px;
            height: 35px;
            background: linear-gradient(135deg, #e5e5e5 0%, #a3a3a3 100%);
            border-radius: 6px;
            margin-bottom: 2rem;
            opacity: 0.9;
            position: relative;
            overflow: hidden;
        }

        .card-number {
            font-family: 'Outfit', monospace;
            font-size: 1.4rem;
            letter-spacing: 4px;
            font-weight: 500;
            margin-bottom: 1.5rem;
            color: rgba(255,255,255,0.9);
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        /* Bottom Nav Mobile */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--bottom-nav-bg);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-around;
            padding: 1rem 0;
            z-index: 1000;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .bottom-nav-item.active {
            color: var(--text-primary);
        }

        .bottom-nav-item i {
            font-size: 1.3rem;
            transition: transform 0.2s;
        }
        
        .bottom-nav-item.active i {
            color: var(--accent);
            transform: translateY(-2px);
        }
        
    </style>
</head>

<body>

    <!-- Desktop Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-bolt text-dark fs-6"></i></div>
            FinPay
        </div>
        <nav class="sidebar-menu">
            <a href="#" class="nav-link-pro active"><i class="fas fa-layer-group"></i> Dashboard</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-paper-plane"></i> Payments</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-wallet"></i> Assets</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-exchange-alt"></i> Swap</a>
            <a href="cards.php" class="nav-link-pro"><i class="fas fa-credit-card"></i> Cards</a>
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
        
        <!-- Mobile Header -->
        <header class="mobile-header">
            <div class="profile-btn">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=00d26a&color=fff&bold=true" style="width: 100%; border-radius: 12px;">
            </div>
            <div style="font-weight: 700; letter-spacing: 1px;">FINPAY</div>
            <a href="cards.php" class="profile-btn" style="color: var(--text-primary); text-decoration: none;">
                <i class="fas fa-credit-card"></i>
            </a>
        </header>

        <!-- Main Layout Grid -->
        <div class="content-grid">
            
            <!-- Left Panel: Core Portfolio -->
            <div class="panel-left">
                
                <div class="balance-hero">
                    <div class="balance-label">Total Portfolio Value</div>
                    <div class="balance-amount">
                        <span class="balance-currency">£</span>12,450<span style="color: var(--text-secondary); font-size: 3rem;">.00</span>
                    </div>
                    
                    <div class="action-grid">
                        <button class="btn-pro btn-pro-primary"><i class="fas fa-plus"></i> Add Money</button>
                        <button class="btn-pro btn-pro-secondary"><i class="fas fa-info-circle"></i> Details</button>
                    </div>
                </div>

                <div class="px-3 px-lg-0 mt-2">
                    <h3 class="section-heading">My Assets <a href="#" style="font-size: 0.9rem; color: var(--accent); text-decoration: none;">Manage</a></h3>
                    
                    <div class="list-pro">
                        <!-- Fiat -->
                        <div class="asset-row">
                            <div class="asset-icon icon-gbp"><i class="fas fa-pound-sign"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">British Pound</div>
                                <div class="asset-sub">Primary Account</div>
                            </div>
                            <div class="asset-value">
                                <div class="asset-price">£4,209.50</div>
                            </div>
                        </div>
                        
                        <!-- Crypto -->
                        <div class="asset-row">
                            <div class="asset-icon icon-btc"><i class="fab fa-bitcoin"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">Bitcoin</div>
                                <div class="asset-sub">0.1250 BTC</div>
                            </div>
                            <div class="asset-value">
                                <div class="asset-price">£8,240.50</div>
                                <div class="asset-change text-success">+2.4%</div>
                            </div>
                        </div>

                        <!-- Vault -->
                        <div class="asset-row">
                            <div class="asset-icon icon-vault"><i class="fas fa-layer-group"></i></div>
                            <div class="asset-info">
                                <div class="asset-name">Yield Vault</div>
                                <div class="asset-sub">Earning 5.2% APY</div>
                            </div>
                            <div class="asset-value">
                                <div class="asset-price">£0.00</div>
                                <div class="asset-change" style="color: var(--text-secondary);">Tap to fund</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Panel: Cards & Activity -->
            <div class="panel-right px-3 px-lg-0 mt-4 mt-lg-5">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Analytics</h3>
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;">This Week <i class="fas fa-chevron-down ms-1"></i></div>
                </div>
                
                <div class="glass-panel text-center" style="padding: 2.5rem 1rem; margin-bottom: 2rem;">
                    <div style="position: relative; height: 120px; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 10px; opacity: 0.8;">
                        <div style="width: 10%; background: var(--text-secondary); height: 30%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 45%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 20%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--accent); height: 60%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                        <div style="width: 10%; background: var(--accent); height: 85%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                        <div style="width: 10%; background: var(--text-secondary); height: 50%; border-radius: 6px; opacity: 0.5;"></div>
                        <div style="width: 10%; background: var(--accent); height: 100%; border-radius: 6px; box-shadow: 0 0 20px var(--accent-glow);"></div>
                    </div>
                    <div class="mt-4">
                        <div style="font-size: 0.95rem; color: var(--text-secondary); font-weight: 500;">Portfolio Performance</div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-top: 5px;">+ £450.20 <span class="text-success" style="font-size: 1rem;">(3.8%)</span></div>
                    </div>
                </div>

                <h3 class="section-heading">Activity</h3>
                <div class="list-pro">
                    <div class="asset-row" style="padding: 0.75rem 1rem;">
                        <div class="asset-icon" style="background: var(--icon-bg-default); width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-coffee"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 0.95rem;">Starbucks</div>
                            <div class="asset-sub">Today</div>
                        </div>
                        <div class="asset-value">
                            <div class="asset-price" style="font-size: 0.95rem;">- £4.50</div>
                        </div>
                    </div>
                    <div class="asset-row" style="padding: 0.75rem 1rem;">
                        <div class="asset-icon" style="background: rgba(0, 210, 106, 0.1); color: var(--accent); width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-arrow-down"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 0.95rem;">Bank Deposit</div>
                            <div class="asset-sub">Yesterday</div>
                        </div>
                        <div class="asset-value">
                            <div class="asset-price text-success" style="font-size: 0.95rem;">+ £50.00</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <!-- Mobile Bottom Nav -->
    <div class="bottom-nav">
        <a href="index.php" class="bottom-nav-item active">
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
        <a href="#" class="bottom-nav-item">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
        </a>
    </div>

</body>
</html>

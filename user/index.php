<?php
$pageTitle = 'FinPay Pro - Dashboard';
$activePage = 'dashboard';
require_once 'templates/head.php';
?>

<body>

    <!-- Desktop Sidebar -->
    <?php require_once 'templates/sidebar.php'; ?>

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
                        <button class="btn-pro btn-pro-primary" data-bs-toggle="offcanvas" data-bs-target="#addMoneyModal"><i class="fas fa-plus"></i> Add Money</button>
                        <button class="btn-pro btn-pro-secondary" data-bs-toggle="offcanvas" data-bs-target="#accountDetailsModal"><i class="fas fa-info-circle"></i> Details</button>
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
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">This Week <i class="fas fa-chevron-right ms-1"></i></div>
                </div>
                
                <div class="glass-panel text-center" style="padding: 2.5rem 1rem; margin-bottom: 2rem; cursor: pointer;" data-bs-toggle="offcanvas" data-bs-target="#analyticsModal">
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

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Activity</h3>
                    <a href="payments.php" style="font-size: 0.85rem; color: var(--accent); font-weight: 600; text-decoration: none;">See All <i class="fas fa-chevron-right ms-1" style="font-size: 0.75rem;"></i></a>
                </div>
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
                    <div class="asset-row" style="padding: 0.75rem 1rem;">
                        <div class="asset-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; width: 40px; height: 40px; font-size: 1.1rem;"><i class="fas fa-film"></i></div>
                        <div class="asset-info">
                            <div class="asset-name" style="font-size: 0.95rem;">Netflix</div>
                            <div class="asset-sub">2 days ago</div>
                        </div>
                        <div class="asset-value">
                            <div class="asset-price" style="font-size: 0.95rem;">- £10.99</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <!-- Mobile Bottom Nav -->
    <?php require_once 'templates/bottom_nav.php'; ?>

    <!-- Account Details Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="accountDetailsModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">GBP Account</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-building text-accent me-1"></i> Local Details</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-right: 15px;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Bank Name</div>
                        <div style="font-weight: 700; font-size: 1.2rem; color: var(--text-primary);">FinPay Bank UK</div>
                    </div>
                </div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Account Holder</div>
                        <div style="font-weight: 700; font-size: 1.15rem; font-family: 'Outfit'; color: var(--text-primary);">John Doe</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Sort Code</div>
                        <div style="font-weight: 700; font-size: 1.35rem; font-family: 'Outfit', monospace; letter-spacing: 2px; color: var(--text-primary);">04-00-04</div>
                    </div>
                    <button class="btn btn-sm shadow-sm" style="background: var(--bg-surface); border: 1px solid var(--border-light); color: var(--text-primary); border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-copy"></i></button>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 2px; font-weight: 600;">Account Number</div>
                        <div style="font-weight: 700; font-size: 1.35rem; font-family: 'Outfit', monospace; letter-spacing: 2px; color: var(--text-primary);">12345678</div>
                    </div>
                    <button class="btn btn-sm shadow-sm" style="background: var(--bg-surface); border: 1px solid var(--border-light); color: var(--text-primary); border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-copy"></i></button>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <div style="background: rgba(16, 185, 129, 0.1); border-radius: 16px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <i class="fas fa-shield-check mt-1" style="color: #10b981; font-size: 1.1rem;"></i>
                    <div>
                        <div style="font-weight: 700; color: #10b981; font-size: 0.95rem;">Verified Primary Account</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; line-height: 1.4;">Only share these native FinPay details with trusted routing parties.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Analytics Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="analyticsModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Portfolio Analytics</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-chart-line text-accent me-1"></i> This Week</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="swap-input-box mb-4 text-center" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 2rem 1.5rem; transition: border-color 0.2s;">
                <div style="font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Total Performance</div>
                <div style="font-weight: 700; font-size: 2.5rem; font-family: 'Outfit'; color: var(--text-primary);">+ £450.20</div>
                <div style="font-size: 1.1rem; color: #10b981; font-weight: 600; margin-top: 5px;"><i class="fas fa-arrow-up me-1"></i>3.8%</div>
                
                <div style="position: relative; height: 100px; width: 100%; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; margin-top: 2rem; opacity: 0.9;">
                    <div style="width: 14%; background: var(--text-secondary); height: 30%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 45%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 20%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 60%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--text-secondary); height: 50%; border-radius: 6px; opacity: 0.4;"></div>
                    <div style="width: 14%; background: var(--accent); height: 85%; border-radius: 6px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                    <div style="width: 14%; background: var(--accent); height: 100%; border-radius: 6px; box-shadow: 0 0 20px var(--accent-glow);"></div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h3 class="section-heading mb-0" style="font-size: 1.1rem;">Asset Allocation</h3>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fas fa-pie-chart text-secondary"></i></div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem;">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">Bitcoin</div>
                    </div>
                    <div style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">66%</div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; margin-right: 12px;"></div>
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">British Pound</div>
                    </div>
                    <div style="font-weight: 700; font-size: 1.05rem; font-family: 'Outfit'; color: var(--text-primary);">34%</div>
                </div>

                <div class="progress mt-4" style="height: 12px; border-radius: 100px; background: rgba(255,255,255,0.05);">
                    <div class="progress-bar" role="progressbar" style="width: 66%; background: #f59e0b; border-radius: 100px;"></div>
                    <div class="progress-bar" role="progressbar" style="width: 34%; background: #3b82f6; border-radius: 100px;"></div>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button class="btn-pro btn-pro-secondary w-100" data-bs-dismiss="offcanvas" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.05rem;">Close Analytics</button>
            </div>

        </div>
    </div>

    <!-- Deposit Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="addMoneyModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Deposit Funds</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-shield-alt text-success me-1"></i> Secure Top-up</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="mb-4 text-center">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Amount to Deposit</div>
                <div class="d-flex align-items-center justify-content-center" style="font-size: 3rem; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                    <span style="font-size: 2rem; margin-right: 5px; color: var(--text-secondary);">£</span>
                    <input type="number" id="depositAmount" value="100" style="background: transparent; border: none; color: var(--text-primary); width: 140px; text-align: center; outline: none;" oninput="updateDepositButton()">
                </div>
            </div>

            <div class="mb-3 px-2">
                <h3 class="section-heading mb-1" style="font-size: 1rem;">Select Method</h3>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'bank')" style="background: rgba(0, 210, 106, 0.05); border: 1px solid var(--accent); border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fas fa-university"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary);">Instant Bank Transfer</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Powered by Open Banking <span class="text-accent fw-bold ms-1" style="font-size: 0.75rem;"><i class="fas fa-bolt"></i> Instant & Free</span></div>
                    </div>
                    <div class="payment-check-icon"><i class="fas fa-check-circle text-accent" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'card')" style="background: var(--bg-surface-light); border: 1px solid transparent; border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Debit or Credit Card</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Visa, Mastercard, Maestro <span class="ms-1 px-1 rounded text-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.7rem;">1% Fee</span></div>
                    </div>
                    <div class="payment-check-icon"><i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="swap-input-box mb-3 payment-method-card" onclick="selectPaymentMethod(this, 'apple')" style="background: var(--bg-surface-light); border: 1px solid transparent; border-radius: 20px; padding: 1.2rem; cursor: pointer; transition: all 0.2s;">
                <div class="d-flex align-items-center">
                    <div class="shadow-sm" style="width: 44px; height: 44px; border-radius: 12px; background: var(--text-primary); color: var(--bg-body); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 14px;">
                        <i class="fab fa-apple"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Apple Pay</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">Instant wallet deposit</div>
                    </div>
                    <div class="payment-check-icon"><i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i></div>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button class="btn-pro btn-pro-primary w-100" onclick="proceedDeposit()" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 25px rgba(239, 184, 12, 0.25);">Continue to Deposit</button>
                <div class="text-center mt-3">
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0; opacity: 0.8;"><i class="fas fa-lock text-success me-1"></i> Funds protected by FinPay Shield.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Card Deposit Offcanvas -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="cardDepositModal" style="z-index: 10500;">
        <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
            <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
            <div class="text-end">
                <div style="font-weight: 700; font-size: 1.1rem;">Card Top-up</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Powered by Stripe</div>
            </div>
        </div>
        <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
            
            <div class="mb-4 text-center">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Amount to Deposit</div>
                <div class="d-flex align-items-center justify-content-center" style="font-size: 3rem; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif;">
                    <span style="font-size: 2rem; margin-right: 5px; color: var(--text-secondary);">£</span>
                    <span id="cardDepositAmountDisplay">100</span>
                </div>
            </div>

            <div class="swap-input-box mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 20px; padding: 1.5rem;">
                <div class="mb-3">
                    <label style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 5px;">Card Number</label>
                    <div style="display: flex; align-items: center; background: var(--bg-body); border-radius: 12px; padding: 12px 15px; border: 1px solid var(--border-light);">
                        <i class="far fa-credit-card text-secondary me-3" style="font-size: 1.2rem;"></i>
                        <input type="text" placeholder="0000 0000 0000 0000" style="background: transparent; border: none; color: var(--text-primary); width: 100%; outline: none; font-size: 1.05rem; letter-spacing: 1px; font-family: 'Outfit', monospace;">
                    </div>
                </div>
                <div class="d-flex gap-3 mb-2">
                    <div style="flex: 1;">
                        <label style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 5px;">Expiry</label>
                        <input type="text" placeholder="MM/YY" style="background: var(--bg-body); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px 15px; color: var(--text-primary); width: 100%; outline: none; font-size: 1.05rem; text-align: center;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 5px;">CVC</label>
                        <input type="text" placeholder="123" style="background: var(--bg-body); border: 1px solid var(--border-light); border-radius: 12px; padding: 12px 15px; color: var(--text-primary); width: 100%; outline: none; font-size: 1.05rem; text-align: center;">
                    </div>
                </div>
            </div>

            <div class="mt-auto w-100" style="padding-bottom: 2rem;">
                <button class="btn-pro btn-pro-primary w-100" data-bs-dismiss="offcanvas" onclick="alert('Demo: Card payment successfully processed!')" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 25px rgba(239, 184, 12, 0.25);">Pay Securely</button>
            </div>

        </div>
    </div>

    <!-- Apple Pay Modal -->
    <div class="modal fade" id="applePayModal" tabindex="-1" aria-hidden="true" style="z-index: 10600;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: 24px; color: var(--text-primary); box-shadow: 0 20px 40px rgba(0,0,0,0.1); padding: 2rem;">
                <div class="text-center">
                    <div style="font-size: 4rem; color: var(--text-primary); margin-bottom: 1rem;"><i class="fab fa-apple"></i></div>
                    <h4 style="font-family: 'Outfit', sans-serif; font-weight: 700; margin-bottom: 0.5rem;">Apple Pay</h4>
                    <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 2rem;">Double click side button to pay <br><span style="font-weight: 700; color: var(--text-primary); font-size: 1.2rem;">£<span id="applePayAmountDisplay">100</span></span></p>
                    <button class="btn btn-dark w-100 py-3" data-bs-dismiss="modal" style="border-radius: 100px; font-weight: 600;">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let selectedMethod = 'bank';
        
        function selectPaymentMethod(element, method) {
            selectedMethod = method;
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.style.background = 'var(--bg-surface-light)';
                card.style.border = '1px solid transparent';
                card.querySelector('.payment-check-icon').innerHTML = '<i class="far fa-circle text-secondary" style="font-size: 1.25rem;"></i>';
            });
            element.style.background = 'rgba(0, 210, 106, 0.05)';
            element.style.border = '1px solid var(--accent)';
            element.querySelector('.payment-check-icon').innerHTML = '<i class="fas fa-check-circle text-accent" style="font-size: 1.25rem;"></i>';
        }

        function updateDepositButton() {
            // Placeholder for real-time validation logic if needed
        }

        function proceedDeposit() {
            let amount = document.getElementById('depositAmount').value || 0;
            
            // Hide current select modal
            let addMoneyModalEl = document.getElementById('addMoneyModal');
            let addMoneyModal = bootstrap.Offcanvas.getInstance(addMoneyModalEl);
            if(addMoneyModal) addMoneyModal.hide();

            setTimeout(() => {
                if(selectedMethod === 'bank') {
                    // Open GBP Local Account Details so they can direct-transfer
                    let modal = new bootstrap.Offcanvas(document.getElementById('accountDetailsModal'));
                    modal.show();
                } else if(selectedMethod === 'card') {
                    // Pass dynamic amount and open secure Card Input Offcanvas
                    document.getElementById('cardDepositAmountDisplay').innerText = amount;
                    let modal = new bootstrap.Offcanvas(document.getElementById('cardDepositModal'));
                    modal.show();
                } else if(selectedMethod === 'apple') {
                    // Pass dynamic amount and launch simulated Native Apple Pay interface
                    document.getElementById('applePayAmountDisplay').innerText = amount;
                    let modal = new bootstrap.Modal(document.getElementById('applePayModal'));
                    modal.show();
                }
            }, 350); // Small timeout to allow previous offcanvas to slide out smoothly
        }
    </script>
</body>
</html>

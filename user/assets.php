<?php
$pageTitle = 'FinPay Pro - Assets';
$activePage = 'assets';
require_once 'templates/head.php';
?>
<body>

    <?php require_once 'templates/sidebar.php'; ?>

    <main class="main-content">
        
        <header class="mobile-header">
            <div class="profile-btn"><i class="fas fa-bars"></i></div>
            <div style="font-weight: 700; letter-spacing: 1px;">ASSETS</div>
            <div class="profile-btn"><i class="fas fa-search"></i></div>
        </header>

        <!-- Desktop Title -->
        <div class="d-none d-lg-flex justify-content-between align-items-center pt-5 px-lg-5 pb-2">
            <h2 class="fw-bold mb-0" style="font-family: 'Outfit';">Wealth & Assets</h2>
            <div class="d-flex align-items-center gap-3">
                <button class="btn-pro btn-pro-primary d-none d-md-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#addAssetModal" style="flex: 0 0 auto; padding: 12px 24px; border-radius: 100px; font-size: 0.95rem;"><i class="fas fa-plus"></i> Add Asset</button>
                <button class="btn-pro btn-pro-primary d-none d-md-flex align-items-center gap-2" style="flex: 0 0 auto; padding: 12px 24px; border-radius: 100px; font-size: 0.95rem; background: var(--text-primary); color: var(--bg-body);"><i class="fas fa-chart-pie"></i> Analytics</button>
            </div>
        </div>

        <div class="content-grid px-lg-5 mt-lg-3">
            
            <!-- Left Panel -->
            <div class="panel-left">

                <!-- Hero Balance Widget -->
                <div class="glass-panel mx-3 mx-lg-0 mb-4 text-center" style="border-radius: 24px; padding: 3rem 1.5rem; position: relative; overflow: hidden;">
                    <!-- Subtle Glow -->
                    <div style="position: absolute; top: -50px; left: 50%; transform: translateX(-50%); width: 150px; height: 150px; background: var(--accent); opacity: 0.15; filter: blur(50px); border-radius: 50%;"></div>
                    
                    <div style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; margin-bottom: 0.5rem; position: relative; z-index: 1;">Total Portfolio</div>
                    <div style="font-size: 4rem; font-weight: 800; font-family: 'Outfit', sans-serif; line-height: 1; margin-bottom: 0.5rem; color: var(--text-primary); position: relative; z-index: 1;">£12,450<span style="font-size: 2.5rem; opacity: 0.5;">.00</span></div>
                    <div style="font-size: 1.05rem; font-weight: 600; color: var(--accent); position: relative; z-index: 1;"><i class="fas fa-arrow-up me-1"></i>+£450.20 <span style="color: var(--text-secondary); font-weight: 500; font-size: 0.95rem;">Past 30 days</span></div>
                    
                    <div class="d-flex justify-content-center mt-4 position-relative z-1" style="max-width: 250px; margin: 0 auto;">
                        <button class="btn-pro btn-pro-primary w-100" data-bs-toggle="offcanvas" data-bs-target="#swapModal" style="border-radius: 14px; padding: 12px; font-weight: 700;"><i class="fas fa-exchange-alt text-accent me-2"></i> Swap Assets</button>
                    </div>
                </div>

                <!-- Filter Pills -->
                <div class="d-flex px-3 px-lg-0 mb-4" style="gap: 10px; overflow-x: auto; -ms-overflow-style: none; scrollbar-width: none; padding-bottom: 5px;">
                    <div style="padding: 10px 24px; background: var(--text-primary); color: var(--bg-body); border-radius: 100px; font-weight: 600; font-size: 0.9rem; white-space: nowrap; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">All Assets</div>
                    <div style="padding: 10px 24px; background: var(--list-bg); border: 1px solid var(--border-light); border-radius: 100px; font-weight: 600; font-size: 0.9rem; white-space: nowrap; cursor: pointer; color: var(--text-secondary);">Crypto</div>
                </div>

                <div class="px-3 px-lg-0 mb-3">
                    <h3 class="section-heading mb-0">Your Balances</h3>
                </div>

                <!-- Asset List -->
                <div class="glass-panel mx-3 mx-lg-0 mb-5" style="border-radius: 24px; padding: 1rem 1.5rem;">
                    
                    <!-- Crypto -->
                    <div class="asset-row px-0" role="button" tabindex="0" onclick="window.location.href='asset_details.php?asset=BTC'" onkeypress="if(event.key==='Enter'){window.location.href='asset_details.php?asset=BTC';}" style="border-radius: 0; padding-top: 0.5rem !important; padding-bottom: 1.5rem !important; border-bottom: 1px solid var(--border-light);">
                        <div class="asset-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; width: 50px; height: 50px; font-size: 1.4rem; border-radius: 16px;"><i class="fab fa-bitcoin"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">Bitcoin <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 4px 8px; border-radius: 6px; color: var(--text-secondary); border: 1px solid var(--border-light);">BTC</span></div>
                            <div class="asset-sub">0.1250 BTC</div>
                        </div>
                        <div class="asset-value text-end">
                            <div class="asset-price" style="font-size: 1.15rem;">£8,240.50</div>
                            <div class="asset-sub text-success" style="opacity: 1;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>+2.4%</div>
                        </div>
                    </div>
                    
                    <!-- Ethereum -->
                    <div class="asset-row px-0" role="button" tabindex="0" onclick="window.location.href='asset_details.php?asset=ETH'" onkeypress="if(event.key==='Enter'){window.location.href='asset_details.php?asset=ETH';}" style="border-radius: 0; padding-top: 1.5rem !important; padding-bottom: 0.5rem !important; border: none;">
                        <div class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 50px; height: 50px; font-size: 1.4rem; border-radius: 16px;"><i class="fab fa-ethereum"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">Ethereum <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 4px 8px; border-radius: 6px; color: var(--text-secondary); border: 1px solid var(--border-light);">ETH</span></div>
                            <div class="asset-sub">0.0000 ETH</div>
                        </div>
                        <div class="asset-value text-end">
                            <div class="asset-price" style="font-size: 1.15rem;">£0.00</div>
                            <div class="asset-sub text-danger" style="opacity: 1;"><i class="fas fa-arrow-down me-1" style="font-size: 0.75rem;"></i>-1.2%</div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Right Panel: Market Trends -->
            <div class="panel-right px-3 px-lg-0 mt-2 mt-lg-0">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-heading mb-0">Trending Crypto</h3>
                    <div style="font-size: 0.85rem; color: var(--accent); font-weight: 600; cursor: pointer;">See All <i class="fas fa-chevron-right ms-1"></i></div>
                </div>

                <div class="glass-panel" style="padding: 1.5rem; border-radius: 24px; margin-bottom: 3rem;">
                    
                    <div class="asset-row px-0 pt-0" role="button" tabindex="0" onclick="window.location.href='asset_details.php?asset=SOL'" onkeypress="if(event.key==='Enter'){window.location.href='asset_details.php?asset=SOL';}" style="padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-light); border-radius: 0;">
                        <div class="asset-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i class="fas fa-bolt"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1rem;">Solana <span style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">SOL</span></div>
                            <div class="asset-sub">£115.20</div>
                        </div>
                        <div class="asset-value text-end">
                            <div class="asset-price text-success" style="font-size: 1rem; color: var(--accent) !important;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>8.4%</div>
                        </div>
                    </div>

                    <div class="asset-row px-0 border-0 pb-0" role="button" tabindex="0" onclick="window.location.href='asset_details.php?asset=BNB'" onkeypress="if(event.key==='Enter'){window.location.href='asset_details.php?asset=BNB';}" style="padding-top: 1.25rem; border-radius: 0;">
                        <div class="asset-icon" style="background: rgba(234, 179, 8, 0.1); color: #eab308; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i class="fas fa-cube"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1rem;">Binance Coin <span style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">BNB</span></div>
                            <div class="asset-sub">£420.32</div>
                        </div>
                        <div class="asset-value text-end">
                            <div class="asset-price text-success" style="font-size: 1rem; color: var(--accent) !important;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>2.1%</div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </main>

    <?php require_once 'templates/bottom_nav.php'; ?>

    <!-- Add Asset Modal (Offcanvas) -->
    <div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="addAssetModal">
        <div class="chat-header">
            <div data-bs-dismiss="offcanvas" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface-light); transition: background 0.2s;"><i class="fas fa-chevron-down" style="transform: rotate(90deg);"></i></div>
            <div>
                <div style="font-weight: 700; font-size: 1.05rem;">Search Assets</div>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">Add cryptocurrencies or stocks</div>
            </div>
        </div>
        
        <div class="chat-body" style="padding: 1.5rem 0;">
            <div class="px-4 mb-4" style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 32px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                <input type="text" placeholder="Search Bitcoin, Apple, etc..." style="width: 100%; padding: 14px 14px 14px 45px; border-radius: 16px; border: 1px solid var(--border-light); background: var(--list-bg); color: var(--text-primary); outline: none; font-family: 'Outfit'; font-weight: 500;">
            </div>
            
            <div class="px-4 mb-3" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Cryptocurrencies</div>
            
            <div class="list-pro mx-3 mb-4" style="background: transparent; border: none; padding: 0;">
                <div class="asset-row px-0 border-bottom" style="padding: 1rem 0 !important; border-radius: 0; border-bottom: 1px solid var(--border-light) !important;">
                    <div class="asset-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 12px;"><i class="fab fa-bitcoin"></i></div>
                    <div class="asset-info ml-3">
                        <div class="asset-name" style="font-size: 1.05rem;">Bitcoin <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 3px 8px; border-radius: 6px; margin-left: 5px;">BTC</span></div>
                        <div class="asset-sub" id="price-bitcoin">£53,420.50</div>
                    </div>
                    <div class="asset-value text-end">
                        <button class="btn btn-sm" style="background: var(--text-primary); color: var(--bg-body); border-radius: 100px; padding: 6px 16px; font-weight: 600; font-family: 'Outfit'; text-transform: uppercase;">Buy</button>
                    </div>
                </div>

                <div class="asset-row px-0 border-bottom" style="padding: 1rem 0 !important; border-radius: 0; border-bottom: 1px solid var(--border-light) !important;">
                    <div class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border-radius: 12px;"><i class="fab fa-ethereum"></i></div>
                    <div class="asset-info ml-3">
                        <div class="asset-name" style="font-size: 1.05rem;">Ethereum <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 3px 8px; border-radius: 6px; margin-left: 5px;">ETH</span></div>
                        <div class="asset-sub" id="price-ethereum">£2,910.30</div>
                    </div>
                    <div class="asset-value text-end">
                        <button class="btn btn-sm" style="background: var(--text-primary); color: var(--bg-body); border-radius: 100px; padding: 6px 16px; font-weight: 600; font-family: 'Outfit'; text-transform: uppercase;">Buy</button>
                    </div>
                </div>

                <div class="asset-row px-0 border-bottom" style="padding: 1rem 0 !important; border-radius: 0; border-bottom: 1px solid var(--border-light) !important;">
                    <div class="asset-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 12px;"><i class="fas fa-coins" style="transform: skew(-10deg);"></i></div>
                    <div class="asset-info ml-3">
                        <div class="asset-name" style="font-size: 1.05rem;">Tether <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 3px 8px; border-radius: 6px; margin-left: 5px;">USDT</span></div>
                        <div class="asset-sub" id="price-tether">£0.79</div>
                    </div>
                    <div class="asset-value text-end">
                        <button class="btn btn-sm" style="background: var(--text-primary); color: var(--bg-body); border-radius: 100px; padding: 6px 16px; font-weight: 600; font-family: 'Outfit'; text-transform: uppercase;">Buy</button>
                    </div>
                </div>

                <div class="asset-row px-0 border-none" style="padding: 1rem 0 !important; border-radius: 0; border: none;">
                    <div class="asset-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 12px;"><i class="fas fa-water"></i></div>
                    <div class="asset-info ml-3">
                        <div class="asset-name" style="font-size: 1.05rem;">Ripple <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 3px 8px; border-radius: 6px; margin-left: 5px;">XRP</span></div>
                        <div class="asset-sub" id="price-ripple">£0.48</div>
                    </div>
                    <div class="asset-value text-end">
                        <button class="btn btn-sm" style="background: var(--text-primary); color: var(--bg-body); border-radius: 100px; padding: 6px 16px; font-weight: 600; font-family: 'Outfit'; text-transform: uppercase;">Buy</button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <?php require_once 'templates/swap_widget.php'; ?>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<script>
fetch('/api/v1/crypto/prices.php')
  .then(response => response.json())
  .then(data => {

    const formatGBP = value => '£' + Number(value).toLocaleString('en-GB', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    if (data.bitcoin?.gbp !== undefined) {
      const el = document.getElementById('price-bitcoin');
      if (el) el.textContent = formatGBP(data.bitcoin.gbp);
    }

    if (data.ethereum?.gbp !== undefined) {
      const el = document.getElementById('price-ethereum');
      if (el) el.textContent = formatGBP(data.ethereum.gbp);
    }

    if (data.tether?.gbp !== undefined) {
      const el = document.getElementById('price-tether');
      if (el) el.textContent = formatGBP(data.tether.gbp);
    }

    if (data.ripple?.gbp !== undefined) {
      const el = document.getElementById('price-ripple');
      if (el) el.textContent = formatGBP(data.ripple.gbp);
    }

    if (data.solana?.gbp !== undefined) {
      const el = document.getElementById('price-solana');
      if (el) el.textContent = formatGBP(data.solana.gbp);
    }

    if (data.binancecoin?.gbp !== undefined) {
      const el = document.getElementById('price-bnb');
      if (el) el.textContent = formatGBP(data.binancecoin.gbp);
    }

  })
  .catch(error => {
    console.error('Crypto API error:', error);
  });
</script>

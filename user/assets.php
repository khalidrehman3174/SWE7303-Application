<?php
$pageTitle = 'FinPay Pro - Assets';
$activePage = 'assets';
require_once 'templates/head.php';
require_once __DIR__ . '/../includes/db_connect.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

function assets_fetch_wallet_balance(mysqli $dbc, int $userId, string $symbol): float
{
    if ($userId <= 0) {
        return 0.0;
    }

    $stmt = mysqli_prepare($dbc, 'SELECT balance FROM wallets WHERE user_id = ? AND symbol = ? LIMIT 1');
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $symbol);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (float)($row['balance'] ?? 0.0);
}

$btcAmount = assets_fetch_wallet_balance($dbc, $userId, 'BTC');
$ethAmount = assets_fetch_wallet_balance($dbc, $userId, 'ETH');
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
                    <div id="portfolioTotalText" style="font-size: 4rem; font-weight: 800; font-family: 'Outfit', sans-serif; line-height: 1; margin-bottom: 0.5rem; color: var(--text-primary); position: relative; z-index: 1;">£0<span style="font-size: 2.5rem; opacity: 0.5;">.00</span></div>
                    <div id="portfolioDeltaText" style="font-size: 1.05rem; font-weight: 600; color: var(--accent); position: relative; z-index: 1;"><i class="fas fa-arrow-up me-1"></i>£0.00 <span style="color: var(--text-secondary); font-weight: 500; font-size: 0.95rem;">Past 24h</span></div>
                    
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
                            <div id="btcAmountText" class="asset-sub"><?php echo number_format($btcAmount, 6); ?> BTC</div>
                        </div>
                        <div class="asset-value text-end">
                            <div id="btcValueText" class="asset-price" style="font-size: 1.15rem;">£0.00</div>
                            <div id="btcChangeText" class="asset-sub text-success" style="opacity: 1;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>+0.00%</div>
                        </div>
                    </div>
                    
                    <!-- Ethereum -->
                    <div class="asset-row px-0" role="button" tabindex="0" onclick="window.location.href='asset_details.php?asset=ETH'" onkeypress="if(event.key==='Enter'){window.location.href='asset_details.php?asset=ETH';}" style="border-radius: 0; padding-top: 1.5rem !important; padding-bottom: 0.5rem !important; border: none;">
                        <div class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 50px; height: 50px; font-size: 1.4rem; border-radius: 16px;"><i class="fab fa-ethereum"></i></div>
                        <div class="asset-info ml-3">
                            <div class="asset-name" style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">Ethereum <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 4px 8px; border-radius: 6px; color: var(--text-secondary); border: 1px solid var(--border-light);">ETH</span></div>
                            <div id="ethAmountText" class="asset-sub"><?php echo number_format($ethAmount, 6); ?> ETH</div>
                        </div>
                        <div class="asset-value text-end">
                            <div id="ethValueText" class="asset-price" style="font-size: 1.15rem;">£0.00</div>
                            <div id="ethChangeText" class="asset-sub text-danger" style="opacity: 1;"><i class="fas fa-arrow-down me-1" style="font-size: 0.75rem;"></i>-0.00%</div>
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
                    
                    <div id="trendRow1" class="asset-row px-0 pt-0" role="button" tabindex="0" data-asset="BTC" style="padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-light); border-radius: 0;">
                        <div id="trendIconWrap1" class="asset-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i id="trendIcon1" class="fab fa-bitcoin"></i></div>
                        <div class="asset-info ml-3">
                            <div id="trendName1" class="asset-name" style="font-size: 1rem;">Bitcoin <span id="trendSymbol1" style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">BTC</span></div>
                            <div id="trendPrice1" class="asset-sub">£0.00</div>
                        </div>
                        <div class="asset-value text-end">
                            <div id="trendChange1" class="asset-price text-success" style="font-size: 1rem; color: var(--accent) !important;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>+0.00%</div>
                        </div>
                    </div>

                    <div id="trendRow2" class="asset-row px-0" role="button" tabindex="0" data-asset="ETH" style="padding-top: 1.25rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-light); border-radius: 0;">
                        <div id="trendIconWrap2" class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i id="trendIcon2" class="fab fa-ethereum"></i></div>
                        <div class="asset-info ml-3">
                            <div id="trendName2" class="asset-name" style="font-size: 1rem;">Ethereum <span id="trendSymbol2" style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">ETH</span></div>
                            <div id="trendPrice2" class="asset-sub">£0.00</div>
                        </div>
                        <div class="asset-value text-end">
                            <div id="trendChange2" class="asset-price text-success" style="font-size: 1rem; color: var(--accent) !important;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>+0.00%</div>
                        </div>
                    </div>

                    <div id="trendRow3" class="asset-row px-0 border-0 pb-0" role="button" tabindex="0" data-asset="SOL" style="padding-top: 1.25rem; border-radius: 0;">
                        <div id="trendIconWrap3" class="asset-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981; width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;"><i id="trendIcon3" class="fas fa-bolt"></i></div>
                        <div class="asset-info ml-3">
                            <div id="trendName3" class="asset-name" style="font-size: 1rem;">Solana <span id="trendSymbol3" style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">SOL</span></div>
                            <div id="trendPrice3" class="asset-sub">£0.00</div>
                        </div>
                        <div class="asset-value text-end">
                            <div id="trendChange3" class="asset-price text-success" style="font-size: 1rem; color: var(--accent) !important;"><i class="fas fa-arrow-up me-1" style="font-size: 0.75rem;"></i>+0.00%</div>
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
                        <div id="btcModalPriceText" class="asset-sub">£0.00</div>
                    </div>
                    <div class="asset-value text-end">
                        <button class="btn btn-sm" style="background: var(--text-primary); color: var(--bg-body); border-radius: 100px; padding: 6px 16px; font-weight: 600; font-family: 'Outfit'; text-transform: uppercase;">Buy</button>
                    </div>
                </div>

                <div class="asset-row px-0 border-bottom" style="padding: 1rem 0 !important; border-radius: 0; border-bottom: 1px solid var(--border-light) !important;">
                    <div class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border-radius: 12px;"><i class="fab fa-ethereum"></i></div>
                    <div class="asset-info ml-3">
                        <div class="asset-name" style="font-size: 1.05rem;">Ethereum <span style="font-size: 0.7rem; background: var(--hover-bg); padding: 3px 8px; border-radius: 6px; margin-left: 5px;">ETH</span></div>
                        <div id="ethModalPriceText" class="asset-sub">£0.00</div>
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
    <script>
        (function () {
            var portfolio = {
                BTC: <?php echo json_encode((float)$btcAmount, JSON_UNESCAPED_SLASHES); ?>,
                ETH: <?php echo json_encode((float)$ethAmount, JSON_UNESCAPED_SLASHES); ?>
            };

            function formatGBP(value) {
                return '£' + Number(value || 0).toLocaleString('en-GB', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function formatAmount(value, symbol) {
                return Number(value || 0).toLocaleString('en-GB', {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 6
                }) + ' ' + symbol;
            }

            function setChangeText(id, pct) {
                var el = document.getElementById(id);
                if (!el) return;

                var value = Number(pct || 0);
                var positive = value >= 0;
                el.classList.remove('text-success', 'text-danger');
                el.classList.add(positive ? 'text-success' : 'text-danger');
                el.innerHTML = '<i class="fas ' + (positive ? 'fa-arrow-up' : 'fa-arrow-down') + ' me-1" style="font-size: 0.75rem;"></i>' + (positive ? '+' : '') + value.toFixed(2) + '%';
            }

            var trendUniverse = ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOGE'];
            var trendMeta = {
                BTC: { name: 'Bitcoin', icon: 'fab fa-bitcoin', color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' },
                ETH: { name: 'Ethereum', icon: 'fab fa-ethereum', color: '#6366f1', bg: 'rgba(99, 102, 241, 0.1)' },
                SOL: { name: 'Solana', icon: 'fas fa-bolt', color: '#10b981', bg: 'rgba(16, 185, 129, 0.1)' },
                BNB: { name: 'Binance Coin', icon: 'fas fa-cube', color: '#eab308', bg: 'rgba(234, 179, 8, 0.1)' },
                XRP: { name: 'XRP', icon: 'fas fa-water', color: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' },
                ADA: { name: 'Cardano', icon: 'fas fa-circle-nodes', color: '#0ea5e9', bg: 'rgba(14, 165, 233, 0.1)' },
                DOGE: { name: 'Dogecoin', icon: 'fas fa-dog', color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' }
            };

            function setTrendRow(slot, symbol, price, pct) {
                var row = document.getElementById('trendRow' + slot);
                var iconWrap = document.getElementById('trendIconWrap' + slot);
                var icon = document.getElementById('trendIcon' + slot);
                var name = document.getElementById('trendName' + slot);
                var sym = document.getElementById('trendSymbol' + slot);
                var priceText = document.getElementById('trendPrice' + slot);
                var change = document.getElementById('trendChange' + slot);

                if (!row || !iconWrap || !icon || !name || !sym || !priceText || !change) {
                    return;
                }

                var meta = trendMeta[symbol] || { name: symbol, icon: 'fas fa-coins', color: '#64748b', bg: 'rgba(100, 116, 139, 0.1)' };
                iconWrap.style.background = meta.bg;
                iconWrap.style.color = meta.color;
                icon.className = meta.icon;

                name.innerHTML = meta.name + ' <span id="trendSymbol' + slot + '" style="font-size: 0.65rem; background: var(--hover-bg); padding: 3px 6px; border-radius: 4px; color: var(--text-secondary); border: 1px solid var(--border-light); margin-left: 5px;">' + symbol + '</span>';
                priceText.textContent = formatGBP(price);

                var positive = Number(pct || 0) >= 0;
                change.classList.remove('text-success', 'text-danger');
                change.classList.add(positive ? 'text-success' : 'text-danger');
                change.innerHTML = '<i class="fas ' + (positive ? 'fa-arrow-up' : 'fa-arrow-down') + ' me-1" style="font-size: 0.75rem;"></i>' + (positive ? '+' : '') + Number(pct || 0).toFixed(2) + '%';

                row.setAttribute('data-asset', symbol);
            }

            function setPortfolioHeadline(totalValue, deltaValue) {
                var totalText = document.getElementById('portfolioTotalText');
                if (totalText) {
                    var fixed = Number(totalValue || 0).toFixed(2);
                    var parts = fixed.split('.');
                    totalText.innerHTML = '£' + Number(parts[0]).toLocaleString('en-GB') + '<span style="font-size: 2.5rem; opacity: 0.5;">.' + parts[1] + '</span>';
                }

                var deltaText = document.getElementById('portfolioDeltaText');
                if (deltaText) {
                    var positive = Number(deltaValue || 0) >= 0;
                    deltaText.style.color = positive ? 'var(--accent)' : '#ef4444';
                    deltaText.innerHTML = '<i class="fas ' + (positive ? 'fa-arrow-up' : 'fa-arrow-down') + ' me-1"></i>' + (positive ? '+' : '-') + formatGBP(Math.abs(deltaValue)).replace('£', '£') + ' <span style="color: var(--text-secondary); font-weight: 500; font-size: 0.95rem;">Past 24h</span>';
                }
            }

            function applyMarket(prices) {
                var btcPrice = Number((prices.BTC && prices.BTC.GBP && prices.BTC.GBP.PRICE) || 0);
                var ethPrice = Number((prices.ETH && prices.ETH.GBP && prices.ETH.GBP.PRICE) || 0);
                var btcPct = Number((prices.BTC && prices.BTC.GBP && prices.BTC.GBP.CHANGEPCT24HOUR) || 0);
                var ethPct = Number((prices.ETH && prices.ETH.GBP && prices.ETH.GBP.CHANGEPCT24HOUR) || 0);

                var btcValue = portfolio.BTC * btcPrice;
                var ethValue = portfolio.ETH * ethPrice;

                var btcPrev = btcPct === -100 ? btcValue : (btcValue / (1 + (btcPct / 100)));
                var ethPrev = ethPct === -100 ? ethValue : (ethValue / (1 + (ethPct / 100)));
                var totalValue = btcValue + ethValue;
                var totalDelta = (btcValue - btcPrev) + (ethValue - ethPrev);

                var btcAmountText = document.getElementById('btcAmountText');
                if (btcAmountText) btcAmountText.textContent = formatAmount(portfolio.BTC, 'BTC');
                var ethAmountText = document.getElementById('ethAmountText');
                if (ethAmountText) ethAmountText.textContent = formatAmount(portfolio.ETH, 'ETH');

                var btcValueText = document.getElementById('btcValueText');
                if (btcValueText) btcValueText.textContent = formatGBP(btcValue);
                var ethValueText = document.getElementById('ethValueText');
                if (ethValueText) ethValueText.textContent = formatGBP(ethValue);

                var btcModalPriceText = document.getElementById('btcModalPriceText');
                if (btcModalPriceText) btcModalPriceText.textContent = formatGBP(btcPrice);
                var ethModalPriceText = document.getElementById('ethModalPriceText');
                if (ethModalPriceText) ethModalPriceText.textContent = formatGBP(ethPrice);

                setChangeText('btcChangeText', btcPct);
                setChangeText('ethChangeText', ethPct);

                var candidates = [];
                trendUniverse.forEach(function (symbol) {
                    var ticker = prices[symbol] && prices[symbol].GBP ? prices[symbol].GBP : null;
                    if (!ticker || typeof ticker.PRICE === 'undefined' || typeof ticker.CHANGEPCT24HOUR === 'undefined') {
                        return;
                    }

                    candidates.push({
                        symbol: symbol,
                        price: Number(ticker.PRICE || 0),
                        pct: Number(ticker.CHANGEPCT24HOUR || 0)
                    });
                });

                candidates.sort(function (a, b) {
                    return b.pct - a.pct;
                });

                var topThree = candidates.slice(0, 3);
                while (topThree.length < 3) {
                    topThree.push({ symbol: 'BTC', price: btcPrice, pct: btcPct });
                }

                setTrendRow(1, topThree[0].symbol, topThree[0].price, topThree[0].pct);
                setTrendRow(2, topThree[1].symbol, topThree[1].price, topThree[1].pct);
                setTrendRow(3, topThree[2].symbol, topThree[2].price, topThree[2].pct);
                setPortfolioHeadline(totalValue, totalDelta);
            }

            function fetchAndRender() {
                fetch('https://min-api.cryptocompare.com/data/pricemultifull?fsyms=' + trendUniverse.join(',') + '&tsyms=GBP', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || !payload.RAW) {
                            return;
                        }
                        applyMarket(payload.RAW);
                    })
                    .catch(function () {
                        // Keep existing values when market fetch fails.
                    });
            }

            fetchAndRender();
            setInterval(fetchAndRender, 20000);

            [1, 2, 3].forEach(function (slot) {
                var row = document.getElementById('trendRow' + slot);
                if (!row) {
                    return;
                }

                row.addEventListener('click', function () {
                    var symbol = String(row.getAttribute('data-asset') || 'BTC').toUpperCase();
                    window.location.href = 'asset_details.php?asset=' + encodeURIComponent(symbol);
                });

                row.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter') {
                        var symbol = String(row.getAttribute('data-asset') || 'BTC').toUpperCase();
                        window.location.href = 'asset_details.php?asset=' + encodeURIComponent(symbol);
                    }
                });
            });
        })();
    </script>

</body>
</html>

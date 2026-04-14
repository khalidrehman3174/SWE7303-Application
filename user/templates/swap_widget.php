<!-- Swap Modal (Offcanvas) -->
<div class="offcanvas offcanvas-end chat-modal" tabindex="-1" id="swapModal" style="z-index: 10500;">
    <div class="chat-header pb-3 border-bottom border-secondary border-opacity-10 align-items-center">
        <div data-bs-dismiss="offcanvas" class="shadow-sm" style="cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: 14px; border: 1px solid var(--border-light); background: var(--bg-surface); transition: background 0.2s;"><i class="fas fa-arrow-right"></i></div>
        <div class="text-end">
            <div style="font-weight: 700; font-size: 1.1rem;">Swap Assets</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-bolt text-accent me-1"></i> 0% Fees</div>
        </div>
    </div>
    
    <div class="chat-body d-flex flex-column" style="padding: 1.5rem 1rem 6rem 1rem; overflow-y: auto;">
        
        <!-- You Pay -->
        <div class="swap-input-box mb-2" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">You Pay</div>
                <div id="swapPayBalanceText" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">Bal: £4,209.50</div>
            </div>
            
            <div class="d-flex align-items-center justify-content-between">
                <input id="swapPayInput" type="text" placeholder="0.00" value="100.00" style="background: transparent; border: none; color: var(--text-primary); font-size: 2.5rem; font-weight: 700; font-family: 'Outfit'; outline: none; width: 55%;">
                
                <div id="swapPaySelector" class="asset-selector d-flex align-items-center shadow-sm" style="gap: 8px; background: var(--bg-surface); padding: 8px 14px 8px 8px; border-radius: 100px; cursor: pointer; border: 1px solid var(--border-light);">
                    <div id="swapPayIconWrap" style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i id="swapPayIcon" class="fas fa-pound-sign" style="font-size: 0.95rem;"></i></div>
                    <span id="swapPaySymbol" style="font-weight: 700; color: var(--text-primary); font-size: 1rem;">GBP</span>
                    <i id="swapPayChevron" class="fas fa-chevron-down text-secondary ms-1" style="font-size: 0.75rem; opacity: 0;"></i>
                </div>
            </div>
        </div>

        <!-- Swap Button -->
        <div class="swap-separator" style="display: flex; justify-content: center; margin: -20px 0; position: relative; z-index: 2;">
            <div id="swapFlipBtn" class="swap-separator-btn shadow-sm" style="width: 44px; height: 44px; border-radius: 50%; background: var(--list-bg); border: 4px solid var(--bg-surface); display: flex; align-items: center; justify-content: center; color: var(--text-primary); cursor: pointer; transition: transform 0.2s; font-size: 1.1rem;">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>

        <!-- You Receive -->
        <div class="swap-input-box mt-2 mb-4" style="background: var(--bg-surface-light); border: 2px solid transparent; border-radius: 24px; padding: 1.5rem; transition: border-color 0.2s;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">You Receive</div>
                <div id="swapReceiveBalanceText" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">Bal: 0.1250 BTC</div>
            </div>
            
            <div class="d-flex align-items-center justify-content-between">
                <input id="swapReceiveInput" type="text" placeholder="0.00" value="0.0024" readonly style="background: transparent; border: none; color: var(--accent); font-size: 2.5rem; font-weight: 700; font-family: 'Outfit'; outline: none; width: 55%;">
                
                <div id="swapReceiveSelector" class="asset-selector d-flex align-items-center shadow-sm" style="gap: 8px; background: var(--bg-surface); padding: 8px 14px 8px 8px; border-radius: 100px; cursor: pointer; border: 1px solid var(--border-light);">
                    <div id="swapReceiveIconWrap" style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i id="swapReceiveIcon" class="fab fa-bitcoin" style="font-size: 0.95rem;"></i></div>
                    <span id="swapReceiveSymbol" style="font-weight: 700; color: var(--text-primary); font-size: 1rem;">BTC</span>
                    <i id="swapReceiveChevron" class="fas fa-chevron-down text-secondary ms-1" style="font-size: 0.75rem;"></i>
                </div>
            </div>
        </div>

        <div id="swapAssetDropdown" class="d-none mb-4" style="background: var(--bg-surface-light); border: 1px solid var(--border-light); border-radius: 18px; padding: 0.9rem;">
            <div style="font-size: 0.78rem; color: var(--text-secondary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 10px;">Select Crypto Asset</div>

            <div class="asset-row px-3 mb-2 rounded swap-asset-option" data-symbol="BTC" data-name="Bitcoin" data-icon="fab fa-bitcoin" data-color="#f59e0b" data-bg="rgba(245, 158, 11, 0.1)" style="background: var(--bg-surface); border: 1px solid var(--border-light); cursor: pointer;">
                <div class="asset-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 12px; width: 40px; height: 40px;"><i class="fab fa-bitcoin"></i></div>
                <div class="asset-info ml-3">
                    <div class="asset-name" style="font-size: 1.05rem;">Bitcoin</div>
                    <div class="asset-sub">BTC</div>
                </div>
            </div>

            <div class="asset-row px-3 rounded swap-asset-option" data-symbol="ETH" data-name="Ethereum" data-icon="fab fa-ethereum" data-color="#6366f1" data-bg="rgba(99, 102, 241, 0.1)" style="background: var(--bg-surface); border: 1px solid var(--border-light); cursor: pointer;">
                <div class="asset-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border-radius: 12px; width: 40px; height: 40px;"><i class="fab fa-ethereum"></i></div>
                <div class="asset-info ml-3">
                    <div class="asset-name" style="font-size: 1.05rem;">Ethereum</div>
                    <div class="asset-sub">ETH</div>
                </div>
            </div>
        </div>

        <div class="exchange-info p-3 mb-4 rounded-4" style="background: var(--bg-surface-light); border: 1px solid var(--border-light);">
            <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 500;">
                <div><i class="fas fa-info-circle me-2 text-accent"></i>Exchange Rate</div>
                <div id="swapRateText">1 GBP = -- BTC</div>
            </div>
            <div class="d-flex justify-content-between align-items-center" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 500;">
                <div>Network Fee</div>
                <div class="text-success text-end">Free</div>
            </div>
        </div>

        <div class="mt-4 mt-auto w-100" style="padding-bottom: 2rem;">
            <button id="swapReviewBtn" class="btn-pro btn-pro-primary w-100" style="padding: 16px; border-radius: 100px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 25px rgba(239, 184, 12, 0.25);">Review Order</button>
            <div class="text-center mt-4">
                <p id="swapHelperText" style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0; opacity: 0.8;"><i class="fas fa-shield-alt text-success me-1"></i> Swaps restricted to GBP ↔ Crypto securely.</p>
            </div>
        </div>
        
    </div>
</div>

<script>
    (function () {
        if (window.__finpaySwapWidgetInit) {
            return;
        }
        window.__finpaySwapWidgetInit = true;

        function toNumber(raw) {
            var clean = String(raw || '').replace(/[^0-9.\-]/g, '');
            var parsed = Number(clean);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function formatFiat(value) {
            return Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatCrypto(value) {
            return Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 6, maximumFractionDigits: 6 });
        }

        function initSwap() {
            var swapModal = document.getElementById('swapModal');
            if (!swapModal) {
                return;
            }

            var payInput = document.getElementById('swapPayInput');
            var receiveInput = document.getElementById('swapReceiveInput');
            var paySelector = document.getElementById('swapPaySelector');
            var receiveSelector = document.getElementById('swapReceiveSelector');
            var paySymbol = document.getElementById('swapPaySymbol');
            var receiveSymbol = document.getElementById('swapReceiveSymbol');
            var payIconWrap = document.getElementById('swapPayIconWrap');
            var receiveIconWrap = document.getElementById('swapReceiveIconWrap');
            var payIcon = document.getElementById('swapPayIcon');
            var receiveIcon = document.getElementById('swapReceiveIcon');
            var payChevron = document.getElementById('swapPayChevron');
            var receiveChevron = document.getElementById('swapReceiveChevron');
            var payBalanceText = document.getElementById('swapPayBalanceText');
            var receiveBalanceText = document.getElementById('swapReceiveBalanceText');
            var rateText = document.getElementById('swapRateText');
            var helperText = document.getElementById('swapHelperText');
            var flipBtn = document.getElementById('swapFlipBtn');
            var reviewBtn = document.getElementById('swapReviewBtn');
            var assetDropdown = document.getElementById('swapAssetDropdown');

            if (!payInput || !receiveInput || !paySelector || !receiveSelector || !paySymbol || !receiveSymbol || !flipBtn || !assetDropdown) {
                return;
            }

            var selectorRows = assetDropdown.querySelectorAll('.swap-asset-option');

            var cfg = window.assetSwapDefaults || {};
            var defaultSymbol = String(cfg.symbol || 'BTC').toUpperCase();
            if (defaultSymbol !== 'BTC' && defaultSymbol !== 'ETH') {
                defaultSymbol = 'BTC';
            }

            var state = {
                isPayFiat: true,
                asset: {
                    symbol: defaultSymbol,
                    name: defaultSymbol === 'ETH' ? 'Ethereum' : 'Bitcoin',
                    icon: defaultSymbol === 'ETH' ? 'fab fa-ethereum' : 'fab fa-bitcoin',
                    color: defaultSymbol === 'ETH' ? '#6366f1' : '#f59e0b',
                    bg: defaultSymbol === 'ETH' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(245, 158, 11, 0.1)'
                },
                rateGbpPerAsset: 0,
                balances: {
                    GBP: toNumber((payBalanceText && payBalanceText.textContent || '').replace('Bal:', '').trim()),
                    BTC: defaultSymbol === 'BTC' ? toNumber((receiveBalanceText && receiveBalanceText.textContent || '').replace('Bal:', '').trim()) : 0,
                    ETH: defaultSymbol === 'ETH' ? toNumber((receiveBalanceText && receiveBalanceText.textContent || '').replace('Bal:', '').trim()) : 0
                }
            };

            if (cfg && typeof cfg.amount !== 'undefined') {
                state.balances[state.asset.symbol] = Number(cfg.amount || 0);
            }

            function updateSelectors() {
                if (state.isPayFiat) {
                    paySymbol.textContent = 'GBP';
                    payIcon.className = 'fas fa-pound-sign';
                    payIconWrap.style.background = 'rgba(59, 130, 246, 0.1)';
                    payIconWrap.style.color = '#3b82f6';

                    receiveSymbol.textContent = state.asset.symbol;
                    receiveIcon.className = state.asset.icon;
                    receiveIconWrap.style.background = state.asset.bg;
                    receiveIconWrap.style.color = state.asset.color;

                    paySelector.removeAttribute('data-bs-toggle');
                    paySelector.removeAttribute('data-bs-target');
                    paySelector.style.cursor = 'default';
                    if (payChevron) payChevron.style.opacity = '0';

                    receiveSelector.style.cursor = 'pointer';
                    if (receiveChevron) receiveChevron.style.opacity = '1';

                    payInput.readOnly = false;
                    receiveInput.readOnly = true;
                } else {
                    paySymbol.textContent = state.asset.symbol;
                    payIcon.className = state.asset.icon;
                    payIconWrap.style.background = state.asset.bg;
                    payIconWrap.style.color = state.asset.color;

                    receiveSymbol.textContent = 'GBP';
                    receiveIcon.className = 'fas fa-pound-sign';
                    receiveIconWrap.style.background = 'rgba(59, 130, 246, 0.1)';
                    receiveIconWrap.style.color = '#3b82f6';

                    paySelector.style.cursor = 'pointer';
                    if (payChevron) payChevron.style.opacity = '1';

                    receiveSelector.removeAttribute('data-bs-toggle');
                    receiveSelector.removeAttribute('data-bs-target');
                    receiveSelector.style.cursor = 'default';
                    if (receiveChevron) receiveChevron.style.opacity = '0';

                    payInput.readOnly = false;
                    receiveInput.readOnly = true;
                }

                if (helperText) {
                    helperText.innerHTML = '<i class="fas fa-shield-alt text-success me-1"></i> Swaps restricted to GBP ↔ ' + state.asset.symbol + ' securely.';
                }

                assetDropdown.classList.add('d-none');
            }

            function updateBalances() {
                if (!payBalanceText || !receiveBalanceText) {
                    return;
                }

                if (state.isPayFiat) {
                    payBalanceText.textContent = 'Bal: £' + formatFiat(state.balances.GBP || 0);
                    receiveBalanceText.textContent = 'Bal: ' + formatCrypto(state.balances[state.asset.symbol] || 0) + ' ' + state.asset.symbol;
                } else {
                    payBalanceText.textContent = 'Bal: ' + formatCrypto(state.balances[state.asset.symbol] || 0) + ' ' + state.asset.symbol;
                    receiveBalanceText.textContent = 'Bal: £' + formatFiat(state.balances.GBP || 0);
                }
            }

            function refreshQuote() {
                fetch('https://min-api.cryptocompare.com/data/pricemultifull?fsyms=' + encodeURIComponent(state.asset.symbol) + '&tsyms=GBP', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                        var info = payload && payload.RAW && payload.RAW[state.asset.symbol] && payload.RAW[state.asset.symbol].GBP ? payload.RAW[state.asset.symbol].GBP : null;
                        if (!info || !info.PRICE) {
                            return;
                        }
                        state.rateGbpPerAsset = Number(info.PRICE || 0);
                        recalc();
                    })
                    .catch(function () {
                        // Keep previous quote on failure.
                    });
            }

            function recalc() {
                var payValue = toNumber(payInput.value);
                var rate = Number(state.rateGbpPerAsset || 0);

                if (state.isPayFiat) {
                    if (rate > 0) {
                        receiveInput.value = formatCrypto(payValue / rate);
                        if (rateText) {
                            rateText.textContent = '1 GBP = ' + formatCrypto(1 / rate) + ' ' + state.asset.symbol;
                        }
                    } else {
                        receiveInput.value = '0.000000';
                        if (rateText) {
                            rateText.textContent = '1 GBP = -- ' + state.asset.symbol;
                        }
                    }
                } else {
                    if (rate > 0) {
                        receiveInput.value = formatFiat(payValue * rate);
                        if (rateText) {
                            rateText.textContent = '1 ' + state.asset.symbol + ' = £' + formatFiat(rate);
                        }
                    } else {
                        receiveInput.value = '0.00';
                        if (rateText) {
                            rateText.textContent = '1 ' + state.asset.symbol + ' = £--';
                        }
                    }
                }

                if (reviewBtn) {
                    var paySym = state.isPayFiat ? 'GBP' : state.asset.symbol;
                    var receiveSym = state.isPayFiat ? state.asset.symbol : 'GBP';
                    reviewBtn.innerHTML = 'Review ' + paySym + ' → ' + receiveSym;
                }
            }

            function applyAsset(symbol, name, icon, color, bg) {
                state.asset.symbol = symbol;
                state.asset.name = name;
                state.asset.icon = icon;
                state.asset.color = color;
                state.asset.bg = bg;

                updateSelectors();
                updateBalances();
                refreshQuote();
            }

            selectorRows.forEach(function (row) {
                row.addEventListener('click', function () {
                    var symbol = String(row.getAttribute('data-symbol') || '').toUpperCase();
                    if (symbol !== 'BTC' && symbol !== 'ETH') {
                        return;
                    }

                    applyAsset(
                        symbol,
                        String(row.getAttribute('data-name') || symbol),
                        String(row.getAttribute('data-icon') || 'fas fa-coins'),
                        String(row.getAttribute('data-color') || '#64748b'),
                        String(row.getAttribute('data-bg') || 'rgba(100, 116, 139, 0.1)')
                    );
                    assetDropdown.classList.add('d-none');
                });
            });

            function openAssetDropdown() {
                assetDropdown.classList.remove('d-none');
            }

            paySelector.addEventListener('click', function () {
                if (!state.isPayFiat) {
                    openAssetDropdown();
                }
            });

            receiveSelector.addEventListener('click', function () {
                if (state.isPayFiat) {
                    openAssetDropdown();
                }
            });

            document.addEventListener('click', function (event) {
                if (assetDropdown.classList.contains('d-none')) {
                    return;
                }

                var target = event.target;
                var clickedSelector = paySelector.contains(target) || receiveSelector.contains(target);
                var clickedDropdown = assetDropdown.contains(target);

                if (!clickedSelector && !clickedDropdown) {
                    assetDropdown.classList.add('d-none');
                }
            });

            flipBtn.addEventListener('click', function () {
                state.isPayFiat = !state.isPayFiat;

                var prevReceive = receiveInput.value;
                if (state.isPayFiat) {
                    payInput.value = formatFiat(toNumber(prevReceive));
                } else {
                    payInput.value = formatCrypto(toNumber(prevReceive));
                }

                updateSelectors();
                updateBalances();
                recalc();
            });

            payInput.addEventListener('input', function () {
                recalc();
            });

            swapModal.addEventListener('shown.bs.offcanvas', function () {
                updateSelectors();
                updateBalances();
                refreshQuote();
            });

            swapModal.addEventListener('hidden.bs.offcanvas', function () {
                assetDropdown.classList.add('d-none');
            });

            updateSelectors();
            updateBalances();
            recalc();
            refreshQuote();
            setInterval(refreshQuote, 30000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSwap);
        } else {
            initSwap();
        }
    })();
</script>

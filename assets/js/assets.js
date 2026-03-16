// assets.js - Professional Asset Render with Live Data
(() => {
    // Use Server-injected assets or fallback to empty
    const assets = window.userAssets || [];

    // Map legacy color/icon if missing from PHP (optional enhancement)
    assets.forEach(a => {
        if (!a.color) a.color = '#6c757d'; // Default color
        // Initialize reactive properties
        a.livePrice = a.price || 0;
        a.change24h = 0;
    });

    let filtered = assets;
    let showBalance = true;
    let hasRendered = false;

    const assetsList = document.getElementById('assets-list');
    const totalBalanceEl = document.getElementById('total-balance');
    const toggleBtn = document.getElementById('toggle-balance');
    const searchInput = document.getElementById('search-assets');
    const addAssetBtn = document.getElementById('modal-add-asset');

    // ...

    function calcTotal(list) {
        return list.reduce((acc, a) => acc + (a.amount * (a.livePrice || 0)), 0);
    }

    function formatUSD(val) {
        return '$' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatCrypto(val, symbol) {
        return val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 6 }) + ' ' + symbol;
    }

    function render(list) {
        let data = list || filtered;
        if (!assetsList) return;

        // Custom Sort: Balance > 0 first, then by USD Value Descending
        data.sort((a, b) => {
            const valA = a.amount * (a.livePrice || 0);
            const valB = b.amount * (b.livePrice || 0);

            const hasBalA = a.amount > 0.00000001;
            const hasBalB = b.amount > 0.00000001;

            if (hasBalA && !hasBalB) return -1;
            if (!hasBalA && hasBalB) return 1;

            return valB - valA;
        });

        if (data.length === 0) {
            assetsList.innerHTML = `
                <div class="text-center py-5 opacity-50">
                    <i class="fas fa-wallet fa-3x mb-3"></i>
                    <p>No assets found. Add one to get started!</p>
                </div>
            `;
            if (totalBalanceEl) totalBalanceEl.textContent = '$0.00';
            return;
        }

        // Animation check
        const animClass = hasRendered ? '' : 'fade-in-up';
        hasRendered = true; // Set flag to true after first render logic check

        // Build HTML String
        const html = data.map(a => {
            const value = (a.amount * (a.livePrice || 0));
            const isPositive = a.change24h >= 0;
            const color = isPositive ? '#0ecb81' : '#f6465d';
            const bgColor = isPositive ? 'rgba(14, 203, 129, 0.1)' : 'rgba(246, 70, 93, 0.1)';
            const changeIcon = isPositive ? 'fa-caret-up' : 'fa-caret-down';

            const iconPath = `assets/icons/${a.symbol.toLowerCase()}.png`;
            const initial = a.symbol[0];

            return `
            <div class="asset-item cursor-pointer ${animClass}" onclick="location.href='asset_details.php?asset=${a.symbol}'">
                <div class="d-flex align-items-center gap-3">
                    <div class="coin-icon position-relative">
                        <img src="${iconPath}" alt="${a.symbol}" 
                             class="rounded-circle shadow-sm"
                             style="width: 42px; height: 42px; object-fit: cover;"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        
                        <div style="display: none; width: 42px; height: 42px; border-radius: 50%; background-color: var(--bg-card-hover, #2c3035); align-items: center; justify-content: center; font-weight: bold; color: white; border: 1px solid rgba(255,255,255,0.1);">
                            ${initial}
                        </div>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="asset-symbol text-white fw-bold">${a.symbol}</span>
                            <span class="badge rounded-pill" style="background-color: ${bgColor}; color: ${color}; font-size: 0.75rem;">
                                <i class="fas ${changeIcon} me-1"></i>${Math.abs(a.change24h).toFixed(2)}%
                            </span>
                        </div>
                        <div class="asset-name text-muted small">${a.name}</div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="asset-value fw-bold text-white">${showBalance ? formatUSD(value) : '****'}</div>
                    <div class="asset-price text-muted small" style="font-size: 0.75rem;">
                         ${showBalance ? formatCrypto(a.amount, a.symbol) : '****'}
                    </div>
                </div>
            </div>`;
        }).join('');

        assetsList.innerHTML = html;

        if (totalBalanceEl) totalBalanceEl.textContent = showBalance ? formatUSD(calcTotal(data)) : '****';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            showBalance = !showBalance;
            toggleBtn.classList.toggle('fa-eye');
            toggleBtn.classList.toggle('fa-eye-slash');
            render();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            filtered = assets.filter(a => a.symbol.toLowerCase().includes(q) || a.name.toLowerCase().includes(q));
            render(filtered);
        });
    }



    // Real-time price updates using CryptoCompare
    async function fetchPrices() {
        try {
            // Collect symbols
            const symbols = assets.map(a => {
                let s = a.symbol ? a.symbol.trim().toUpperCase() : '';
                // Handle basics
                if (s === 'USDT' || s === 'USDC') return null; 
                return s;
            }).filter(s => s);

            if (symbols.length === 0) return;

            const uniqueSymbols = [...new Set(symbols)];
            const symString = uniqueSymbols.join(',');

            const response = await fetch(`https://min-api.cryptocompare.com/data/pricemultifull?fsyms=${symString}&tsyms=USD`);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();

            let updated = false;

            assets.forEach(asset => {
                const sym = asset.symbol ? asset.symbol.toUpperCase() : '';
                
                // Stablecoins
                if (sym === 'USDT' || sym === 'USDC') {
                    asset.livePrice = 1.00;
                    asset.change24h = 0.00;
                    return;
                }

                // Check Data
                if (data.RAW && data.RAW[sym] && data.RAW[sym].USD) {
                    const ticker = data.RAW[sym].USD;
                    asset.livePrice = parseFloat(ticker.PRICE);
                    asset.change24h = parseFloat(ticker.CHANGEPCT24HOUR);
                    updated = true;
                }
            });

            if (updated) render();
        } catch (e) {
            console.error('Error fetching prices:', e);
        }
    }

    // Initial fetch and polling
    fetchPrices();
    setInterval(fetchPrices, 15000); // 15s poll to respect rate limits while keeping fresh

    // initial render
    render();
})();

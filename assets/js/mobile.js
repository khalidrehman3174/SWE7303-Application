// mobile.js - Home Page Logic

document.addEventListener('DOMContentLoaded', () => {
    // 0. Initial Offline Render to prevent "..."
    if (window.userAssets && window.userAssets.length > 0) {
        let initialVal = 0;
        window.userAssets.forEach(asset => {
            let p = (asset.symbol === 'USDT' || asset.symbol === 'USDC') ? 1 : 0;
            initialVal += asset.amount * p;
        });

        // Safety check for element as updateBalanceDisplay might not be defined depending on race
        const b = document.getElementById('total-portfolio-value');
        if (b) b.textContent = initialVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    fetchMarketData();

    // Balance Toggle Logic
    const toggleBtn = document.getElementById('toggle-balance');
    const balanceEl = document.getElementById('total-portfolio-value') || document.getElementById('total-balance');

    if (toggleBtn && balanceEl) {
        // Init: Store initial text as data-value if missing
        if (!balanceEl.hasAttribute('data-value')) {
            balanceEl.setAttribute('data-value', balanceEl.textContent.replace(/,/g, ''));
        }

        // Load preference
        let isHidden = localStorage.getItem('hideBalance') === 'true';

        // Display Function
        const updateDisplay = () => {
            if (isHidden) {
                balanceEl.textContent = '****.**';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                // Format stored value
                const val = parseFloat(balanceEl.getAttribute('data-value') || 0);
                balanceEl.textContent = val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        };

        // Initial Run
        updateDisplay();

        // Click Handler
        toggleBtn.addEventListener('click', () => {
            isHidden = !isHidden;
            localStorage.setItem('hideBalance', isHidden);
            updateDisplay();
        });

        // Expose update function for the fetcher to use
        window.updateBalanceDisplay = (newVal) => {
            balanceEl.setAttribute('data-value', newVal);
            updateDisplay();
        };
    }
});

// Helper for safe parsing
function safeParseFloat(val) {
    if (val === undefined || val === null || val === '') return 0;
    const parsed = parseFloat(val);
    return isNaN(parsed) ? 0 : parsed;
}

function formatPrice(val) {
    const price = safeParseFloat(val);
    if (price === 0) return '--.--';

    if (price >= 1000) return price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (price >= 1) return price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (price >= 0.1) return price.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    return price.toLocaleString('en-US', { minimumFractionDigits: 6, maximumFractionDigits: 8 });
}

function formatVolume(val) {
    const vol = safeParseFloat(val);
    if (vol >= 1e9) return (vol / 1e9).toFixed(2) + 'B';
    if (vol >= 1e6) return (vol / 1e6).toFixed(2) + 'M';
    if (vol >= 1e3) return (vol / 1e3).toFixed(2) + 'K';
    return vol.toFixed(2);
}

async function fetchMarketData() {
    const container = document.getElementById('market-list-container');

    try {
        // A. Identify all unique symbols we need
        // 1. User Assets
        const userSyms = (window.userAssets || [])
            .map(a => a.symbol)
            .filter(s => s !== 'USDT' && s !== 'USDC');
        
        // 2. Market List / Movers (Top Coins)
        const TOP_COINS = ['BTC', 'ETH', 'BNB', 'SOL', 'XRP', 'ADA', 'DOGE', 'TRX', 'DOT', 'MATIC', 'LTC', 'SHIB', 'AVAX', 'UNI', 'ATOM', 'LINK', 'ETC', 'XMR', 'XLM', 'BCH'];
        
        // Combine
        const allSyms = [...new Set([...userSyms, ...TOP_COINS])];
        if (allSyms.length === 0 && (!window.userAssets || window.userAssets.length === 0)) return;

        // B. Fetch form CryptoCompare
        // Note: fsyms limit is usually around 50-100? our list is small (~30).
        const symString = allSyms.join(',');
        
        // If string empty (only stablecoins), skip fetch
        let rawData = {};
        if (symString) {
            const res = await fetch(`https://min-api.cryptocompare.com/data/pricemultifull?fsyms=${symString}&tsyms=USD`);
            if (!res.ok) throw new Error('Network error');
            const json = await res.json();
            rawData = json.RAW || {};
        }

        // C. Update Portfolio Balance
        if (window.userAssets && window.userAssets.length > 0) {
            let totalVal = 0;
            let totalOpenVal = 0;

            window.userAssets.forEach(asset => {
                let price = 0;
                let change = 0;

                // Stablecoins
                if (asset.symbol === 'USDT' || asset.symbol === 'USDC') {
                    price = 1.00; 
                    change = 0.00;
                } 
                // Metals with external feed?
                else if (['XAU', 'XAG', 'XPT'].includes(asset.symbol) && typeof GoldFeed !== 'undefined') {
                    price = GoldFeed.getPrice(asset.symbol);
                    change = GoldFeed.stats ? GoldFeed.stats24h[asset.symbol] : 0;
                }
                // Crypto from API
                else if (rawData[asset.symbol] && rawData[asset.symbol].USD) {
                    const t = rawData[asset.symbol].USD;
                    price = parseFloat(t.PRICE);
                    change = parseFloat(t.CHANGEPCT24HOUR);
                }

                // If price 0, maybe it wasn't fetched or is unsupported, keep 0 to avoid NaN
                
                const currentVal = asset.amount * price;
                // Cost basis approximation for PnL (Current / (1 + change%))
                const openVal = currentVal / (1 + (change / 100));

                totalVal += currentVal;
                totalOpenVal += openVal;
            });

            // Update DOM
            if (typeof window.updateBalanceDisplay === 'function') {
                window.updateBalanceDisplay(totalVal);
            } else {
                 const balEl = document.getElementById('total-portfolio-value');
                 if (balEl) balEl.textContent = totalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // PnL DOM
            const pnlVal = totalVal - totalOpenVal;
            const pnlPct = totalOpenVal > 0 ? (pnlVal / totalOpenVal) * 100 : 0;
            
            const pnlValueEl = document.getElementById('pnl-value');
            if (pnlValueEl) {
                const isPos = pnlVal >= 0;
                const sign = isPos ? '+' : '';
                pnlValueEl.textContent = `${sign}$${pnlVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Today`;
                pnlValueEl.className = `small fw-bold mb-4 ${isPos ? 'text-green' : 'text-danger'}`;
                
                const pnlPercentEl = document.getElementById('pnl-percent');
                if (pnlPercentEl) pnlPercentEl.textContent = `${sign}${pnlPct.toFixed(2)}%`;
            }
        }

        // D. Update Lists (Container & Movers)
        if (container) {
            // Transform rawData to array format expected by updateMarketList
            const marketArray = [];
            TOP_COINS.forEach(sym => {
                if (rawData[sym] && rawData[sym].USD) {
                    const t = rawData[sym].USD;
                    marketArray.push({
                        symbol: sym, // New format: Just 'BTC'
                        lastPrice: t.PRICE,
                        priceChangePercent: t.CHANGEPCT24HOUR,
                        quoteVolume: t.VOLUME24HOURTO
                    });
                }
            });

            // Pass 1: Market List
            updateMarketList(container, marketArray);
            
            // Pass 2: Movers (Sort by Change %)
            // Clone array to sort differently
            const moversArray = [...marketArray].sort((a, b) => Math.abs(parseFloat(b.priceChangePercent)) - Math.abs(parseFloat(a.priceChangePercent)));
            updateMovers(moversArray); 
            // Note: updateMovers implementation needs slight tweak likely, as it expects Binance structure with 'USDT' keys?
            // Let's check updateMovers function in next step or assume generic structure.
            // The existing updateMovers uses filter on .symbol ending in USDT. We need to fix that too.
        }

    } catch (error) {
        console.error('Error fetching market data:', error);
    }
}

function updateMarketList(container, data) {
    const skeletons = container.querySelectorAll('.skeleton-item');
    skeletons.forEach(el => el.remove());

    data.forEach(ticker => {
        const symbol = ticker.symbol;
        const price = formatPrice(ticker.lastPrice);
        const change = safeParseFloat(ticker.priceChangePercent);
        const volume = formatVolume(ticker.quoteVolume);
        const itemId = `hot-item-${symbol}`;
        const isUp = change >= 0;
        const bgClass = isUp ? 'rgba(0, 210, 106, 0.1)' : 'rgba(240, 68, 56, 0.1)';
        const textClass = isUp ? 'var(--primary-green)' : 'var(--danger-red)';
        const iconPath = `assets/icons/${symbol.toLowerCase()}.png`;

        let itemEl = document.getElementById(itemId);
        if (itemEl) {
            const priceEl = itemEl.querySelector('.price-val');
            const changeEl = itemEl.querySelector('.change-val');
            const volEl = itemEl.querySelector('.vol-val');
            if (priceEl && priceEl.textContent !== `$${price}`) priceEl.textContent = `$${price}`;
            if (volEl) volEl.textContent = `Vol ${volume}`;
            if (changeEl) {
                changeEl.textContent = `${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
                changeEl.style.background = bgClass;
                changeEl.style.color = textClass;
            }
        } else {
            const a = document.createElement('a');
            a.href = `chart.php?coin=${ticker.symbol}`;
            a.className = 'market-list-item px-3 text-decoration-none fade-in';
            a.id = itemId;
            a.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-icon" style="width: 40px; height: 40px; background: transparent; padding: 0;">
                         <img src="${iconPath}" alt="${symbol}" 
                              style="width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"
                              onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                         <div style="display: none; width: 100%; height: 100%; border-radius: 50%; background: rgba(255,255,255,0.05); align-items: center; justify-content: center; font-weight: bold; color: white;">${symbol[0]}</div>
                    </div>
                    <div>
                        <div class="fw-bold text-white" style="font-size: 1rem;">${symbol}</div>
                        <div class="small text-muted vol-val" style="font-size: 0.75rem;">Vol ${volume}</div>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                    <div class="fw-bold text-white price-val" style="font-size: 1rem; font-family: 'Inter', monospace;">$${price}</div>
                    <div class="change-val" style="background: ${bgClass}; color: ${textClass}; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">${change > 0 ? '+' : ''}${change.toFixed(2)}%</div>
                </div>
             `;
            container.appendChild(a);
        }
    });
}

function updateMovers(allData) {
    const moversContainer = document.querySelector('.movers-scroll');
    if (!moversContainer) return;

    const skeletons = moversContainer.querySelectorAll('.skeleton-item');
    skeletons.forEach(el => el.remove());

    // In new logic, allData is already the top coins list, just sort/filter as needed
    const topMovers = allData
        .sort((a, b) => parseFloat(b.priceChangePercent) - parseFloat(a.priceChangePercent))
        .slice(0, 12);

    topMovers.forEach((t, index) => {
        const sym = t.symbol;
        const price = formatPrice(t.lastPrice);
        const change = parseFloat(t.priceChangePercent);

        const isUp = change >= 0;
        const colorClass = isUp ? 'text-green' : 'text-red';
        const iconPath = `assets/icons/${sym.toLowerCase()}.png`;
        const itemId = `mover-item-${sym}`;

        let itemEl = document.getElementById(itemId);

        if (itemEl && itemEl.parentNode === moversContainer) {
            // Move to correct position
            if (moversContainer.children[index] !== itemEl) {
                if (moversContainer.children[index]) moversContainer.insertBefore(itemEl, moversContainer.children[index]);
                else moversContainer.appendChild(itemEl);
            }
            // Update
            itemEl.querySelector('.price-val').innerText = `$${price}`;
            const chgEl = itemEl.querySelector('.change-val');
            chgEl.innerText = `${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
            chgEl.className = `${colorClass} small fw-bold change-val`;
        } else {
            const a = document.createElement('a');
            a.href = `chart.php?coin=${t.symbol}`;
            a.className = 'card-glass mover-card text-decoration-none text-white d-block';
            a.id = itemId;
            a.style.minWidth = '140px';
            a.style.marginRight = '1rem';
            a.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="${iconPath}" alt="${sym}" style="width: 24px; height: 24px; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none;font-weight:bold;">${sym[0]}</div>
                    <span class="fw-bold">${sym}</span>
                </div>
                <div class="fw-bold mb-1 price-val" style="font-size: 1rem; font-family: 'Inter', monospace;">$${price}</div>
                <div class="${colorClass} small fw-bold change-val">${change > 0 ? '+' : ''}${change.toFixed(2)}%</div>
            `;

            if (moversContainer.children[index]) moversContainer.insertBefore(a, moversContainer.children[index]);
            else moversContainer.appendChild(a);
        }
    });

    while (moversContainer.children.length > topMovers.length) {
        moversContainer.lastChild.remove();
    }
}

// Auto-update every 10 seconds
setInterval(fetchMarketData, 10000);

// markets.js - Robust Market Data Manager

const MarketDataManager = (() => {
    const CACHE_KEY = 'upt_market_data';
    const CACHE_DURATION = 15000; // 15 seconds
    const API_URL = 'https://min-api.cryptocompare.com/data/pricemultifull';

    // Top 40 Important Coins
    const TOP_40_SYMBOLS = [
        'BTC', 'ETH', 'BNB', 'XRP', 'SOL',
        'ADA', 'DOGE', 'TRX', 'DOT', 'MATIC',
        'LTC', 'SHIB', 'AVAX', 'UNI', 'ATOM',
        'LINK', 'ETC', 'XMR', 'XLM', 'BCH',
        'ALGO', 'NEAR', 'FIL', 'VET', 'HBAR',
        'ICP', 'EGLD', 'SAND', 'THETA', 'MANA',
        'AXS', 'EOS', 'AAVE', 'CAKE', 'GRT',
        'KLAY', 'NEO', 'MKR', 'BSV', 'XTZ'
    ];

    let allMarkets = [];
    let updateInterval = null;

    // Load from cache
    function loadCache() {
        try {
            const cached = localStorage.getItem(CACHE_KEY);
            if (cached) {
                const { timestamp, data } = JSON.parse(cached);
                if (Date.now() - timestamp < CACHE_DURATION) {
                    console.log('Loaded market data from cache');
                    return data;
                }
            }
        } catch (e) {
            console.error('Cache load error', e);
        }
        return null;
    }

    // Save to cache
    function saveCache(data) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({
                timestamp: Date.now(),
                data: data
            }));
        } catch (e) {
            console.error('Cache save error', e);
        }
    }

    // Fetch data
    async function fetchData() {
        try {
            const syms = TOP_40_SYMBOLS.join(',');
            const response = await fetch(`${API_URL}?fsyms=${syms}&tsyms=USD`);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();

            // Transform CryptoCompare format to array for render
            // Structure: data.RAW[SYMBOL].USD
            const processed = [];
            
            if (data.RAW) {
                Object.keys(data.RAW).forEach(sym => {
                    const t = data.RAW[sym].USD;
                    processed.push({
                        symbol: sym,
                        lastPrice: t.PRICE,
                        priceChangePercent: t.CHANGEPCT24HOUR,
                        quoteVolume: t.VOLUME24HOURTO // USD Volume
                    });
                });
            }
            
            // Sort by defined order or Volume? 
            // Original code sorted by index in TOP_40_SYMBOLS, let's keep that
            processed.sort((a, b) => TOP_40_SYMBOLS.indexOf(a.symbol) - TOP_40_SYMBOLS.indexOf(b.symbol));

            saveCache(processed);
            return processed;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }

    // Helper for safe parsing
    function safeParseFloat(val) {
        if (val === undefined || val === null || val === '') return 0;
        const parsed = parseFloat(val);
        return isNaN(parsed) ? 0 : parsed;
    }

    // Professional formatting helpers
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

    // Render list (Optimized for no-blink)
    function render(markets) {
        const container = document.getElementById('market-list');
        if (!container) return;

        if (markets.length === 0) {
            container.innerHTML = '<div class="p-5 text-center text-muted">No markets found</div>';
            return;
        }

        // Clear "No markets" / Loading state if present (checked by looking for existing items)
        if (!container.querySelector('.market-item')) {
            container.innerHTML = '';
        }

        const existingIds = new Set();

        markets.forEach(ticker => {
            const symbol = ticker.symbol; // Already bare symbol now
            const price = formatPrice(ticker.lastPrice);
            const change = safeParseFloat(ticker.priceChangePercent);
            const volume = formatVolume(ticker.quoteVolume);

            const isUp = change >= 0;
            const bgClass = isUp ? 'rgba(0, 210, 106, 0.1)' : 'rgba(240, 68, 56, 0.1)';
            const textClass = isUp ? 'var(--primary-green)' : 'var(--danger-red)';
            const iconColor = isUp ? '#00d26a' : '#f04438';
            const iconPath = `assets/icons/${symbol.toLowerCase()}.png`;
            const itemId = `market-item-${symbol}`;

            existingIds.add(itemId);

            let itemEl = document.getElementById(itemId);

            if (itemEl) {
                // UPDATE existing
                const priceEl = itemEl.querySelector('.price-val');
                const changeEl = itemEl.querySelector('.change-val');
                const volEl = itemEl.querySelector('.vol-val');

                // Update text only if changed (browser handles optimization efficiently, but explicit checks are nice)
                if (priceEl.textContent !== `$${price}`) priceEl.textContent = `$${price}`;

                if (volEl.textContent !== `Vol ${volume}`) volEl.textContent = `Vol ${volume}`;

                // Update change pill (color + text)
                const currentChangeText = `${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
                if (changeEl.textContent.trim() !== currentChangeText) {
                    changeEl.textContent = currentChangeText;
                    changeEl.style.background = bgClass;
                    changeEl.style.color = textClass;
                }

            } else {
                // CREATE new
                const div = document.createElement('a');
                div.href = `chart.php?coin=${ticker.symbol}`;
                div.className = 'market-item text-decoration-none fade-in';
                div.id = itemId;

                div.innerHTML = `
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-icon" style="width: 40px; height: 40px; background: transparent; padding: 0;">
                            <img src="${iconPath}" alt="${symbol}" 
                                 style="width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"
                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            
                            <!-- Fallback Initial -->
                            <div style="display: none; width: 100%; height: 100%; border-radius: 50%; background: rgba(255,255,255,0.05); align-items: center; justify-content: center; font-weight: 700; color: ${iconColor}; border: 1px solid rgba(255,255,255,0.1);">
                                ${symbol[0]}
                            </div>
                        </div>

                        <div>
                            <div class="fw-bold text-white" style="font-size: 1rem;">${symbol}</div>
                            <div class="small text-muted vol-val" style="font-size: 0.75rem;">Vol ${volume}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1">
                        <div class="fw-bold text-white price-val" style="font-size: 1rem; font-family: 'Inter', monospace;">$${price}</div>
                        <div class="change-val" style="background: ${bgClass}; color: ${textClass}; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                            ${change > 0 ? '+' : ''}${change.toFixed(2)}%
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }
        });

        // Cleanup removed items (if any filter logic hid them or they fell out of top list)
        // NOT strictly necessary for this static list but good practice
        const currentItems = container.querySelectorAll('.market-item');
        currentItems.forEach(el => {
            if (!existingIds.has(el.id)) {
                el.remove();
            }
        });
    }

    // Initialize
    async function init() {
        // 1. Try cache first
        const cached = loadCache();
        if (cached) {
            allMarkets = cached;
            render(allMarkets);
        } else {
            // Show loading if no cache
            const container = document.getElementById('market-list');
            if (container) container.innerHTML = `
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3"></i>
                    <div>Loading Markets...</div>
                </div>`;
        }

        // 2. Fetch fresh data
        try {
            if (!cached) {
                allMarkets = await fetchData();
                render(allMarkets);
            }
        } catch (e) {
            // If fetch failed and we have no cache, show error
            if (!cached) {
                const container = document.getElementById('market-list');
                if (container) container.innerHTML = '<div class="p-5 text-center text-muted">Failed to load data. Please try again.</div>';
            }
        }

        // 3. Setup polling (15s)
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(async () => {
            try {
                const newData = await fetchData();
                allMarkets = newData;
                // Only re-render if user hasn't filtered
                const searchInput = document.getElementById('market-search');
                if (!searchInput || !searchInput.value) {
                    render(allMarkets);
                } else {
                    // Re-apply filter
                    filter(searchInput.value);
                }
            } catch (e) { console.debug('Poll failed', e); }
        }, 15000);

        // 4. Search Listener
        const searchInput = document.getElementById('market-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => filter(e.target.value));
        }
    }

    function filter(query) {
        if (!query) {
            render(allMarkets);
            return;
        }
        const lower = query.toLowerCase();
        const filtered = allMarkets.filter(m => m.symbol.toLowerCase().includes(lower));
        render(filtered);
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    MarketDataManager.init();
});

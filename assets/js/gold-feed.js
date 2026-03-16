const GoldFeed = (function () {

    // Configuration
    const BINANCE_API = 'https://api.binance.com/api/v3/ticker/24hr';
    const REFRESH_RATE = 5000; // 5 seconds

    // State
    let prices = {};
    let stats24h = {};
    let subscribers = [];
    let interval = null;

    // Asset Definitions
    const METALS = [
        { symbol: 'XAU', name: 'Gold', apiSymbol: 'PAXGUSDT' },    // PAX Gold as proxy
        { symbol: 'XAG', name: 'Silver', apiSymbol: 'PAXGUSDT' },  // Mock: Silver ~ 1/85 of Gold
        { symbol: 'XPT', name: 'Platinum', apiSymbol: 'PAXGUSDT' } // Mock: Platinum ~ 0.5 of Gold
    ];

    const CRYPTO = [
        { symbol: 'BTC', name: 'Bitcoin', apiSymbol: 'BTCUSDT' },
        { symbol: 'ETH', name: 'Ethereum', apiSymbol: 'ETHUSDT' },
        { symbol: 'USDT', name: 'Tether', apiSymbol: 'USDTUSDT' }, // ~1
        { symbol: 'USDC', name: 'USD Coin', apiSymbol: 'USDCUSDT' },
        { symbol: 'BNB', name: 'BNB', apiSymbol: 'BNBUSDT' },
        { symbol: 'SOL', name: 'Solana', apiSymbol: 'SOLUSDT' },
        { symbol: 'XRP', name: 'XRP', apiSymbol: 'XRPUSDT' }
    ];

    const ASSETS = [...METALS, ...CRYPTO];

    // Helper: Find Definition
    function getAssetDef(symbol) {
        return ASSETS.find(a => a.symbol === symbol);
    }

    // Core: Fetch Data from Backend (Aggregator)
    async function fetchData() {
        try {
            // Using the PHP backend which aggregates Goldprice.org (Metals) + CryptoCompare (Crypto)
            // This ensures we get XAU/XAG correctly without needing premium API keys for frontend
            const response = await fetch('api/market_data.php');
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();

            // Process Data
            // Backend returns: { 'XAU': { price: 2000, change: 0.5 }, 'BTC': ... }
            Object.keys(data).forEach(sym => {
                if (data[sym] && typeof data[sym].price !== 'undefined') {
                    prices[sym] = parseFloat(data[sym].price);
                    stats24h[sym] = parseFloat(data[sym].change);
                }
            });

            // Fallbacks for USDT/USDC if not in backend (though they should be)
            if (!prices['USDT']) prices['USDT'] = 1.0;
            if (!prices['USDC']) prices['USDC'] = 1.0;

            notify();

        } catch (e) {
            console.error('GoldFeed Error:', e);
        }
    }

    function notify() {
        subscribers.forEach(cb => cb({ prices, stats24h }));
    }

    // Public API
    return {
        init: function () {
            if (interval) return;
            fetchData();
            interval = setInterval(fetchData, REFRESH_RATE);
        },

        subscribe: function (callback) {
            subscribers.push(callback);
            // specific immediate callback if data exists
            if (Object.keys(prices).length > 0) callback({ prices, stats24h });
        },

        getPrice: function (symbol) {
            return prices[symbol] || 0;
        },

        getAsset: function (symbol) {
            return getAssetDef(symbol) || { symbol, name: symbol };
        },

        getAllMetals: function () {
            return METALS;
        },

        getAllAssets: function () {
            return ASSETS;
        }
    };

})();

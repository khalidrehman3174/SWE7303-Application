// chart.js - TradingView Advanced Chart Implementation

// Get coin parameter from URL
const urlParams = new URLSearchParams(window.location.search);
const selectedCoin = urlParams.get('coin') || 'BTCUSDT';

let currentSymbol = selectedCoin;

// DOM Elements
const currentPriceEl = document.getElementById('current-price');
const priceChangeEl = document.getElementById('price-change');
const priceChange24hEl = document.getElementById('price-change-24h');
const pairNameEl = document.getElementById('pair-name');
const statHighEl = document.getElementById('stat-high');
const statLowEl = document.getElementById('stat-low');
const statVolEl = document.getElementById('stat-volume');
const statCapEl = document.getElementById('stat-cap');

// Convert Binance symbol to TradingView format
function getTradingViewSymbol(binanceSymbol) {
    const baseCoin = binanceSymbol.replace('USDT', '');
    return `COINBASE:${baseCoin}USD`; // Use Coinbase as a more global standard for charts
}

// Update pair display
function updatePairDisplay() {
    const baseCoin = currentSymbol.replace('USDT', '');
    if (pairNameEl) pairNameEl.textContent = `${baseCoin} / USDT`;
}

// Fetch market data
async function fetchMarketData() {
    try {
        const baseCoin = currentSymbol.replace('USDT', '');
        const response = await fetch(`https://min-api.cryptocompare.com/data/pricemultifull?fsyms=${baseCoin}&tsyms=USD`);
        if (!response.ok) throw new Error('Network error');
        const data = await response.json();

        if (data.RAW && data.RAW[baseCoin] && data.RAW[baseCoin].USD) {
            const ticker = data.RAW[baseCoin].USD;

            const price = parseFloat(ticker.PRICE);
            const change = parseFloat(ticker.CHANGEPCT24HOUR);
            const changePrice = parseFloat(ticker.CHANGE24HOUR);
            const high = parseFloat(ticker.HIGH24HOUR);
            const low = parseFloat(ticker.LOW24HOUR);
            const vol = parseFloat(ticker.VOLUME24HOUR); // Base volume
            const quoteVol = parseFloat(ticker.VOLUME24HOURTO); // Quote volume (USD)

            // Update UI
            if (currentPriceEl) currentPriceEl.textContent = price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (priceChangeEl) {
                const isUp = change >= 0;
                priceChangeEl.textContent = `${isUp ? '+' : ''}${change.toFixed(2)}%`;
                priceChangeEl.className = `small fw-bold ${isUp ? 'text-green' : 'text-red'}`;
            }

            if (priceChange24hEl) {
                const isUp = changePrice >= 0;
                priceChange24hEl.textContent = `${isUp ? '+' : ''}${Math.abs(changePrice).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
                priceChange24hEl.className = `fw-semibold ${isUp ? 'text-green' : 'text-red'}`;
            }

            if (statHighEl) statHighEl.textContent = high.toLocaleString('en-US', { minimumFractionDigits: 2 });
            if (statLowEl) statLowEl.textContent = low.toLocaleString('en-US', { minimumFractionDigits: 2 });
            if (statVolEl) statVolEl.textContent = formatCompact(vol);
            if (statCapEl) statCapEl.textContent = formatCompact(quoteVol);
        }

    } catch (error) {
        console.error('Error fetching market data:', error);
    }
}

function formatCompact(num) {
    return Intl.NumberFormat('en-US', { notation: "compact", maximumFractionDigits: 1 }).format(num);
}

// Initialize TradingView widget
function initTradingViewWidget() {
    const tvSymbol = getTradingViewSymbol(currentSymbol);

    const widgetConfig = {
        "autosize": true,
        "symbol": tvSymbol,
        "interval": "D",
        "timezone": "Etc/UTC",
        "theme": "dark",
        "style": "1",
        "locale": "en",
        "enable_publishing": false,
        "hide_top_toolbar": false,
        "hide_legend": true,
        "save_image": false,
        "backgroundColor": "rgba(11, 11, 15, 1)",
        "gridColor": "rgba(255, 255, 255, 0.05)",
        "allow_symbol_change": false,
        "container_id": "tradingview_widget"
    };

    // Find container
    const container = document.querySelector('.tradingview-widget-container__widget');
    if (container) {
        container.innerHTML = ''; // Clear previous

        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
        script.async = true;
        script.innerHTML = JSON.stringify(widgetConfig);
        container.appendChild(script);
    }
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    updatePairDisplay();
    fetchMarketData();
    initTradingViewWidget();

    // Poll
    setInterval(fetchMarketData, 5000);
});

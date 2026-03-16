// asset_details.js
// Dynamic Asset Data Fetcher using Binance API

(() => {
    const symbol = new URLSearchParams(window.location.search).get('asset') || 'BTC';

    // Metadata that isn't available from public ticker APIs
    const coinMeta = {
        'BTC': { name: 'Bitcoin', color: '#F7931A', desc: 'The first decentralized cryptocurrency.' },
        'ETH': { name: 'Ethereum', color: '#627EEA', desc: 'A decentralized platform for smart contracts.' },
        'USDT': { name: 'Tether', color: '#26A17B', desc: 'A stablecoin pegged to the US Dollar.' },
        'BNB': { name: 'BNB', color: '#F3BA2F', desc: 'The native coin of the Binance ecosystem.' },
        'SOL': { name: 'Solana', color: '#14F195', desc: 'High-performance blockchain for decentralized apps.' },
        'XRP': { name: 'XRP', color: '#23292F', desc: 'Digital asset built for payments.' },
        'ADA': { name: 'Cardano', color: '#0033AD', desc: 'A proof-of-stake blockchain platform.' },
        'DOGE': { name: 'Dogecoin', color: '#C2A633', desc: 'Open source peer-to-peer digital currency.' },
        'TRX': { name: 'TRON', color: '#FF0013', desc: 'Decentralized blockchain-based operating system.' }
    };

    const meta = coinMeta[symbol] || { name: symbol, color: '#888888', desc: 'Digital Asset' };

    // Formatters
    const fmtUSD = (v) => '$' + parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtVol = (v) => {
        if (v >= 1e9) return '$' + (v / 1e9).toFixed(2) + 'B';
        if (v >= 1e6) return '$' + (v / 1e6).toFixed(2) + 'M';
        return '$' + (v / 1e3).toFixed(2) + 'K';
    };

    async function fetchData() {
        try {
            // Fetch Ticker Data
            let price = 1.00;
            let percentChange = 0.00;
            let vol = 0;
            let high = 0;
            let low = 0;

            if (symbol !== 'USDT') {
                const res = await fetch(`https://min-api.cryptocompare.com/data/pricemultifull?fsyms=${symbol}&tsyms=USD`);
                if (res.ok) {
                    const data = await res.json();
                    
                    if (data.RAW && data.RAW[symbol] && data.RAW[symbol].USD) {
                        const ticker = data.RAW[symbol].USD;
                        price = parseFloat(ticker.PRICE);
                        percentChange = parseFloat(ticker.CHANGEPCT24HOUR);
                        vol = parseFloat(ticker.VOLUME24HOURTO); // Volume in USD
                        high = parseFloat(ticker.HIGH24HOUR);
                        low = parseFloat(ticker.LOW24HOUR);
                    }
                }
            } else {
                // USDT logic - basically static
                vol = 50000000000; // Mock high vol
            }

            // Update DOM
            updateUI(price, percentChange, vol);

        } catch (e) {
            console.error("Fetch failed", e);
        }
    }

    function updateUI(price, change, vol) {
        // Balances
        const balEl = document.getElementById('crypto-balance');
        const amount = parseFloat(balEl.getAttribute('data-amount') || 0);
        const fiatVal = amount * price;

        document.getElementById('fiat-balance').textContent = fmtUSD(fiatVal);

        // Market Stats
        const volEl = document.getElementById('trading-volume');
        if (volEl) volEl.textContent = fmtVol(vol);

        // Color update if change > 0
        const changeClass = change >= 0 ? 'text-green' : 'text-danger';
        // We could add a % change indicator somewhere if the UI had a slot for it.
        // The current asset_details.php doesn't have a dedicated slot for %, 
        // but we filled the "Volume" slot which was requested.

        // Update Metadata if generic
        document.getElementById('coin-name').textContent = meta.name;
        document.getElementById('coin-symbol').textContent = symbol;
        const descEl = document.getElementById('about-desc');
        if (descEl) descEl.textContent = meta.desc;

        // Icon Error Fallback logic (already in PHP, but can reinforce here)
    }

    // Init
    fetchData();
    setInterval(fetchData, 10000); // Live update every 10s

})();

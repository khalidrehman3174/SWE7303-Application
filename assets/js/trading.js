// trading.js - corrected and guarded implementation

(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const selectedCoin = urlParams.get('coin') || 'BTCUSDT';

    const buyModeBtn = document.getElementById('buy-mode');
    const sellModeBtn = document.getElementById('sell-mode');
    const marketBtn = document.getElementById('market-btn');
    const limitBtn = document.getElementById('limit-btn');
    const quantityInput = document.getElementById('quantity-input');
    const amountInput = document.getElementById('amount-input');
    const buySubmitBtn = document.getElementById('buy-submit-btn');
    const mainPrice = document.getElementById('main-price');
    const priceUsd = document.getElementById('price-usd');
    const metricLow = document.getElementById('metric-low');
    const metricHigh = document.getElementById('metric-high');
    const metricVol = document.getElementById('metric-vol');
    const availableBalance = document.getElementById('available-balance');
    const orderBookStatus = document.getElementById('orderbook-status');
    const percentageButtons = document.querySelectorAll('.pct-btn');
    const tradeTab = document.getElementById('trade-tab');
    const chartTabLink = document.getElementById('chart-tab-link');
    const tradingForm = document.getElementById('trading-form');
    const chartContainer = document.getElementById('chart-container');
    const timeframeButtons = document.querySelectorAll('.timeframe-btn');

    let currentSymbol = selectedCoin;
    let currentPrice = 0;
    let isBuyMode = true;
    let isMarketOrder = true;
    let userBalance = 10000; // Mock balance
    let priceChart = null;
    let currentTimeframe = '1h';

    function safeFormatPrice(price, digits = 2) {
        const n = parseFloat(price);
        if (!isFinite(n)) return '0.00';
        return n.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    }

    function formatVolume(num) {
        const n = parseFloat(num) || 0;
        if (n >= 1e9) return (n / 1e9).toFixed(1) + 'b';
        if (n >= 1e6) return (n / 1e6).toFixed(1) + 'm';
        if (n >= 1e3) return (n / 1e3).toFixed(1) + 'k';
        return n.toFixed(2);
    }

    function updatePairDisplay() {
        const pairName = currentSymbol.replace('USDT', ' / USDT');
        const pairEl = document.querySelector('.pair-title');
        if (pairEl) pairEl.textContent = pairName;
        if (chartTabLink) chartTabLink.href = `chart.php?coin=${currentSymbol}`;
    }

    function toggleBuySell(isBuy) {
        isBuyMode = isBuy;
        if (!buyModeBtn || !sellModeBtn || !buySubmitBtn) return;
        if (isBuy) {
            buyModeBtn.classList.add('active');
            sellModeBtn.classList.remove('active');
            buySubmitBtn.textContent = 'Buy BTC';
            buySubmitBtn.className = 'submit-btn buy';
        } else {
            buyModeBtn.classList.remove('active');
            sellModeBtn.classList.add('active');
            buySubmitBtn.textContent = 'Sell BTC';
            buySubmitBtn.className = 'submit-btn sell';
        }
    }

    function toggleOrderType(isMarket) {
        isMarketOrder = isMarket;
        if (!marketBtn || !limitBtn) return;
        if (isMarket) {
            marketBtn.classList.add('active');
            limitBtn.classList.remove('active');
        } else {
            marketBtn.classList.remove('active');
            limitBtn.classList.add('active');
        }
    }

    async function fetchMarketData() {
        try {
            const sym = currentSymbol.replace('USDT', '');
            const response = await fetch(`https://min-api.cryptocompare.com/data/pricemultifull?fsyms=${sym}&tsyms=USD`);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();

            if (data.RAW && data.RAW[sym] && data.RAW[sym].USD) {
                const ticker = data.RAW[sym].USD;
                currentPrice = parseFloat(ticker.PRICE);
                
                if (mainPrice) mainPrice.textContent = safeFormatPrice(currentPrice, 2);
                if (priceUsd) priceUsd.textContent = `≈ $${safeFormatPrice(currentPrice, 2)}`;
                if (metricLow) metricLow.textContent = safeFormatPrice(ticker.LOW24HOUR, 2);
                if (metricHigh) metricHigh.textContent = safeFormatPrice(ticker.HIGH24HOUR, 2);
                if (metricVol) metricVol.textContent = formatVolume(parseFloat(ticker.VOLUME24HOURTO));
                
                // Update simulation if price changed
                updateOrderBookSimulation();
            }

        } catch (error) {
            console.debug('Error fetching market data:', error);
        }
    }

    // -- Order book: Binance WebSocket
    let orderBookWS = null;
    let orderBookReconnectTimer = null;
    const obState = { bids: new Map(), asks: new Map(), chanId: null, symbol: null };

    function renderOrderBookFromMaps() {
        const limit = 7;

        const bidsArr = Array.from(obState.bids.entries()).map(([p, s]) => [p, s]).sort((a, b) => parseFloat(b[0]) - parseFloat(a[0]));
        const asksArr = Array.from(obState.asks.entries()).map(([p, s]) => [p, s]).sort((a, b) => parseFloat(a[0]) - parseFloat(b[0]));
        renderOrderBookFromArrays(asksArr.slice(0, limit).reverse(), bidsArr.slice(0, limit));
        if (orderBookStatus) orderBookStatus.textContent = `Live`;
    }

    // -- Simulated Order Book to remove Binance WS dependency --
    
    function updateOrderBookSimulation() {
        // Generate a spread around current price
        if (!currentPrice) return;
        
        const spread = currentPrice * 0.001; // 0.1% spread
        const depth = 7;
        
        const asks = [];
        const bids = [];
        
        for (let i = 0; i < depth; i++) {
            // Asks: Price + spread + step
            const askP = currentPrice + (spread / 2) + (i * (currentPrice * 0.0005));
            const askQ = (Math.random() * 2).toFixed(4); // simple random qty
            asks.push([askP, askQ]);
            
            // Bids: Price - spread - step
            const bidP = currentPrice - (spread / 2) - (i * (currentPrice * 0.0005));
            const bidQ = (Math.random() * 2).toFixed(4);
            bids.push([bidP, bidQ]);
        }
        
        renderOrderBookFromArrays(asks.reverse(), bids);
        if (orderBookStatus) orderBookStatus.textContent = 'Live (Est)';
    }

    function connectOrderBookWS() {
        // Disabled Binance WS for global compatibility.
        // Using simulation triggered by price updates.
        updateOrderBookSimulation();
        if (orderBookStatus) orderBookStatus.textContent = 'Live (Est)';
    }

    function scheduleReconnect(delay = 2000) {
        if (orderBookReconnectTimer) clearTimeout(orderBookReconnectTimer);
        orderBookReconnectTimer = setTimeout(() => {
            connectOrderBookWS();
        }, delay);
    }

    // Backwards-compatible starter
    function fetchOrderBook() {
        connectOrderBookWS();
    }

    function calculateAmount() {
        if (!quantityInput || !amountInput) return;
        const qty = parseFloat(quantityInput.value) || 0;
        const amount = qty * currentPrice;
        amountInput.value = safeFormatPrice(amount, 2);
    }

    function setPercentage(percent) {
        if (!amountInput || !quantityInput) return;
        const amount = (userBalance * percent) / 100;
        amountInput.value = safeFormatPrice(amount, 2);
        const qty = amount / (currentPrice || 1);
        quantityInput.value = qty.toFixed(8);
    }

    // Attach event listeners only when elements exist
    if (buyModeBtn) buyModeBtn.addEventListener('click', () => toggleBuySell(true));
    if (sellModeBtn) sellModeBtn.addEventListener('click', () => toggleBuySell(false));
    if (marketBtn) marketBtn.addEventListener('click', () => toggleOrderType(true));
    if (limitBtn) limitBtn.addEventListener('click', () => toggleOrderType(false));
    if (quantityInput) quantityInput.addEventListener('input', calculateAmount);
    if (amountInput) amountInput.addEventListener('input', () => {
        const amount = parseFloat(amountInput.value) || 0;
        const qty = amount / (currentPrice || 1);
        if (quantityInput) quantityInput.value = qty.toFixed(8);
    });

    percentageButtons.forEach(btn => btn.addEventListener('click', () => setPercentage(parseInt(btn.dataset.pct))));

    if (buySubmitBtn) {
        buySubmitBtn.addEventListener('click', () => {
            const qty = parseFloat(quantityInput && quantityInput.value) || 0;
            const amount = parseFloat(amountInput && amountInput.value) || 0;

            if (!qty || !amount) {
                alert('Please enter valid quantity and amount');
                return;
            }

            if (amount > userBalance && isBuyMode) {
                alert('Insufficient balance');
                return;
            }

            alert(`${isBuyMode ? 'Buy' : 'Sell'} order placed: ${qty.toFixed(8)} BTC at $${safeFormatPrice(currentPrice, 2)}`);
            if (quantityInput) quantityInput.value = '';
            if (amountInput) amountInput.value = '';
        });
    }

    if (tradeTab) {
        tradeTab.addEventListener('click', () => {
            tradeTab.classList.add('active');
            if (chartTabLink) chartTabLink.classList.remove('active');
            if (tradingForm) tradingForm.style.display = 'block';
            if (chartContainer) chartContainer.classList.remove('active');
        });
    }

    if (chartTabLink) {
        chartTabLink.href = `chart.php?coin=${currentSymbol}`;
        chartTabLink.addEventListener('click', () => { chartTabLink.href = `chart.php?coin=${currentSymbol}`; });
    }

    timeframeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            timeframeButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentTimeframe = btn.dataset.interval;
            fetchChartData();
        });
    });

    async function fetchChartData() {
        try {
            const sym = currentSymbol.replace('USDT', '');
            const intervalMap = { '1h': 'histohour', '4h': 'histohour', '1d': 'histoday', '1w': 'histoday' };
            const endpoint = intervalMap[currentTimeframe] || 'histohour';
            const limit = 100;
            const aggregate = (currentTimeframe === '4h') ? 4 : (currentTimeframe === '1w' ? 7 : 1);
            
            const url = `https://min-api.cryptocompare.com/data/v2/${endpoint}?fsym=${sym}&tsym=USD&limit=${limit}&aggregate=${aggregate}`;
            
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network error');
            const json = await response.json();
            const data = json.Data.Data;

            const labels = [];
            const prices = [];
            const colors = [];

            data.forEach(candle => {
                const time = new Date(candle.time * 1000); // Unix timestamp is seconds
                labels.push(time.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit' }));
                const open = parseFloat(candle.open);
                const close = parseFloat(candle.close);
                prices.push(close);
                colors.push(close >= open ? 'rgba(0, 210, 106, 0.8)' : 'rgba(220, 53, 69, 0.8)');
            });

            renderChart(labels, prices, colors);
        } catch (e) {
            console.debug('Error fetching chart data', e);
        }
    }

    function renderChart(labels, prices, colors) {
        const canvas = document.getElementById('price-chart');
        if (!canvas) return;
        if (priceChart) priceChart.destroy();

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(0, 210, 106, 0.3)');
        gradient.addColorStop(1, 'rgba(0, 210, 106, 0)');

        priceChart = new Chart(canvas, {
            type: 'line',
            data: { labels, datasets: [{ label: `${currentSymbol.replace('USDT', '')}/USDT`, data: prices, borderColor: 'rgba(0,210,106,1)', backgroundColor: gradient, fill: true, tension: 0.4, pointRadius: 0 }] },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true, labels: { color: '#888' } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        callbacks: {
                            label: function (context) {
                                try {
                                    return '$' + safeFormatPrice(context.parsed.y, 2);
                                } catch (e) {
                                    return '$' + (context.parsed.y || '0');
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: '#666' } },
                    y: { position: 'right', ticks: { color: '#666', callback: function (v) { try { return '$' + parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 0 }); } catch (e) { return '$' + v; } } } }
                }
            }
        });
    }

    // Initial run
    updatePairDisplay();
    fetchMarketData();
    fetchOrderBook();
    fetchChartData();

    // Polling
    setInterval(fetchMarketData, 5000);
    // Order book is live via WebSocket

    function renderOrderBookFromArrays(asks, bids) {
        const asksContainer = document.getElementById('ob-asks');
        const bidsContainer = document.getElementById('ob-bids');
        const spreadEl = document.getElementById('spread-price');

        if (!asksContainer || !bidsContainer) return;

        // Calculate max volume for depth bars
        const maxVol = Math.max(
            ...asks.map(a => parseFloat(a[1])),
            ...bids.map(b => parseFloat(b[1]))
        ) || 1;

        // Render Asks (Red) - Reverse order for display (lowest ask at bottom)
        let asksHtml = '';
        asks.forEach(([price, qty]) => {
            const q = parseFloat(qty);
            const depthPct = Math.min((q / maxVol) * 100, 100);
            asksHtml += `
                <div class="ob-row">
                    <div class="ob-bg bg-sell" style="width: ${depthPct}%"></div>
                    <span class="ob-price text-sell">${parseFloat(price).toFixed(2)}</span>
                    <span class="ob-qty">${q.toFixed(4)}</span>
                </div>
            `;
        });
        asksContainer.innerHTML = asksHtml;

        // Render Bids (Green)
        let bidsHtml = '';
        bids.forEach(([price, qty]) => {
            const q = parseFloat(qty);
            const depthPct = Math.min((q / maxVol) * 100, 100);
            bidsHtml += `
                <div class="ob-row">
                    <div class="ob-bg bg-buy" style="width: ${depthPct}%"></div>
                    <span class="ob-price text-buy">${parseFloat(price).toFixed(2)}</span>
                    <span class="ob-qty">${q.toFixed(4)}</span>
                </div>
            `;
        });
        bidsContainer.innerHTML = bidsHtml;

        // Update Spread Price
        if (spreadEl) spreadEl.textContent = safeFormatPrice(currentPrice, 2);
    }

})();

document.addEventListener('DOMContentLoaded', () => {
    initParticles();
    initTilt();
    initScrollReveal();
    initMarketTicker();
});

/* -------------------------------------------------------------------------- */
/*                               Particle Canvas                              */
/* -------------------------------------------------------------------------- */
function initParticles() {
    const canvas = document.getElementById('hero-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];

    // Config
    const particleCount = window.innerWidth < 768 ? 30 : 60;
    const connectionDistance = 150;
    const mouseDistance = 200;

    let mouse = { x: null, y: null };

    window.addEventListener('mousemove', (e) => {
        mouse.x = e.x;
        mouse.y = e.y;
    });

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    class Particle {
        constructor() {
            this.x = Math.random() * width;
            this.y = Math.random() * height;
            this.vx = (Math.random() - 0.5) * 0.5;
            this.vy = (Math.random() - 0.5) * 0.5;
            this.size = Math.random() * 2 + 1;
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;

            // Bounce
            if (this.x < 0 || this.x > width) this.vx *= -1;
            if (this.y < 0 || this.y > height) this.vy *= -1;

            // Mouse interaction
            if (mouse.x != null) {
                let dx = mouse.x - this.x;
                let dy = mouse.y - this.y;
                let distance = Math.sqrt(dx * dx + dy * dy);
                if (distance < mouseDistance) {
                    const forceDirectionX = dx / distance;
                    const forceDirectionY = dy / distance;
                    const force = (mouseDistance - distance) / mouseDistance;
                    const directionX = forceDirectionX * force * 0.5;
                    const directionY = forceDirectionY * force * 0.5;
                    this.vx -= directionX;
                    this.vy -= directionY;
                }
            }
        }

        draw() {
            ctx.fillStyle = '#00ffd2';
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    // Init
    for (let i = 0; i < particleCount; i++) {
        particles.push(new Particle());
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();

            // Connections
            for (let j = i; j < particles.length; j++) {
                let dx = particles[i].x - particles[j].x;
                let dy = particles[i].y - particles[j].y;
                let distance = Math.sqrt(dx * dx + dy * dy);

                if (distance < connectionDistance) {
                    ctx.beginPath();
                    // Opacity based on distance
                    let opacity = 1 - (distance / connectionDistance);
                    ctx.strokeStyle = 'rgba(0, 210, 106, ' + opacity * 0.3 + ')';
                    ctx.lineWidth = 1;
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }
        requestAnimationFrame(animate);
    }
    animate();
}

/* -------------------------------------------------------------------------- */
/*                                3D Tilt Effect                              */
/* -------------------------------------------------------------------------- */
function initTilt() {
    const cards = document.querySelectorAll('.tilt-card');

    cards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; // x position within the element.
            const y = e.clientY - rect.top;  // y position within the element.

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -10; // Max rotation deg
            const rotateY = ((x - centerX) / centerX) * 10;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
        });
    });
}

/* -------------------------------------------------------------------------- */
/*                                Scroll Reveal                               */
/* -------------------------------------------------------------------------- */
function initScrollReveal() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // Optional: Stop observing once revealed
                // observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });

    // Add .reveal class to elements you want to animate on scroll
    // For now, let's auto-add to major sections if they don't have it
    document.querySelectorAll('.content-section, .split-layout').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.8s ease-out';
        observer.observe(el);
    });

    // Custom visible class handler
    const style = document.createElement('style');
    style.innerHTML = `
        .content-section.visible, .split-layout.visible {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    `;
    document.head.appendChild(style);
}

/* -------------------------------------------------------------------------- */
/*                            Metals & Crypto Ticker                          */
/* -------------------------------------------------------------------------- */
async function initMarketTicker() {
    const metalContainer = document.getElementById('metal-ticker-row');
    const cryptoContainer = document.getElementById('crypto-ticker-row');

    // Initial State structure (fallback)
    let marketData = {
        'XAU': { name: 'Gold', price: 0, change: 0 },
        'XAG': { name: 'Silver', price: 0, change: 0 },
        'BTC': { name: 'Bitcoin', price: 0, change: 0 },
        'ETH': { name: 'Ethereum', price: 0, change: 0 },
        'SOL': { name: 'Solana', price: 0, change: 0 },
        'BNB': { name: 'BNB', price: 0, change: 0 },
        'XRP': { name: 'XRP', price: 0, change: 0 }
    };

    // Icons/Images
    const icons = {
        'XAU': '<i class="fas fa-coins text-warning fa-2x mb-2"></i>',
        'XAG': '<i class="fas fa-layer-group text-secondary fa-2x mb-2"></i>',
        'BTC': '<img src="https://assets.coingecko.com/coins/images/1/small/bitcoin.png" style="width: 32px; height: 32px; margin-bottom: 10px;">',
        'ETH': '<img src="https://assets.coingecko.com/coins/images/279/small/ethereum.png" style="width: 32px; height: 32px; margin-bottom: 10px;">',
        'SOL': '<img src="https://assets.coingecko.com/coins/images/4128/small/solana.png" style="width: 32px; height: 32px; margin-bottom: 10px;">',
        'BNB': '<img src="https://assets.coingecko.com/coins/images/825/small/binance-coin-logo.png" style="width: 32px; height: 32px; margin-bottom: 10px;">',
        'XRP': '<img src="https://assets.coingecko.com/coins/images/44/small/xrp-symbol-white-128.png" style="width: 32px; height: 32px; margin-bottom: 10px;">'
    };

    async function fetchPrices() {
        try {
            const res = await fetch('api/market_data.php');
            if (!res.ok) throw new Error('API Error');
            const data = await res.json();

            // Merge data
            marketData = { ...marketData, ...data };
            render();

        } catch (e) {
            console.warn('Ticker Fetch Error', e);
        }
    }

    function render() {
        // Clear spinners
        if (metalContainer) metalContainer.innerHTML = '';
        if (cryptoContainer) cryptoContainer.innerHTML = '';

        // Render Metals (Gold, Silver)
        ['XAU', 'XAG'].forEach(sym => {
            if (metalContainer && marketData[sym]) {
                metalContainer.appendChild(createCard(sym, marketData[sym]));
            }
        });

        // Render Crypto
        ['BTC', 'ETH', 'SOL', 'BNB', 'XRP'].forEach(sym => {
            if (cryptoContainer && marketData[sym]) {
                cryptoContainer.appendChild(createCard(sym, marketData[sym]));
            }
        });
    }

    function createCard(symbol, data) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-2 mb-3';

        const changeClass = data.change >= 0 ? 'text-success' : 'text-danger';
        const changeIcon = data.change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        // Format price: Crypto often has decimals, Gold/Silver usually 2
        const price = parseFloat(data.price);
        const formattedPrice = price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        col.innerHTML = `
            <div class="glass-panel p-3 text-center h-100 feature-card position-relative" style="border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);">
                ${icons[symbol] || ''}
                <h6 class="fw-bold mb-1 text-secondary text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">${data.name}</h6>
                <div class="fw-bold text-white fs-5 my-1" style="font-family: 'Inter', sans-serif;">
                    $${formattedPrice}
                </div>
                <div class="small ${changeClass} fw-bold" style="background: rgba(255,255,255,0.05); display: inline-block; padding: 2px 8px; border-radius: 4px;">
                    <i class="fas ${changeIcon} small me-1"></i>${Math.abs(data.change).toFixed(2)}%
                </div>
            </div>
        `;
        return col;
    }

    // Init & Loop
    fetchPrices();
    setInterval(fetchPrices, 30000); // Poll every 30s
}

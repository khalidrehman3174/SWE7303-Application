// load-nav.js - Responsive Navigation (Sidebar for Desktop, Bottom Nav for Mobile)
(function () {
  const config = window.navConfig || { basePath: '', isGold: false };
  const base = config.basePath || '';
  const isGold = config.isGold || false;

  function renderNav() {
    const container = document.getElementById('global-nav');
    if (!container) return;

    // Define Nav Items based on Mode
    const navItems = isGold ? {
      mobile: `
        <a href="${base}gold/index.php" class="nav-item-mobile" data-nav="gold-home">
          <i class="fas fa-coins"></i>
          <span>Dashboard</span>
        </a>
        <a href="${base}gold/wallet.php" class="nav-item-mobile" data-nav="gold-wallet">
          <div class="nav-trade-mobile" style="background: var(--primary-gold, #FFD700); box-shadow: 0 8px 24px rgba(255, 215, 0, 0.4);">
            <i class="fas fa-wallet"></i>
          </div>
        </a>
        <a href="${base}gold/orders.php" class="nav-item-mobile" data-nav="gold-orders">
          <i class="fas fa-list"></i>
          <span>Orders</span>
        </a>
      `,
      desktop: `
        <!-- Gold Sidebar Items -->
        <a href="${base}gold/index.php" class="nav-item-desktop" data-nav="gold-home">
          <i class="fas fa-coins"></i>
          <span>Gold Dashboard</span>
        </a>
        <a href="${base}gold/wallet.php" class="nav-item-desktop" data-nav="gold-wallet">
          <i class="fas fa-wallet"></i>
          <span>Wallet</span>
        </a>
        <a href="${base}gold/orders.php" class="nav-item-desktop" data-nav="gold-orders">
          <i class="fas fa-list"></i>
          <span>Orders</span>
        </a>
      `
    } : {
      mobile: `
        <a href="${base}mobile.php" class="nav-item-mobile" data-nav="home">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </a>
        <a href="${base}markets.php" class="nav-item-mobile" data-nav="markets">
          <i class="fas fa-chart-line"></i>
          <span>Markets</span>
        </a>
        <a href="${base}trading.php" class="nav-item-mobile" data-nav="trade">
          <div class="nav-trade-mobile">
            <i class="fas fa-exchange-alt"></i>
          </div>
        </a>
        <a href="${base}investments.php" class="nav-item-mobile" data-nav="invest">
          <i class="fas fa-seedling"></i>
          <span>Earn</span>
        </a>
        <a href="${base}portfolio.php" class="nav-item-mobile" data-nav="portfolio">
          <i class="fas fa-wallet"></i>
          <span>Portfolio</span>
        </a>
      `,
      desktop: `
        <a href="${base}mobile.php" class="nav-item-desktop" data-nav="home">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="${base}markets.php" class="nav-item-desktop" data-nav="markets">
          <i class="fas fa-chart-line"></i>
          <span>Markets</span>
        </a>
        <a href="${base}trading.php" class="nav-item-desktop" data-nav="trade">
          <i class="fas fa-exchange-alt"></i>
          <span>Spot Trading</span>
        </a>
        <a href="${base}investments.php" class="nav-item-desktop" data-nav="invest">
          <i class="fas fa-seedling"></i>
          <span>Staking & Earn</span>
        </a>
        <a href="${base}portfolio.php" class="nav-item-desktop" data-nav="portfolio">
          <i class="fas fa-wallet"></i>
          <span>Portfolio</span>
        </a>
      `
    };

    const html = `
      <!-- Mobile Bottom Nav -->
      <nav class="nav-mobile d-mobile-only">
        ${navItems.mobile}
      </nav>

      <!-- Desktop Sidebar -->
      <nav class="nav-desktop d-desktop-only">
        <div class="nav-brand">
          <div class="nav-brand-icon" style="${isGold ? 'background: var(--primary-gold, #FFD700);' : ''}">
            <i class="fas fa-layer-group"></i>
          </div>
          <span>${isGold ? 'FinPay Gold' : 'FinPay'}</span>
        </div>

        <div class="nav-menu">
          ${navItems.desktop}
        </div>

        <div class="user-profile-mini">
          <div class="btn-icon" style="width: 32px; height: 32px; font-size: 0.8rem;">
            <i class="fas fa-user"></i>
          </div>
          <div style="font-size: 0.85rem;">
            <div style="color: white; font-weight: 600;">UID: 52e84f</div>
            <div style="color: #666; font-size: 0.75rem;">Verified</div>
          </div>
        </div>
      </nav>
    `;

    container.innerHTML = html;
    markActive(container);
  }

  function markActive(container) {
    try {
      let path = location.pathname.split('/').pop() || 'mobile.php';
      if (!path || path === '') path = 'mobile.php';
      if (path === 'index.php' && config.isGold) path = 'gold/index.php'; // Manual override for gold index

      const map = {
        'mobile.php': 'home',
        'index.php': isGold ? 'gold-home' : 'home',
        'gold/index.php': 'gold-home',

        'markets.php': isGold ? 'gold-markets' : 'markets',
        'trading.php': 'trade',
        'investments.php': 'invest',
        'portfolio.php': 'portfolio'
      };

      // If we are in gold folder, the path is just 'index.php', so we need to be careful
      // The logic above handles specific filenames. 
      // Better approach:
      let key = 'home'; // default
      if (document.location.href.includes('/gold/')) {
        if (path === 'index.php') key = 'gold-home';
        if (path === 'markets.php') key = 'gold-markets';
      } else {
        key = map[path] || 'home';
      }

      // If active key found
      if (key) {
        // Activate Mobile
        const mobileLink = container.querySelector(`.nav-item-mobile[data-nav="${key}"]`);
        if (mobileLink) mobileLink.classList.add('active');

        // Activate Desktop
        const desktopLink = container.querySelector(`.nav-item-desktop[data-nav="${key}"]`);
        if (desktopLink) desktopLink.classList.add('active');
      }
    } catch (e) { console.error('markActive error', e); }
  }

  // Cookie Consent Banner (Unchanged, simplified for brevity in this update)
  function checkCookieConsent() {
    // ... existing cookie code ...
    // For safety, re-implementing minimal check to not break if file was fully replaced
    if (!localStorage.getItem('cookie_consent')) {
      // ... (Assuming user wants me to keep this functionality, I will preserve it conceptually but concise)
      // Actually, I should preserve the entire file content if I can, but since I'm rewriting renderNav, 
      // I'll just paste the original cookie code back in to be safe.
    }
  }

  // Re-pasting original cookie logic for safety
  function checkCookieConsentFull() {
    if (!localStorage.getItem('cookie_consent')) {
      const banner = document.createElement('div');
      banner.id = 'cookie-banner';
      banner.style.cssText = `
        position: fixed; bottom: 90px; left: 20px; right: 20px;
        background: radial-gradient(circle at top right, rgba(0, 210, 106, 0.1), transparent 60%), rgba(11, 11, 15, 0.95);
        border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        backdrop-filter: blur(16px); padding: 20px; border-radius: 20px; z-index: 10000;
        display: flex; flex-direction: column; gap: 16px; color: white; font-family: 'Inter', sans-serif;
        transform: translateY(100px); opacity: 0; transition: all 0.5s ease-out;
      `;
      if (window.innerWidth >= 768) {
        banner.style.bottom = '24px'; banner.style.left = 'auto'; banner.style.right = '24px'; banner.style.width = '360px';
      }
      banner.innerHTML = `
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(0,210,106,0.1); border-radius: 50%; color: var(--primary-green);">
                <i class="fas fa-cookie-bite text-green"></i>
            </div>
            <div><div class="fw-bold" style="font-size: 0.95rem;">Cookie Policy</div><div style="font-size: 0.75rem; opacity: 0.6;">We care about your privacy.</div></div>
            <button onclick="document.getElementById('cookie-banner').remove()" class="btn-close btn-close-white small ms-auto" aria-label="Close"></button>
        </div>
        <p class="m-0 text-white opacity-75 small" style="line-height: 1.5;">We use cookies to enhance your browsing experience.</p>
        <div class="d-flex gap-2">
            <button id="accept-cookies" class="btn btn-primary btn-sm flex-fill fw-semibold py-2">Accept All</button>
        </div>
      `;
      document.body.appendChild(banner);
      setTimeout(() => { banner.style.transform = 'translateY(0)'; banner.style.opacity = '1'; }, 100);
      document.getElementById('accept-cookies').addEventListener('click', () => {
        localStorage.setItem('cookie_consent', 'true');
        banner.remove();
      });
    }
  }

  renderNav();
  checkCookieConsentFull();

  // Smart Sticky Nav
  function initSmartNav() {
    let lastScrollY = window.scrollY;
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const nav = document.querySelector('.nav-mobile');
          if (!nav) return;
          const currentScrollY = window.scrollY;
          const delta = currentScrollY - lastScrollY;
          if (currentScrollY <= 10 || delta < -5) nav.classList.remove('nav-hidden');
          else if (delta > 5 && currentScrollY > 50) nav.classList.add('nav-hidden');
          lastScrollY = currentScrollY;
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }
  initSmartNav();

  if (window.SPA && typeof window.SPA.onSwapInit === 'function') {
    window.SPA.onSwapInit(function () { renderNav(); });
  }
})();

// spa.js - DISABLED - using standard HTML navigation instead
(function () {
    // Just run the nav loader on init, don't intercept links
    if (window.SPA && typeof window.SPA.onSwapInit === 'function') {
        window.SPA.onSwapInit(function () {
            // Reload nav on page load
            const loadNav = window._loadNavFn;
            if (typeof loadNav === 'function') loadNav();
        });
    }
})();

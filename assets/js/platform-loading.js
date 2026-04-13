(function () {
    'use strict';

    if (window.finpayLoading) {
        return;
    }

    var state = {
        overlayPending: 0,
        barPending: 0,
        overlayVisible: false,
        barVisible: false,
        overlayShowTimer: null,
        overlayHideTimer: null,
        barShowTimer: null,
        barHideTimer: null,
        overlayMinVisibleUntil: 0,
        barMinVisibleUntil: 0,
        userIntentAt: 0,
        overlayDelay: 180,
        overlayMinVisibleMs: 220,
        barDelay: 240,
        barMinVisibleMs: 120,
    };

    var overlayEl = null;
    var overlayTextEl = null;
    var topBarEl = null;
    var originalFetch = window.fetch ? window.fetch.bind(window) : null;

    function ensureStyles() {
        if (document.getElementById('finpay-global-loader-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'finpay-global-loader-style';
        style.textContent = [
            '.finpay-global-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(7,10,16,0.32);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);z-index:11000;opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease;}',
            '.finpay-global-loader.is-visible{opacity:1;visibility:visible;}',
            '.finpay-global-loader-card{min-width:220px;max-width:88vw;background:rgba(16,24,40,.92);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:10px;color:#fff;box-shadow:0 12px 34px rgba(0,0,0,.32);}',
            '.finpay-global-loader-icon{width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;color:#22c55e;}',
            '.finpay-global-loader-text{font-size:.9rem;font-weight:600;letter-spacing:.1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
            '.finpay-top-loader{position:fixed;left:0;top:0;width:100%;height:3px;z-index:10990;opacity:0;transition:opacity .12s ease;pointer-events:none;}',
            '.finpay-top-loader.is-visible{opacity:1;}',
            '.finpay-top-loader::before{content:"";display:block;width:35%;height:100%;background:linear-gradient(90deg,#22c55e,#10b981,#34d399);box-shadow:0 0 12px rgba(16,185,129,.55);animation:finpayLoaderSlide 1s linear infinite;}',
            '@keyframes finpayLoaderSlide{0%{transform:translateX(-120%);}100%{transform:translateX(320%);}}',
            '.finpay-is-loading{pointer-events:none;opacity:.78;}',
            '.finpay-inline-spinner{margin-right:7px;}'
        ].join('');

        document.head.appendChild(style);
    }

    function ensureLoaderElements() {
        if (overlayEl && topBarEl) {
            return;
        }

        overlayEl = document.createElement('div');
        overlayEl.className = 'finpay-global-loader';
        overlayEl.id = 'finpayGlobalLoader';
        overlayEl.setAttribute('aria-live', 'polite');
        overlayEl.setAttribute('aria-hidden', 'true');
        overlayEl.innerHTML = '' +
            '<div class="finpay-global-loader-card" role="status">' +
            '  <span class="finpay-global-loader-icon"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i></span>' +
            '  <span class="finpay-global-loader-text">Loading...</span>' +
            '</div>';

        overlayTextEl = overlayEl.querySelector('.finpay-global-loader-text');

        topBarEl = document.createElement('div');
        topBarEl.className = 'finpay-top-loader';
        topBarEl.id = 'finpayTopLoader';

        document.body.appendChild(topBarEl);
        document.body.appendChild(overlayEl);
    }

    function setOverlayText(message) {
        if (!overlayTextEl) {
            return;
        }
        overlayTextEl.textContent = String(message || 'Loading...');
    }

    function showOverlay(message, immediate) {
        ensureStyles();
        ensureLoaderElements();
        setOverlayText(message || 'Loading...');

        if (state.overlayHideTimer) {
            clearTimeout(state.overlayHideTimer);
            state.overlayHideTimer = null;
        }

        if (state.overlayShowTimer) {
            clearTimeout(state.overlayShowTimer);
            state.overlayShowTimer = null;
        }

        var reveal = function () {
            if (!overlayEl) {
                return;
            }
            overlayEl.classList.add('is-visible');
            overlayEl.setAttribute('aria-hidden', 'false');
            state.overlayVisible = true;
            state.overlayMinVisibleUntil = Date.now() + state.overlayMinVisibleMs;
        };

        if (immediate) {
            reveal();
            return;
        }

        state.overlayShowTimer = setTimeout(reveal, state.overlayDelay);
    }

    function hideOverlay(force) {
        if (state.overlayShowTimer) {
            clearTimeout(state.overlayShowTimer);
            state.overlayShowTimer = null;
        }

        if (!overlayEl || !state.overlayVisible) {
            return;
        }

        var hideNow = function () {
            if (!overlayEl) {
                return;
            }
            overlayEl.classList.remove('is-visible');
            overlayEl.setAttribute('aria-hidden', 'true');
            state.overlayVisible = false;
        };

        if (force) {
            hideNow();
            return;
        }

        var wait = Math.max(0, state.overlayMinVisibleUntil - Date.now());
        if (wait === 0) {
            hideNow();
            return;
        }

        state.overlayHideTimer = setTimeout(hideNow, wait);
    }

    function showTopBar(immediate) {
        ensureStyles();
        ensureLoaderElements();

        if (state.barHideTimer) {
            clearTimeout(state.barHideTimer);
            state.barHideTimer = null;
        }
        if (state.barShowTimer) {
            clearTimeout(state.barShowTimer);
            state.barShowTimer = null;
        }

        var reveal = function () {
            if (!topBarEl) {
                return;
            }
            topBarEl.classList.add('is-visible');
            state.barVisible = true;
            state.barMinVisibleUntil = Date.now() + state.barMinVisibleMs;
        };

        if (immediate) {
            reveal();
            return;
        }

        state.barShowTimer = setTimeout(reveal, state.barDelay);
    }

    function hideTopBar(force) {
        if (state.barShowTimer) {
            clearTimeout(state.barShowTimer);
            state.barShowTimer = null;
        }

        if (!topBarEl || !state.barVisible) {
            return;
        }

        var hideNow = function () {
            if (!topBarEl) {
                return;
            }
            topBarEl.classList.remove('is-visible');
            state.barVisible = false;
        };

        if (force) {
            hideNow();
            return;
        }

        var wait = Math.max(0, state.barMinVisibleUntil - Date.now());
        if (wait === 0) {
            hideNow();
            return;
        }

        state.barHideTimer = setTimeout(hideNow, wait);
    }

    function startTask(options) {
        var opts = options || {};
        var mode = String(opts.mode || 'bar').toLowerCase();
        var immediate = opts.immediate === true;

        if (mode === 'overlay') {
            state.overlayPending += 1;
            showOverlay(opts.message || 'Loading...', immediate);
            return;
        }

        state.barPending += 1;
        showTopBar(immediate);
    }

    function stopTask(options) {
        var opts = options || {};
        var mode = String(opts.mode || 'bar').toLowerCase();
        var force = opts.force === true;

        if (mode === 'overlay') {
            state.overlayPending = Math.max(0, state.overlayPending - 1);
            if (state.overlayPending === 0) {
                hideOverlay(force);
            }
            return;
        }

        state.barPending = Math.max(0, state.barPending - 1);
        if (state.barPending === 0) {
            hideTopBar(force);
        }
    }

    function markUserIntent() {
        state.userIntentAt = Date.now();
    }

    function hasRecentUserIntent() {
        return (Date.now() - state.userIntentAt) < 1800;
    }

    function setBusyButton(button, busyLabel) {
        if (!button || button.dataset.finpayBusy === '1') {
            return;
        }

        button.dataset.finpayBusy = '1';
        button.dataset.finpayOriginalHtml = button.innerHTML;
        button.classList.add('finpay-is-loading');
        button.disabled = true;

        var label = String(busyLabel || button.getAttribute('data-loading-label') || 'Processing...');
        button.innerHTML = '<i class="fas fa-circle-notch fa-spin finpay-inline-spinner" aria-hidden="true"></i>' + label;
    }

    function getRequestUrl(input) {
        if (typeof input === 'string') {
            return input;
        }
        if (input && typeof input.url === 'string') {
            return input.url;
        }
        return '';
    }

    function getRequestMethod(input, init) {
        var method = '';
        if (init && typeof init.method === 'string') {
            method = init.method;
        } else if (input && typeof input.method === 'string') {
            method = input.method;
        }
        return String(method || 'GET').toUpperCase();
    }

    function getHeaderValue(headers, key) {
        if (!headers) {
            return '';
        }

        if (typeof Headers !== 'undefined' && headers instanceof Headers) {
            return String(headers.get(key) || headers.get(key.toLowerCase()) || '');
        }

        if (Array.isArray(headers)) {
            for (var i = 0; i < headers.length; i += 1) {
                var pair = headers[i] || [];
                if (String(pair[0] || '').toLowerCase() === key.toLowerCase()) {
                    return String(pair[1] || '');
                }
            }
            return '';
        }

        if (typeof headers === 'object') {
            return String(headers[key] || headers[key.toLowerCase()] || '');
        }

        return '';
    }

    function getRequestMode(method, init) {
        var headers = init && init.headers ? init.headers : null;
        var skipValue = getHeaderValue(headers, 'X-Loading-Skip').toLowerCase();
        if (skipValue === '1' || skipValue === 'true') {
            return 'none';
        }

        var modeHeader = getHeaderValue(headers, 'X-Loading-Mode').toLowerCase();
        if (modeHeader === 'overlay' || modeHeader === 'bar' || modeHeader === 'none') {
            return modeHeader;
        }

        var modeOption = init && init.finpayLoadingMode ? String(init.finpayLoadingMode).toLowerCase() : '';
        if (modeOption === 'overlay' || modeOption === 'bar' || modeOption === 'none') {
            return modeOption;
        }

        if (!hasRecentUserIntent()) {
            return 'none';
        }

        if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
            return 'bar';
        }

        return 'none';
    }

    function shouldSkipRequest(url, method, init) {
        if (init && init.headers && typeof init.headers === 'object') {
            var headerValue = init.headers['X-Loading-Skip'] || init.headers['x-loading-skip'];
            if (String(headerValue || '').toLowerCase() === '1' || String(headerValue || '').toLowerCase() === 'true') {
                return true;
            }
        }

        var lower = String(url || '').toLowerCase();
        if (
            lower.indexOf('withdraw_status.php') !== -1 ||
            lower.indexOf('prices') !== -1 ||
            lower.indexOf('history') !== -1 ||
            lower.indexOf('heartbeat') !== -1 ||
            lower.indexOf('poll') !== -1
        ) {
            return true;
        }

        return false;
    }

    function patchFetch() {
        if (!originalFetch) {
            return;
        }

        window.fetch = function (input, init) {
            var url = getRequestUrl(input);
            var method = getRequestMethod(input, init);
            var normalizedInit = init || {};
            var mode = getRequestMode(method, normalizedInit);
            var track = mode !== 'none' && !shouldSkipRequest(url, method, normalizedInit);

            if (track) {
                startTask({
                    mode: mode,
                    message: 'Working...',
                });
            }

            return originalFetch(input, init).finally(function () {
                if (track) {
                    stopTask({ mode: mode });
                }
            });
        };
    }

    function patchForms() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || !(form instanceof HTMLFormElement)) {
                return;
            }
            if (form.hasAttribute('data-no-loading')) {
                return;
            }

            markUserIntent();

            var submitter = event.submitter;
            if (!submitter) {
                submitter = form.querySelector('button[type="submit"],input[type="submit"]');
            }
            if (submitter) {
                setBusyButton(submitter, submitter.getAttribute('data-loading-label') || 'Please wait...');
            }

            setTimeout(function () {
                if (event.defaultPrevented) {
                    return;
                }

                var mode = String(form.getAttribute('data-loading-mode') || 'overlay').toLowerCase();
                if (mode !== 'none') {
                    startTask({
                        mode: mode,
                        message: form.getAttribute('data-loading-message') || 'Processing request...',
                        immediate: true,
                    });
                }
            }, 0);
        }, true);
    }

    function patchDataLoadingElements() {
        document.addEventListener('pointerdown', markUserIntent, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                markUserIntent();
            }
        }, true);
    }

    function init() {
        ensureStyles();
        ensureLoaderElements();
        patchFetch();
        patchForms();
        patchDataLoadingElements();

        window.addEventListener('pageshow', function () {
            state.overlayPending = 0;
            state.barPending = 0;
            hideOverlay(true);
            hideTopBar(true);
        });

        window.addEventListener('beforeunload', function () {
            state.overlayPending = 0;
            state.barPending = 0;
            hideOverlay(true);
            hideTopBar(true);
        });
    }

    window.finpayLoading = {
        show: function (message, mode) {
            startTask({
                mode: mode || 'overlay',
                message: message || 'Loading...',
                immediate: true,
            });
        },
        hide: function (mode) {
            var targetMode = String(mode || 'all').toLowerCase();
            if (targetMode === 'overlay' || targetMode === 'all') {
                state.overlayPending = 0;
                hideOverlay(true);
            }
            if (targetMode === 'bar' || targetMode === 'all') {
                state.barPending = 0;
                hideTopBar(true);
            }
        },
        start: startTask,
        stop: stopTask,
        markUserIntent: markUserIntent,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

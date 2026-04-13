(function () {
    var SOUND_PREF_KEY = 'finpay_activity_sound_enabled';
    var JOURNAL_KEY = 'finpay_activity_journal';
    var JOURNAL_MAX_ITEMS = 200;
    var soundEnabled = true;
    var lastToastMeta = {
        ts: 0,
        title: '',
        type: '',
        message: ''
    };
    var TOAST_DEDUPE_WINDOW_MS = 1200;

    try {
        soundEnabled = localStorage.getItem(SOUND_PREF_KEY) !== '0';
    } catch (e) {
        soundEnabled = true;
    }

    function ensureContainer() {
        var existing = document.getElementById('finpay-toast-container');
        if (existing) {
            return existing;
        }

        var container = document.createElement('div');
        container.id = 'finpay-toast-container';
        container.className = 'finpay-toast-container';
        document.body.appendChild(container);
        return container;
    }

    function ensureStyles() {
        if (document.getElementById('finpay-toast-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'finpay-toast-style';
        style.textContent = '' +
            '.finpay-toast-container{position:fixed;top:16px;right:16px;z-index:20000;display:flex;flex-direction:column;gap:10px;max-width:360px;width:calc(100% - 24px);}' +
            '.finpay-toast{border-radius:14px;padding:12px 14px;border:1px solid var(--border-light,rgba(0,0,0,0.1));background:rgba(255,255,255,0.96);box-shadow:0 12px 30px rgba(0,0,0,0.12);opacity:0;transform:translateY(-6px);transition:opacity .18s ease,transform .18s ease;}' +
            '.finpay-toast.show{opacity:1;transform:translateY(0);}' +
            '.finpay-toast-title{font-size:0.82rem;font-weight:700;letter-spacing:.2px;margin:0 0 2px;color:#111827;}' +
            '.finpay-toast-message{font-size:0.88rem;line-height:1.45;color:#374151;}' +
            '.finpay-toast.success{border-color:rgba(16,185,129,0.35);background:rgba(236,253,245,0.98);}' +
            '.finpay-toast.error{border-color:rgba(239,68,68,0.35);background:rgba(254,242,242,0.98);}' +
            '.finpay-toast.warning{border-color:rgba(245,158,11,0.35);background:rgba(255,251,235,0.98);}' +
            '.finpay-toast.info{border-color:rgba(59,130,246,0.28);background:rgba(239,246,255,0.98);}' +
            '@media (max-width:767.98px){.finpay-toast-container{left:12px;right:12px;top:10px;max-width:none;width:auto;}}';
        document.head.appendChild(style);
    }

    function playTone(kind) {
        if (!soundEnabled) {
            return;
        }

        try {
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                return;
            }

            var ctx = new AudioContextClass();
            var now = ctx.currentTime;
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();

            var profile = {
                success: [740, 980, 0.20],
                error: [220, 160, 0.26],
                warning: [460, 520, 0.22],
                info: [560, 680, 0.18]
            };

            var p = profile[kind] || profile.info;
            osc.type = kind === 'error' ? 'triangle' : 'sine';
            osc.frequency.setValueAtTime(p[0], now);
            osc.frequency.exponentialRampToValueAtTime(p[1], now + (p[2] * 0.55));

            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + p[2]);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + p[2] + 0.02);
        } catch (e) {
            // no-op
        }
    }

    function notify(message, options) {
        options = options || {};
        var type = String(options.type || 'info').toLowerCase();
        var title = String(options.title || (type.charAt(0).toUpperCase() + type.slice(1)));
        var duration = Number(options.duration || 3200);

        if (!message) {
            return;
        }

        ensureStyles();
        var container = ensureContainer();
        var toast = document.createElement('div');
        toast.className = 'finpay-toast ' + type;
        toast.innerHTML = '<div class="finpay-toast-title"></div><div class="finpay-toast-message"></div>';

        var titleEl = toast.querySelector('.finpay-toast-title');
        var msgEl = toast.querySelector('.finpay-toast-message');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (msgEl) {
            msgEl.textContent = String(message);
        }

        lastToastMeta = {
            ts: Date.now(),
            title: title.toLowerCase(),
            type: type,
            message: String(message).trim().toLowerCase(),
        };

        container.appendChild(toast);
        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        if (options.sound !== false) {
            playTone(type);
        }

        var ttl = duration > 0 ? duration : 3200;
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, ttl);
    }

    window.finpayFeedback = {
        notify: notify,
        playSound: playTone,
        isSoundEnabled: function () {
            return !!soundEnabled;
        },
        setSoundEnabled: function (enabled) {
            soundEnabled = !!enabled;
            try {
                localStorage.setItem(SOUND_PREF_KEY, soundEnabled ? '1' : '0');
            } catch (e) {
                // no-op
            }
            return soundEnabled;
        }
    };

    window.finpayNotify = notify;
    window.finpayActivitySound = playTone;

    function shouldSuppressDuplicateToast(kind, title, message) {
        var now = Date.now();
        if ((now - Number(lastToastMeta.ts || 0)) > TOAST_DEDUPE_WINDOW_MS) {
            return false;
        }

        var normalizedKind = String(kind || 'info').toLowerCase();
        var normalizedTitle = String(title || '').trim().toLowerCase();
        var normalizedMessage = String(message || '').trim().toLowerCase();

        var sameType = normalizedKind === String(lastToastMeta.type || '');
        var sameTitle = normalizedTitle !== '' && normalizedTitle === String(lastToastMeta.title || '');
        var sameMessage = normalizedMessage !== '' && normalizedMessage === String(lastToastMeta.message || '');

        return sameMessage || (sameType && sameTitle);
    }

    function readJournal() {
        try {
            var raw = localStorage.getItem(JOURNAL_KEY);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function writeJournal(items) {
        try {
            localStorage.setItem(JOURNAL_KEY, JSON.stringify((Array.isArray(items) ? items : []).slice(0, JOURNAL_MAX_ITEMS)));
        } catch (e) {
            // no-op
        }
    }

    function appendJournal(detail) {
        if (!detail || typeof detail !== 'object') {
            return;
        }

        var item = {
            ts: new Date().toISOString(),
            detail: {
                kind: String(detail.kind || detail.type || 'info').toLowerCase(),
                type: String(detail.type || detail.kind || 'info').toLowerCase(),
                title: String(detail.title || 'Activity'),
                message: String(detail.message || ''),
                asset: String(detail.asset || detail.symbol || ''),
                symbol: String(detail.symbol || detail.asset || ''),
                important: detail.important !== false,
            }
        };

        var journal = readJournal();
        journal.unshift(item);
        writeJournal(journal);
    }

    window.addEventListener('finpay:activity', function (event) {
        var detail = (event && event.detail) ? event.detail : {};
        appendJournal(detail);

        var kind = String(detail.kind || detail.type || 'info').toLowerCase();
        var message = detail.message || '';
        var title = detail.title || 'Activity';
        var important = detail.important !== false;

        if (message && important) {
            if (detail.toast === false || shouldSuppressDuplicateToast(kind, title, message)) {
                return;
            }

            notify(message, {
                type: kind,
                title: title,
                duration: Number(detail.duration || 3200),
                sound: detail.sound !== false,
            });
            return;
        }

        if (important) {
            playTone(kind);
        }
    });
})();

const App = {
    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    url(path) {
        const base = document.querySelector('meta[name="app-base"]')?.content
                  || document.querySelector('meta[name="app-url"]')?.content
                  || '';
        if (!path.startsWith('/')) path = '/' + path;
        return (base.replace(/\/$/, '') + path) || path;
    },

    asset(path) {
        return this.url('/assets/' + path.replace(/^\//, ''));
    },

    async fetch(url, options = {}) {
        if (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) {
            url = this.url(url);
        }

        const defaults = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken(),
            },
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        if (options.method && options.method !== 'GET') {
            if (options.body instanceof FormData) {
                options.body.append('_csrf_token', this.csrfToken());
            }
        }

        const res = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...options.headers } });

        let data;
        try {
            data = await res.json();
        } catch {
            data = { success: false, message: 'Invalid server response' };
        }

        if (!res.ok) {
            throw { status: res.status, ...data };
        }

        return data;
    },

    toast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-orange-500',
            info: 'bg-blue-600',
        };

        const el = document.createElement('div');
        el.className = `toast ${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg text-sm max-w-sm flex items-center gap-2`;
        el.innerHTML = `<span class="flex-1">${this.escape(message)}</span><button type="button" onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100">&times;</button>`;
        container.appendChild(el);

        setTimeout(() => el.remove(), 5000);
    },

    escape(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    setLoading(btn, loading, text) {
        if (!btn) return;
        if (loading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin inline w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>${text || 'Loading...'}`;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || text || 'Submit';
        }
    },

    // ─── Universal Progress Loader (1% → 100%) ───────────────────────────────
    // Use App.ProgressLoader.show(label) to start, App.ProgressLoader.done(cb) when done.
    ProgressLoader: {
        _el: null,
        _bar: null,
        _label: null,
        _pct: null,
        _timer: null,
        _current: 0,

        _build() {
            if (document.getElementById('_prog-overlay')) {
                this._el    = document.getElementById('_prog-overlay');
                this._bar   = document.getElementById('_prog-bar');
                this._label = document.getElementById('_prog-label');
                this._pct   = document.getElementById('_prog-pct');
                return;
            }
            const overlay = document.createElement('div');
            overlay.id = '_prog-overlay';
            overlay.style.cssText = [
                'position:fixed','inset:0','z-index:9999',
                'background:rgba(15,23,42,0.65)',
                'display:flex','align-items:center','justify-content:center',
                'backdrop-filter:blur(3px)',
            ].join(';');

            overlay.innerHTML = [
                '<div style="background:#fff;border-radius:16px;padding:32px 40px;min-width:320px;max-width:90vw;',
                'box-shadow:0 25px 60px rgba(0,0,0,0.25);text-align:center;">',
                  '<div style="width:56px;height:56px;margin:0 auto 20px;border-radius:50%;background:#eff6ff;',
                  'display:flex;align-items:center;justify-content:center;">',
                    '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="animation:_progSpin 1s linear infinite;">',
                      '<circle cx="12" cy="12" r="10" stroke="#bfdbfe" stroke-width="3"/>',
                      '<path d="M4 12a8 8 0 018-8" stroke="#2563eb" stroke-width="3" stroke-linecap="round"/>',
                    '</svg>',
                  '</div>',
                  '<div id="_prog-label" style="font-size:15px;font-weight:600;color:#1e293b;margin-bottom:16px;">Processing...</div>',
                  '<div style="background:#f1f5f9;border-radius:999px;height:10px;overflow:hidden;margin-bottom:10px;">',
                    '<div id="_prog-bar" style="height:100%;width:1%;background:linear-gradient(90deg,#2563eb,#60a5fa);',
                    'border-radius:999px;transition:width 0.4s ease;"></div>',
                  '</div>',
                  '<div id="_prog-pct" style="font-size:13px;color:#64748b;font-weight:500;">1%</div>',
                '</div>',
            ].join('');

            if (!document.getElementById('_prog-keyframes')) {
                const style = document.createElement('style');
                style.id = '_prog-keyframes';
                style.textContent = '@keyframes _progSpin{to{transform:rotate(360deg)}}';
                document.head.appendChild(style);
            }
            document.body.appendChild(overlay);

            this._el    = overlay;
            this._bar   = document.getElementById('_prog-bar');
            this._label = document.getElementById('_prog-label');
            this._pct   = document.getElementById('_prog-pct');
        },

        show(label) {
            this._build();
            this._current = 1;
            this._el.style.display = 'flex';
            if (this._label) this._label.textContent = label || 'Processing...';
            this._setWidth(1);
            clearInterval(this._timer);

            // Smooth crawl: fast at start, slows near 95%
            this._timer = setInterval(() => {
                if (this._current < 30) {
                    this._current += (Math.random() * 3 + 1);
                } else if (this._current < 70) {
                    this._current += (Math.random() * 1.5 + 0.3);
                } else if (this._current < 90) {
                    this._current += (Math.random() * 0.6 + 0.1);
                } else if (this._current < 95) {
                    this._current += 0.05;
                }
                this._current = Math.min(this._current, 95);
                this._setWidth(this._current);
            }, 300);
        },

        done(cb) {
            clearInterval(this._timer);
            this._setWidth(100);
            if (this._label) this._label.textContent = 'Done! \u2713';
            setTimeout(() => {
                if (this._el) this._el.style.display = 'none';
                this._current = 0;
                if (typeof cb === 'function') cb();
            }, 500);
        },

        error(msg) {
            clearInterval(this._timer);
            if (this._el) this._el.style.display = 'none';
            this._current = 0;
            App.toast(msg || 'An error occurred', 'error');
        },

        _setWidth(pct) {
            const v = Math.round(Math.min(100, Math.max(1, pct)));
            if (this._bar) this._bar.style.width = v + '%';
            if (this._pct) this._pct.textContent = v + '%';
        },
    },
    // ─────────────────────────────────────────────────────────────────────────
};

setTimeout(() => {
    document.getElementById('flash-success')?.remove();
    document.getElementById('flash-error')?.remove();
}, 5000);

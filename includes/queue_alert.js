/* includes/queue_alert.js
 * Shows a top-most "Client Waiting" alert on any AIMS page.
 * No dependencies (does not need jQuery or SweetAlert).
 */
(function () {
    if (window.__queueAlertLoaded) return;
    window.__queueAlertLoaded = true;

    const ENDPOINT  = '../includes/queue_alert_ajax.php';
    const SOUND_URL = '../dist/sounds/queue-bell.wav';
    const POLL_MS   = 5000;
    const REPEAT_SOUND_MS = 4000;

    const active = new Map();      // id -> alert data
    const notified = new Set();    // ids already sent as desktop notifications
    const origTitle = document.title;
    let overlay, listEl, soundTimer = null, flashTimer = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function fmtTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        let h = d.getHours(), m = d.getMinutes();
        const ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ap;
    }

    // ---------- UI ----------
    function buildOverlay() {
        const style = document.createElement('style');
        style.textContent = `
        #qaOverlay{display:none;position:fixed;inset:0;z-index:2147483647;background:rgba(0,0,0,.65);
            align-items:center;justify-content:center;font-family:'Nunito Sans',Arial,sans-serif}
        #qaBox{background:#fff;border-radius:18px;width:min(560px,92vw);max-height:88vh;overflow:auto;
            border:5px solid #f97316;box-shadow:0 10px 60px rgba(0,0,0,.5);animation:qaPulse 1s ease-in-out infinite}
        @keyframes qaPulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.7)}50%{box-shadow:0 0 0 18px rgba(249,115,22,0)}}
        #qaHead{background:#0e5225;color:#fff;padding:16px 22px;font-size:22px;font-weight:900;letter-spacing:1px}
        .qaItem{padding:16px 22px;border-bottom:1px solid #e5e7eb}
        .qaItem.pri{background:#fff5ec}
        .qaNum{font-size:40px;font-weight:900;color:#0e5225;line-height:1}
        .qaItem.pri .qaNum{color:#f97316}
        .qaName{font-size:20px;font-weight:800;margin-top:6px;color:#1a2e1a}
        .qaMeta{font-size:14px;color:#555;margin-top:2px}
        .qaBtn{margin-top:12px;background:#1a8a3c;color:#fff;border:0;border-radius:10px;
            padding:12px 22px;font-size:16px;font-weight:800;cursor:pointer;width:100%}
        .qaBtn:hover{background:#0e5225}
        .qaTag{display:inline-block;background:#f97316;color:#fff;border-radius:6px;padding:2px 8px;
            font-size:12px;font-weight:800;margin-left:8px;vertical-align:middle}`;
        document.head.appendChild(style);

        overlay = document.createElement('div');
        overlay.id = 'qaOverlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.innerHTML = '<div id="qaBox"><div id="qaHead">🔔 CLIENT WAITING</div><div id="qaList"></div></div>';
        document.body.appendChild(overlay);
        listEl = overlay.querySelector('#qaList');

        listEl.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-ack]');
            if (btn) acknowledge(parseInt(btn.getAttribute('data-ack'), 10));
        });
    }

    function render() {
        if (!overlay) buildOverlay();
        if (active.size === 0) {
            overlay.style.display = 'none';
            stopAttention();
            return;
        }
        let html = '';
        active.forEach(a => {
            html += `<div class="qaItem ${a.is_priority ? 'pri' : ''}">
                <div class="qaNum">${esc(a.queue_number)}${a.is_priority ? '<span class="qaTag">PRIORITY</span>' : ''}</div>
                <div class="qaName">${esc(a.visitor_name)}${a.company ? ' — ' + esc(a.company) : ''}</div>
                <div class="qaMeta">Purpose: ${esc(a.purpose)}${a.destination ? ' · ' + esc(a.destination) : ''}</div>
                <div class="qaMeta">Arrived ${esc(fmtTime(a.time_in))}</div>
                <button class="qaBtn" data-ack="${a.id}">✔ Acknowledge</button>
            </div>`;
        });
        listEl.innerHTML = html;
        overlay.style.display = 'flex';
        startAttention();
    }

    // ---------- attention: sound, tab title, desktop notification ----------
    function playSound() {
        try { const a = new Audio(SOUND_URL); a.volume = 1; a.play().catch(() => {}); } catch (e) {}
    }

    function startAttention() {
        if (!soundTimer) { playSound(); soundTimer = setInterval(playSound, REPEAT_SOUND_MS); }
        if (!flashTimer) {
            flashTimer = setInterval(function () {
                document.title = document.title === origTitle
                    ? '🔔 CLIENT WAITING (' + active.size + ')' : origTitle;
            }, 800);
        }
    }

    function stopAttention() {
        clearInterval(soundTimer); soundTimer = null;
        clearInterval(flashTimer); flashTimer = null;
        document.title = origTitle;
    }

    function desktopNotify(a) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (notified.has(a.id)) return;
        notified.add(a.id);
        try {
            const n = new Notification('Client waiting: ' + a.queue_number, {
                body: a.visitor_name + ' — ' + a.purpose,
                requireInteraction: true,
                tag: 'queue-' + a.id
            });
            n.onclick = function () { window.focus(); n.close(); };
        } catch (e) {}
    }

    // Ask for notification permission on the first click anywhere (browsers require a user gesture)
    document.addEventListener('click', function askOnce() {
        document.removeEventListener('click', askOnce);
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    });

    // ---------- server calls ----------
    function acknowledge(id) {
        active.delete(id);
        render();
        fetch(ENDPOINT + '?action=ack', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        }).catch(() => {});
    }

    let inFlight = false, stopped = false, worker = null, fallbackTimer = null;

    function stopPolling() {
        stopped = true;
        if (worker) { worker.terminate(); worker = null; }
        if (fallbackTimer) { clearInterval(fallbackTimer); fallbackTimer = null; }
    }

    function poll() {
        if (inFlight || stopped) return;
        inFlight = true;
        fetch(ENDPOINT + '?action=get', { credentials: 'same-origin', cache: 'no-store' })
            .then(r => {
                if (r.status === 401) { stopPolling(); return null; }   // logged out
                return r.json();
            })
            .then(data => {
                if (!data || !data.success) return;
                const fresh = new Map();
                data.alerts.forEach(a => fresh.set(a.id, a));

                // add new, drop ones handled elsewhere (acked on another PC/tab)
                fresh.forEach((a, id) => {
                    if (!active.has(id)) desktopNotify(a);
                    active.set(id, a);
                });
                Array.from(active.keys()).forEach(id => { if (!fresh.has(id)) active.delete(id); });
                render();
            })
            .catch(() => {})
            .then(() => { inFlight = false; });
    }

    // A Web Worker timer is not slowed down when the tab is in the background,
    // unlike setTimeout/setInterval on the page itself.
    function startTimer() {
        try {
            const src = 'setInterval(function(){postMessage(1)},' + POLL_MS + ');';
            worker = new Worker(URL.createObjectURL(new Blob([src], { type: 'application/javascript' })));
            worker.onmessage = poll;
        } catch (e) {
            fallbackTimer = setInterval(poll, POLL_MS);   // e.g. blocked by CSP
        }
    }

    function start() { poll(); startTimer(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
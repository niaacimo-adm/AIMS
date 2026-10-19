/* includes/queue_alert.js
 * Shows a top-most full-screen alert on any AIMS page for:
 *   - a client waiting in the queue (orange/green, bell repeats until acknowledged)
 *   - a new chat message (blue, one ping)
 * No dependencies (does not need jQuery or SweetAlert).
 */
(function () {
    if (window.__queueAlertLoaded) return;
    window.__queueAlertLoaded = true;

    const ENDPOINT  = '../includes/queue_alert_ajax.php';
    const SOUND_URL = '../dist/sounds/queue-bell.wav';
    const POLL_MS   = 5000;
    const REPEAT_SOUND_MS = 4000;

    const active = new Map();        // key -> alert data
    const notified = new Set();      // "key:version" already sent as desktop notifications
    const viewingAcked = new Set();  // "key:version" silently dismissed because the chat is open
    const origTitle = document.title;
    let overlay, listEl, headEl, boxEl;
    let soundTimer = null, flashTimer = null, lastSig = '', actx = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function fmtTime(dt) {
        if (!dt) return '';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d)) return '';
        let h = d.getHours(), m = d.getMinutes();
        const ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ap;
    }

    function avatar(pic) {
        return pic ? '../dist/img/employees/' + pic : '../dist/img/nialogo.png';
    }

    function hasQueue() {
        return Array.from(active.values()).some(a => a.type !== 'chat');
    }

    function isViewing(uid) {
        return !!(window.chatSystem && typeof window.chatSystem.isViewing === 'function' &&
                  window.chatSystem.isViewing(uid));
    }

    // ---------- UI ----------
    function buildOverlay() {
        const style = document.createElement('style');
        style.textContent = `
        #qaOverlay{display:none;position:fixed;inset:0;z-index:2147483647;background:rgba(0,0,0,.65);
            align-items:center;justify-content:center;font-family:'Nunito Sans',Arial,sans-serif}
        #qaBox{background:#fff;border-radius:18px;width:min(560px,92vw);max-height:88vh;overflow:auto;
            border:5px solid #f97316;box-shadow:0 10px 60px rgba(0,0,0,.5);animation:qaPulse 1s ease-in-out infinite}
        #qaBox.chatOnly{border-color:#4361ee;animation:qaPulseBlue 1s ease-in-out infinite}
        @keyframes qaPulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.7)}50%{box-shadow:0 0 0 18px rgba(249,115,22,0)}}
        @keyframes qaPulseBlue{0%,100%{box-shadow:0 0 0 0 rgba(67,97,238,.7)}50%{box-shadow:0 0 0 18px rgba(67,97,238,0)}}
        #qaHead{background:#0e5225;color:#fff;padding:16px 22px;font-size:22px;font-weight:900;letter-spacing:1px}
        #qaBox.chatOnly #qaHead{background:#3f37c9}
        .qaItem{padding:16px 22px;border-bottom:1px solid #e5e7eb}
        .qaItem.pri{background:#fff5ec}
        .qaItem.chat{background:#f0f3ff;border-left:6px solid #4361ee}
        .qaNum{font-size:40px;font-weight:900;color:#0e5225;line-height:1}
        .qaItem.pri .qaNum{color:#f97316}
        .qaName{font-size:20px;font-weight:800;margin-top:6px;color:#1a2e1a}
        .qaMeta{font-size:14px;color:#555;margin-top:2px}
        .qaChatHead{display:flex;align-items:center;gap:12px}
        .qaAvatar{width:56px;height:56px;border-radius:50%;object-fit:cover;flex-shrink:0}
        .qaChatName{font-size:20px;font-weight:800;color:#1a2e1a}
        .qaChatText{font-size:15px;color:#222;margin-top:10px;word-break:break-word;
            background:#fff;border:1px solid #d6dcff;border-radius:12px;padding:10px 12px}
        .qaBtnRow{display:flex;gap:10px;margin-top:12px}
        .qaBtn{margin-top:12px;background:#1a8a3c;color:#fff;border:0;border-radius:10px;
            padding:12px 22px;font-size:16px;font-weight:800;cursor:pointer;width:100%}
        .qaBtn:hover{background:#0e5225}
        .qaBtnRow .qaBtn{margin-top:0;flex:1}
        .qaBtn.blue{background:#4361ee}.qaBtn.blue:hover{background:#3f37c9}
        .qaBtn.gray{background:#6b7280}.qaBtn.gray:hover{background:#4b5563}
        .qaTag{display:inline-block;background:#f97316;color:#fff;border-radius:6px;padding:2px 8px;
            font-size:12px;font-weight:800;margin-left:8px;vertical-align:middle}`;
        document.head.appendChild(style);

        overlay = document.createElement('div');
        overlay.id = 'qaOverlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.innerHTML = '<div id="qaBox"><div id="qaHead"></div><div id="qaList"></div></div>';
        document.body.appendChild(overlay);
        boxEl  = overlay.querySelector('#qaBox');
        headEl = overlay.querySelector('#qaHead');
        listEl = overlay.querySelector('#qaList');

        listEl.addEventListener('click', function (e) {
            const reply = e.target.closest('[data-reply]');
            if (reply) {
                const sid = reply.getAttribute('data-sender');
                acknowledge(reply.getAttribute('data-reply'));
                if (window.chatSystem && window.chatSystem.openChatWith) window.chatSystem.openChatWith(sid);
                return;
            }
            const btn = e.target.closest('[data-ack]');
            if (btn) acknowledge(btn.getAttribute('data-ack'));
        });
    }

    function itemHtml(a) {
        if (a.type === 'chat') {
            const n = parseInt(a.count, 10) || 1;
            const canReply = !!(window.chatSystem && window.chatSystem.openChatWith);
            return `<div class="qaItem chat">
                <div class="qaChatHead">
                    <img class="qaAvatar" src="${esc(avatar(a.picture))}" alt="" onerror="this.src='../dist/img/nialogo.png'">
                    <div>
                        <div class="qaChatName">${esc(a.sender_name)}</div>
                        <div class="qaMeta">${n > 1 ? n + ' new messages' : 'New message'} · ${esc(fmtTime(a.time_in))}</div>
                    </div>
                </div>
                <div class="qaChatText">${esc(a.preview)}</div>
                <div class="qaBtnRow">
                    ${canReply ? `<button class="qaBtn blue" data-reply="${esc(a.key)}" data-sender="${esc(a.sender_id)}">Reply</button>` : ''}
                    <button class="qaBtn gray" data-ack="${esc(a.key)}">Dismiss</button>
                </div>
            </div>`;
        }
        return `<div class="qaItem ${a.is_priority ? 'pri' : ''}">
            <div class="qaNum">${esc(a.queue_number)}${a.is_priority ? '<span class="qaTag">PRIORITY</span>' : ''}</div>
            <div class="qaName">${esc(a.visitor_name)}${a.company ? ' — ' + esc(a.company) : ''}</div>
            <div class="qaMeta">Purpose: ${esc(a.purpose)}${a.destination ? ' · ' + esc(a.destination) : ''}</div>
            <div class="qaMeta">Arrived ${esc(fmtTime(a.time_in))}</div>
            <button class="qaBtn" data-ack="${esc(a.key)}">✔ Acknowledge</button>
        </div>`;
    }

    function render() {
        if (!overlay) buildOverlay();
        if (active.size === 0) {
            overlay.style.display = 'none';
            stopAttention();
            lastSig = '';
            return;
        }
        const q = hasQueue();
        const sig = Array.from(active.values()).map(a => a.key + ':' + a.version).join('|');
        if (sig !== lastSig) {                       // only rebuild when something changed
            lastSig = sig;
            // client waiting first, then chat messages
            const ordered = Array.from(active.values()).sort((a, b) =>
                (a.type === 'chat') - (b.type === 'chat'));
            listEl.innerHTML = ordered.map(itemHtml).join('');
        }
        const hasChat = Array.from(active.values()).some(a => a.type === 'chat');
        headEl.textContent = q ? (hasChat ? '🔔 CLIENT WAITING + 💬 MESSAGE' : '🔔 CLIENT WAITING') : '💬 NEW MESSAGE';
        boxEl.className = q ? '' : 'chatOnly';
        overlay.style.display = 'flex';
        startAttention();
    }

    // ---------- attention: sound, tab title, desktop notification ----------
    function playSound() {
        try { const a = new Audio(SOUND_URL); a.volume = 1; a.play().catch(() => {}); } catch (e) {}
    }

    // short two-note ping for chat (no sound file needed)
    function chatPing() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            if (!actx) actx = new AC();
            if (actx.state === 'suspended') actx.resume();
            const t = actx.currentTime;
            [[880, 0], [1175, 0.12]].forEach(function (n) {
                const o = actx.createOscillator(), g = actx.createGain();
                o.type = 'sine'; o.frequency.value = n[0];
                g.gain.setValueAtTime(0.0001, t + n[1]);
                g.gain.exponentialRampToValueAtTime(0.3, t + n[1] + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + 0.25);
                o.connect(g); g.connect(actx.destination);
                o.start(t + n[1]); o.stop(t + n[1] + 0.28);
            });
        } catch (e) {}
    }

    function startAttention() {
        // bell repeats only while a client is waiting; chat gets a single ping when it arrives
        if (hasQueue()) {
            if (!soundTimer) { playSound(); soundTimer = setInterval(playSound, REPEAT_SOUND_MS); }
        } else if (soundTimer) {
            clearInterval(soundTimer); soundTimer = null;
        }
        if (!flashTimer) {
            flashTimer = setInterval(function () {
                const label = hasQueue() ? '🔔 CLIENT WAITING (' : '💬 NEW MESSAGE (';
                document.title = document.title === origTitle ? label + active.size + ')' : origTitle;
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
        const tag = a.key + ':' + a.version;
        if (notified.has(tag)) return;
        notified.add(tag);
        try {
            const title = a.type === 'chat' ? 'Message from ' + a.sender_name : 'Client waiting: ' + a.queue_number;
            const body  = a.type === 'chat' ? a.preview : a.visitor_name + ' — ' + a.purpose;
            const n = new Notification(title, { body: body, requireInteraction: true, tag: a.key });
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
    function sendAck(key) {
        return fetch(ENDPOINT + '?action=ack', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=' + encodeURIComponent(key)
        }).catch(() => {});
    }

    function acknowledge(key) {
        active.delete(key);
        render();
        sendAck(key);
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
        fetch(ENDPOINT + '?action=get&v=2', { credentials: 'same-origin', cache: 'no-store' })
            .then(r => {
                if (r.status === 401) { stopPolling(); return null; }   // logged out
                return r.json();
            })
            .then(data => {
                if (!data || !data.success) return;

                const fresh = new Map();
                data.alerts.forEach(a => {
                    a.key = a.key || ('q' + a.id);
                    a.version = String(a.version == null ? 1 : a.version);
                    // already reading this chat: don't interrupt, just dismiss it quietly
                    if (a.type === 'chat' && isViewing(a.sender_id)) {
                        const tag = a.key + ':' + a.version;
                        if (!viewingAcked.has(tag)) { viewingAcked.add(tag); sendAck(a.key); }
                        return;
                    }
                    fresh.set(a.key, a);
                });

                fresh.forEach((a, key) => {
                    const old = active.get(key);
                    if (!old) {
                        desktopNotify(a);
                        if (a.type === 'chat') chatPing();
                    } else if (a.type === 'chat' && old.version !== a.version) {
                        desktopNotify(a);
                        chatPing();
                    }
                    active.set(key, a);
                });
                Array.from(active.keys()).forEach(key => { if (!fresh.has(key)) active.delete(key); });
                render();
            })
            .catch(() => {})
            .then(() => { inFlight = false; });
    }

    // A Web Worker timer is not slowed down when the tab is in the background
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
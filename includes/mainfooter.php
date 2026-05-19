<!-- Main Footer -->
<footer class="main-footer" id="mainFooter">
    <strong>NIA - ACIMO Intelligent Management Solution (AIMS)</strong>
    <div class="float-right d-none d-sm-inline-block">
        <b>MDO</b>(2024)
    </div>
</footer>

<!-- ═══════════════════════════════════════════════════════
     CHAT WIDGET — Floating button + mini popover
     ═══════════════════════════════════════════════════════ -->
<div class="cw-wrapper" id="cwWrapper">
    <!-- Floating Action Button -->
    <button class="cw-fab" id="cwFab" title="Messages">
        <i class="fas fa-comments"></i>
        <span class="cw-fab-badge" id="cwFabBadge" style="display:none;"></span>
    </button>

    <!-- Mini Chat Popover (contacts list) -->
    <div class="cw-popover" id="cwPopover">
        <div class="cw-popover-header">
            <span class="cw-popover-title">Messages</span>
            <div class="cw-popover-actions">
                <button class="cw-action-btn" id="cwOpenInbox" title="Open Inbox"><i class="fas fa-expand-alt"></i></button>
                <button class="cw-action-btn" id="cwClosePopover" title="Close"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="cw-popover-search">
            <i class="fas fa-search cw-search-icon"></i>
            <input type="text" id="cwSearch" placeholder="Search people…" class="cw-search-input">
        </div>
        <ul class="cw-user-list" id="cwUserList">
            <li class="cw-list-loading"><i class="fas fa-circle-notch fa-spin"></i> Loading…</li>
        </ul>
    </div>

    <!-- Active mini-chat window (one at a time in popover mode) -->
    <div class="cw-chat-window" id="cwChatWindow" style="display:none;">
        <div class="cw-chat-header">
            <button class="cw-back-btn" id="cwBackBtn"><i class="fas fa-arrow-left"></i></button>
            <div class="cw-chat-avatar-wrap">
                <img src="" id="cwChatAvatar" alt="" onerror="this.src='../dist/img/nialogo.png'">
                <span class="cw-status-dot" id="cwChatStatusDot"></span>
            </div>
            <div class="cw-chat-info">
                <div class="cw-chat-name" id="cwChatName"></div>
                <div class="cw-chat-status" id="cwChatStatus"></div>
            </div>
            <div class="cw-chat-header-actions">
                <button class="cw-action-btn" id="cwMsgSearchToggle" title="Search messages"><i class="fas fa-search"></i></button>
                <button class="cw-action-btn" id="cwMaximizeFromChat" title="Open in Inbox"><i class="fas fa-expand-alt"></i></button>
            </div>
        </div>
        <!-- Popover message search bar -->
        <div class="cw-msg-search-bar" id="cwMsgSearchBar" style="display:none;">
            <div class="cw-msg-search-inner">
                <i class="fas fa-search cw-msg-search-icon"></i>
                <input type="text" id="cwMsgSearchInput" class="cw-msg-search-field" placeholder="Search in conversation…" autocomplete="off">
                <span class="cw-msg-search-counter" id="cwMsgSearchCounter"></span>
                <button class="cw-msg-search-nav" id="cwMsgSearchPrev" title="Previous"><i class="fas fa-chevron-up"></i></button>
                <button class="cw-msg-search-nav" id="cwMsgSearchNext" title="Next"><i class="fas fa-chevron-down"></i></button>
                <button class="cw-msg-search-clear" id="cwMsgSearchClose" title="Close search"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="cw-messages-area" id="cwMessagesArea"></div>
        <div class="cw-input-bar">
            <input type="file" id="cwFileInput" class="cw-file-input-hidden" accept="*/*" multiple>
            <button class="cw-attach-btn cw-popover-attach" title="Attach file"><i class="fas fa-paperclip"></i></button>
            <textarea class="cw-msg-input" id="cwMsgInput" placeholder="Aa" rows="1"></textarea>
            <button class="cw-send-btn" id="cwSendBtn"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     INBOX MODAL — Full messenger-style maximized view
     ═══════════════════════════════════════════════════════ -->
<div class="cw-inbox-overlay" id="cwInboxOverlay" style="display:none;">
    <div class="cw-inbox" id="cwInbox">
        <!-- Sidebar: conversation list -->
        <div class="cw-inbox-sidebar">
            <div class="cw-inbox-sidebar-header">
                <h4>Messages</h4>
                <button class="cw-action-btn cw-inbox-close-btn" id="cwInboxClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="cw-inbox-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="cwInboxSearch" placeholder="Search conversations…" class="cw-inbox-search">
            </div>
            <ul class="cw-inbox-conv-list" id="cwInboxConvList">
                <li class="cw-list-loading"><i class="fas fa-circle-notch fa-spin"></i> Loading…</li>
            </ul>
        </div>

        <!-- Main: chat area -->
        <div class="cw-inbox-main" id="cwInboxMain">
            <!-- Empty state -->
            <div class="cw-inbox-empty" id="cwInboxEmpty">
                <i class="fas fa-comments"></i>
                <h5>Your Messages</h5>
                <p>Select a conversation to start chatting</p>
            </div>

            <!-- Active conversation -->
            <div class="cw-inbox-convo" id="cwInboxConvo" style="display:none;">
                <div class="cw-inbox-convo-header">
                    <div class="cw-chat-avatar-wrap">
                        <img src="" id="cwInboxAvatar" alt="" onerror="this.src='../dist/img/nialogo.png'">
                        <span class="cw-status-dot" id="cwInboxStatusDot"></span>
                    </div>
                    <div class="cw-chat-info">
                        <div class="cw-chat-name" id="cwInboxName"></div>
                        <div class="cw-chat-status" id="cwInboxStatus"></div>
                    </div>
                    <div class="cw-chat-header-actions">
                        <button class="cw-action-btn" id="cwInboxMsgSearchToggle" title="Search messages"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <!-- Inbox message search bar -->
                <div class="cw-msg-search-bar" id="cwInboxMsgSearchBar" style="display:none;">
                    <div class="cw-msg-search-inner">
                        <i class="fas fa-search cw-msg-search-icon"></i>
                        <input type="text" id="cwInboxMsgSearchInput" class="cw-msg-search-field" placeholder="Search in conversation…" autocomplete="off">
                        <span class="cw-msg-search-counter" id="cwInboxMsgSearchCounter"></span>
                        <button class="cw-msg-search-nav" id="cwInboxMsgSearchPrev" title="Previous"><i class="fas fa-chevron-up"></i></button>
                        <button class="cw-msg-search-nav" id="cwInboxMsgSearchNext" title="Next"><i class="fas fa-chevron-down"></i></button>
                        <button class="cw-msg-search-clear" id="cwInboxMsgSearchClose" title="Close search"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="cw-inbox-messages" id="cwInboxMessages"></div>
                <div class="cw-file-preview-strip" id="cwInboxFilePreview" style="display:none;"></div>
                <div class="cw-inbox-input-bar">
                    <input type="file" id="cwInboxFileInput" class="cw-file-input-hidden" accept="*/*" multiple>
                    <button class="cw-attach-btn cw-inbox-attach" title="Attach file"><i class="fas fa-paperclip"></i></button>
                    <textarea class="cw-msg-input cw-inbox-msg-input" id="cwInboxMsgInput" placeholder="Type a message…" rows="1"></textarea>
                    <button class="cw-send-btn cw-inbox-send-btn" id="cwInboxSendBtn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     STYLES
     ═══════════════════════════════════════════════════════ -->
<style>
/* ── Root tokens (inherit from mainheader vars) ────────── */
.cw-wrapper {
    position: fixed;
    bottom: 90px;
    right: 24px;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}

/* ── FAB ───────────────────────────────────────────────── */
.cw-fab {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    border: none; outline: none;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(67,97,238,.45);
    display: flex; align-items: center; justify-content: center;
    transition: transform .2s, box-shadow .2s;
    position: relative;
}
.cw-fab:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(67,97,238,.6); }
.cw-fab-badge {
    position: absolute; top: -4px; right: -4px;
    background: #e63946; color: #fff;
    border-radius: 10px; min-width: 20px; height: 20px;
    font-size: 11px; font-weight: 700; line-height: 20px;
    text-align: center; padding: 0 5px;
    border: 2px solid #fff;
}

/* ── Popover ───────────────────────────────────────────── */
.cw-popover {
    display: none;
    width: 320px;
    background: var(--chat-bg, #fff);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,.18);
    overflow: hidden;
    flex-direction: column;
    animation: cwSlideUp .2s ease;
    border: 1px solid var(--card-border, rgba(0,0,0,.08));
}
.cw-popover.open { display: flex; }
@keyframes cwSlideUp {
    from { opacity:0; transform: translateY(12px); }
    to   { opacity:1; transform: translateY(0); }
}
.cw-popover-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
}
.cw-popover-title { font-weight: 700; font-size: 16px; color: var(--text-primary, #111); }
.cw-popover-actions { display: flex; gap: 4px; }
.cw-action-btn {
    width: 30px; height: 30px;
    background: none; border: none; outline: none;
    border-radius: 50%;
    color: var(--text-muted, #6c757d);
    font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s;
}
.cw-action-btn:hover { background: var(--table-stripe, #f5f5f5); color: var(--text-primary, #111); }

/* ── Search ────────────────────────────────────────────── */
.cw-popover-search, .cw-inbox-search-wrap {
    position: relative; padding: 10px 12px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
}
.cw-search-icon, .cw-inbox-search-wrap i {
    position: absolute; left: 22px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted, #6c757d); font-size: 13px; pointer-events: none;
}
.cw-search-input, .cw-inbox-search {
    width: 100%; padding: 8px 12px 8px 36px;
    background: var(--input-bg, #f5f5f5);
    border: 1px solid var(--input-border, #e0e0e0);
    border-radius: 20px; outline: none;
    font-size: 13px; color: var(--input-color, #333);
    transition: border-color .2s;
}
.cw-search-input:focus, .cw-inbox-search:focus { border-color: #4361ee; }

/* ── User list (popover) ───────────────────────────────── */
.cw-user-list {
    list-style: none; margin: 0; padding: 6px 0;
    overflow-y: auto; max-height: 320px;
}
.cw-user-list::-webkit-scrollbar { width: 5px; }
.cw-user-list::-webkit-scrollbar-thumb { background: #ddd; border-radius: 3px; }
.cw-list-loading { padding: 20px; text-align: center; color: var(--text-muted,#888); font-size:13px; }
.cw-user-item {
    display: flex; align-items: center;
    gap: 10px; padding: 9px 14px;
    cursor: pointer; transition: background .15s;
    position: relative;
}
.cw-user-item:hover { background: var(--table-stripe, #f8f8f8); }
.cw-user-item.active { background: #eef1ff; }
body.dark-mode .cw-user-item.active { background: rgba(67,97,238,.15); }
.cw-avatar-wrap { position: relative; flex-shrink: 0; }
.cw-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    object-fit: cover; display: block;
    background: #dde;
}
.cw-status-dot {
    position: absolute; bottom: 1px; right: 1px;
    width: 11px; height: 11px; border-radius: 50%;
    border: 2px solid var(--chat-bg, #fff);
}
.cw-status-dot.online { background: #2dc653; }
.cw-status-dot.offline { background: #adb5bd; }
.cw-user-meta { flex: 1; min-width: 0; }
.cw-user-name { font-size: 13.5px; font-weight: 600; color: var(--text-primary,#222); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cw-user-status { font-size: 11.5px; color: var(--text-muted,#888); margin-top: 1px; }
.cw-user-status.online-text { color: #2dc653; }
.cw-unread-pill {
    background: #e63946; color: #fff;
    border-radius: 10px; padding: 2px 7px;
    font-size: 11px; font-weight: 700;
    min-width: 20px; text-align: center;
    flex-shrink: 0;
}

/* ── Mini chat window ──────────────────────────────────── */
.cw-chat-window {
    width: 320px;
    background: var(--chat-bg, #fff);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,.18);
    overflow: hidden;
    display: flex; flex-direction: column;
    border: 1px solid var(--card-border, rgba(0,0,0,.08));
    animation: cwSlideUp .2s ease;
}
.cw-chat-header {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--card-bg, #fff);
}
.cw-back-btn {
    background: none; border: none; outline: none;
    color: #4361ee; font-size: 14px; cursor: pointer;
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
    flex-shrink: 0;
}
.cw-back-btn:hover { background: #eef1ff; }
.cw-chat-avatar-wrap { position: relative; flex-shrink: 0; }
.cw-chat-avatar-wrap img {
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
}
.cw-chat-info { flex: 1; min-width: 0; }
.cw-chat-name { font-size: 13.5px; font-weight: 700; color: var(--text-primary,#222); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cw-chat-status { font-size: 11px; color: var(--text-muted,#888); }
.cw-chat-header-actions { display: flex; gap: 4px; flex-shrink: 0; }

/* Messages area */
.cw-messages-area {
    flex: 1; overflow-y: auto;
    padding: 12px 12px 6px;
    display: flex; flex-direction: column; gap: 4px;
    max-height: 280px;
    background: var(--body-bg, #f8f9fa);
}
.cw-messages-area::-webkit-scrollbar { width: 4px; }
.cw-messages-area::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }
.cw-msg-row { display: flex; flex-direction: column; margin-bottom: 2px; }
.cw-msg-row.sent { align-items: flex-end; }
.cw-msg-row.received { align-items: flex-start; }
.cw-bubble {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: 18px;
    font-size: 13.5px; line-height: 1.45;
    word-break: break-word;
}
.cw-msg-row.sent .cw-bubble {
    background: #4361ee; color: #fff;
    border-bottom-right-radius: 5px;
}
.cw-msg-row.received .cw-bubble {
    background: var(--chat-msg-received-bg, #fff);
    color: var(--chat-msg-received-color, #222);
    border: 1px solid var(--card-border, #e9ecef);
    border-bottom-left-radius: 5px;
}
.cw-bubble-time { font-size: 10px; opacity: .6; text-align: right; margin-top: 3px; }
.cw-msg-row.received .cw-bubble-time { text-align: left; }
.cw-no-msgs { text-align: center; padding: 30px 10px; color: var(--text-muted,#888); font-size: 13px; }
.cw-no-msgs i { font-size: 28px; display: block; margin-bottom: 8px; opacity: .4; }

/* Input bar */
.cw-input-bar {
    display: flex; align-items: flex-end; gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--chat-bg, #fff);
}
.cw-msg-input {
    flex: 1; border: 1px solid var(--input-border, #e0e0e0);
    border-radius: 20px; padding: 8px 14px;
    font-size: 13.5px; outline: none; resize: none;
    max-height: 80px; line-height: 1.4;
    background: var(--input-bg, #f5f5f5);
    color: var(--input-color, #333);
    font-family: inherit;
    transition: border-color .2s;
}
.cw-msg-input:focus { border-color: #4361ee; }
.cw-send-btn {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    border: none; outline: none; border-radius: 50%;
    color: #fff; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: transform .15s, box-shadow .15s;
}
.cw-send-btn:hover { transform: scale(1.08); box-shadow: 0 3px 12px rgba(67,97,238,.4); }

/* ── Inbox Overlay ─────────────────────────────────────── */
.cw-inbox-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(3px);
    z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    animation: cwFadeIn .2s ease;
}
@keyframes cwFadeIn { from { opacity:0; } to { opacity:1; } }
.cw-inbox {
    width: min(900px, 95vw);
    height: min(620px, 90vh);
    background: var(--chat-bg, #fff);
    border-radius: 20px;
    box-shadow: 0 24px 80px rgba(0,0,0,.25);
    display: flex; overflow: hidden;
    animation: cwZoomIn .22s ease;
    border: 1px solid var(--card-border, rgba(0,0,0,.1));
}
@keyframes cwZoomIn { from { opacity:0; transform:scale(.94); } to { opacity:1; transform:scale(1); } }

/* Inbox sidebar */
.cw-inbox-sidebar {
    width: 300px; flex-shrink: 0;
    border-right: 1px solid var(--card-border, rgba(0,0,0,.08));
    display: flex; flex-direction: column;
    background: var(--card-bg, #f9f9f9);
}
.cw-inbox-sidebar-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 16px 12px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
}
.cw-inbox-sidebar-header h4 {
    margin: 0; font-size: 20px; font-weight: 800;
    color: var(--text-primary, #111);
}
.cw-inbox-close-btn { font-size: 16px; }
.cw-inbox-search-wrap { padding: 10px 14px; }
.cw-inbox-search-wrap i { left: 26px; }
.cw-inbox-conv-list {
    list-style: none; margin: 0; padding: 4px 0;
    overflow-y: auto; flex: 1;
}
.cw-inbox-conv-list::-webkit-scrollbar { width: 4px; }
.cw-inbox-conv-list::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }
.cw-conv-item {
    display: flex; align-items: center; gap: 11px;
    padding: 10px 16px; cursor: pointer;
    transition: background .15s; position: relative;
}
.cw-conv-item:hover { background: var(--table-stripe, #f2f2f2); }
.cw-conv-item.active { background: #eef1ff; }
body.dark-mode .cw-conv-item.active { background: rgba(67,97,238,.18); }
.cw-conv-avatar { position: relative; flex-shrink: 0; }
.cw-conv-avatar img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
.cw-conv-meta { flex: 1; min-width: 0; }
.cw-conv-name { font-size: 14px; font-weight: 700; color: var(--text-primary,#222); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cw-conv-preview { font-size: 12px; color: var(--text-muted,#888); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.cw-conv-unread { background: #e63946; color: #fff; border-radius: 10px; padding: 2px 7px; font-size: 11px; font-weight: 700; min-width: 20px; text-align: center; }

/* Inbox main */
.cw-inbox-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.cw-inbox-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: var(--text-muted, #aaa); text-align: center; padding: 40px;
}
.cw-inbox-empty i { font-size: 56px; margin-bottom: 16px; opacity: .3; }
.cw-inbox-empty h5 { font-size: 20px; font-weight: 700; margin-bottom: 6px; color: var(--text-primary,#333); }
.cw-inbox-empty p { font-size: 14px; margin: 0; }

.cw-inbox-convo { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.cw-inbox-convo-header {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--card-bg, #fff);
    flex-shrink: 0;
}
.cw-inbox-convo-header .cw-chat-avatar-wrap img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.cw-inbox-convo-header .cw-chat-name { font-size: 16px; font-weight: 700; }
.cw-inbox-convo-header .cw-chat-status { font-size: 12px; }

.cw-inbox-messages {
    flex: 1; overflow-y: auto;
    padding: 20px; display: flex; flex-direction: column; gap: 6px;
    background: var(--body-bg, #f8f9fa);
}
.cw-inbox-messages::-webkit-scrollbar { width: 5px; }
.cw-inbox-messages::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }

.cw-inbox-input-bar {
    display: flex; align-items: flex-end; gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--chat-bg, #fff);
    flex-shrink: 0;
}
.cw-attach-btn {
    width: 36px; height: 36px;
    background: none; border: 1px solid var(--card-border, #ddd);
    border-radius: 50%; color: var(--text-muted, #888);
    font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background .15s, color .15s, border-color .15s;
}
.cw-attach-btn:hover { background: var(--table-stripe, #f5f5f5); color: #4361ee; border-color: #4361ee; }

/* Hidden file input */
.cw-file-input-hidden { display: none; }

/* ── File preview strip (above inbox input bar) ────────── */
.cw-file-preview-strip {
    display: flex; flex-wrap: wrap; gap: 8px;
    padding: 8px 16px;
    border-top: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--chat-bg, #fff);
    max-height: 120px; overflow-y: auto;
}
.cw-preview-item {
    position: relative; border-radius: 8px; overflow: hidden;
    border: 1px solid var(--card-border, #ddd);
    background: var(--table-stripe, #f8f8f8);
    display: flex; align-items: center;
}
.cw-preview-item.is-image { width: 72px; height: 72px; }
.cw-preview-item.is-file  { padding: 6px 10px; gap: 7px; max-width: 200px; }
.cw-preview-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cw-preview-icon { font-size: 20px; color: #4361ee; flex-shrink: 0; }
.cw-preview-file-info { min-width: 0; }
.cw-preview-file-name { font-size: 11px; font-weight: 600; color: var(--text-primary,#333); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.cw-preview-file-size { font-size: 10px; color: var(--text-muted,#888); margin-top: 1px; }
.cw-preview-remove {
    position: absolute; top: 2px; right: 2px;
    width: 18px; height: 18px; border-radius: 50%;
    background: rgba(0,0,0,.55); color: #fff;
    border: none; outline: none; cursor: pointer;
    font-size: 9px; line-height: 18px; text-align: center;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.cw-preview-remove:hover { background: #e63946; }

/* ── File bubble in message area ──────────────────────── */
.cw-file-bubble { display: flex; flex-direction: column; gap: 6px; }
.cw-file-bubble img.cw-img-msg {
    max-width: 220px; max-height: 200px;
    border-radius: 10px; display: block;
    cursor: pointer; object-fit: cover;
}
.cw-file-card {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.15);
    border-radius: 10px; padding: 8px 12px;
    text-decoration: none; color: inherit;
    border: 1px solid rgba(255,255,255,.25);
    min-width: 160px; max-width: 220px;
}
.cw-msg-row.received .cw-file-card {
    background: var(--table-stripe, #f2f2f2);
    border-color: var(--card-border, #ddd);
    color: var(--text-primary, #222);
}
.cw-file-card-icon { font-size: 22px; flex-shrink: 0; opacity: .85; }
.cw-file-card-info { min-width: 0; flex: 1; }
.cw-file-card-name { font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cw-file-card-size { font-size: 10px; opacity: .7; margin-top: 2px; }
.cw-file-card-dl { font-size: 14px; opacity: .75; flex-shrink: 0; }
.cw-file-card-dl:hover { opacity: 1; }

/* Upload progress indicator inside bubble */
.cw-upload-progress {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; opacity: .8; padding: 4px 0;
}
.cw-upload-progress .cw-progress-bar-wrap {
    flex: 1; height: 4px; background: rgba(255,255,255,.3);
    border-radius: 2px; overflow: hidden;
}
.cw-upload-progress .cw-progress-bar-fill {
    height: 100%; background: #fff;
    border-radius: 2px; transition: width .2s;
    width: 0%;
}

/* ── Message action buttons ────────────────────────────── */
.cw-msg-actions {
    display: none;
    gap: 4px;
    margin-top: 5px;
    justify-content: flex-end;
}
.cw-msg-row.sent:hover .cw-msg-actions { display: flex; }
.cw-msg-action-btn {
    background: rgba(255,255,255,.22);
    border: none; outline: none;
    border-radius: 50%;
    width: 24px; height: 24px;
    font-size: 11px; cursor: pointer;
    color: rgba(255,255,255,.85);
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, transform .1s;
}
.cw-msg-action-btn:hover { background: rgba(255,255,255,.38); transform: scale(1.1); }
.cw-delete-btn:hover { color: #ff6b6b; }

/* ── Deleted message bubble ────────────────────────────── */
.cw-msg-deleted .cw-bubble {
    background: transparent !important;
    border: 1.5px dashed var(--card-border, #ccc) !important;
    color: var(--text-muted, #999) !important;
    font-style: italic;
    padding: 6px 12px;
}
.cw-msg-deleted.sent .cw-bubble { border-color: rgba(255,255,255,.35) !important; }
.cw-deleted-text { font-size: 12.5px; display: flex; align-items: center; gap: 6px; }
.cw-deleted-text i { opacity: .65; }

/* ═══════════════════════════════════════════════════════
   SEEN INDICATOR
   ═══════════════════════════════════════════════════════ */
.cw-seen-indicator {
    font-size: 10px;
    color: var(--text-muted, #888);
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    /* Only shown on the last sent message */
}
.cw-seen-indicator.seen { color: #4361ee; }
.cw-seen-avatar {
    width: 14px; height: 14px; border-radius: 50%;
    object-fit: cover;
    border: 1px solid #fff;
}
body.dark-mode .cw-seen-avatar { border-color: var(--card-border); }

/* ═══════════════════════════════════════════════════════
   REACTION SYSTEM
   ═══════════════════════════════════════════════════════ */

/* Reaction picker — appears on hover over bubble */
.cw-bubble { position: relative; }

.cw-reaction-picker {
    display: none;
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--chat-bg, #fff);
    border: 1px solid var(--card-border, #e0e0e0);
    border-radius: 24px;
    padding: 5px 10px;
    gap: 5px;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    z-index: 30;
    white-space: nowrap;
    pointer-events: auto;
}
/* Sent messages: picker anchors to right edge */
.cw-msg-row.sent .cw-reaction-picker { left: auto; right: 0; }

/* Show picker on bubble hover */
.cw-bubble:hover .cw-reaction-picker { display: flex; }
/* Keep picker open while hovering it */
.cw-reaction-picker:hover { display: flex; }

.cw-reaction-picker button.cw-reaction-btn {
    font-size: 17px;
    cursor: pointer;
    transition: transform .15s, background .15s;
    line-height: 1;
    border-radius: 50%;
    padding: 4px;
    width: 30px; height: 30px;
    display: inline-flex; align-items: center; justify-content: center;
    background: none;
    border: none;
    outline: none;
    /* reset button defaults */
    -webkit-appearance: none;
    appearance: none;
}
.cw-reaction-picker button.cw-reaction-btn:hover {
    transform: scale(1.4);
    background: var(--table-stripe, rgba(0,0,0,.06));
}
.cw-reaction-picker button.cw-reaction-btn:focus-visible {
    outline: 2px solid #4361ee;
    outline-offset: 2px;
}

/* Reaction chips bar below the bubble */
.cw-reactions-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
    max-width: 220px;
}
.cw-msg-row.sent .cw-reactions-bar { justify-content: flex-end; }

.cw-reaction-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #f0f2ff;
    border: 1px solid #d0d5f5;
    border-radius: 12px;
    padding: 3px 8px;
    font-size: 12px;
    cursor: pointer;
    transition: background .15s, transform .1s, box-shadow .1s;
    user-select: none;
    line-height: 1.4;
    /* reset <button> defaults */
    -webkit-appearance: none;
    appearance: none;
    font-family: inherit;
    outline: none;
}
.cw-chip-count {
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
}
.cw-reaction-chip:hover { background: #dde1ff; transform: scale(1.05); box-shadow: 0 2px 8px rgba(67,97,238,.2); }
.cw-reaction-chip:focus-visible { outline: 2px solid #4361ee; outline-offset: 2px; }
.cw-reaction-chip.reacted {
    background: #4361ee;
    color: #fff;
    border-color: #4361ee;
}

/* Pulse animation when YOUR sent message gets a new reaction */
@keyframes cwReactionPulse {
    0%   { box-shadow: 0 0 0 0 rgba(67,97,238,.5); }
    70%  { box-shadow: 0 0 0 8px rgba(67,97,238,0); }
    100% { box-shadow: 0 0 0 0 rgba(67,97,238,0); }
}
.cw-bubble.cw-pulse { animation: cwReactionPulse 0.6s ease-out; }

/* Dark mode reaction */
body.dark-mode .cw-reaction-picker { background: var(--card-bg); border-color: var(--card-border); }
body.dark-mode .cw-reaction-chip { background: var(--table-stripe); border-color: var(--card-border); color: var(--text-primary); }
body.dark-mode .cw-reaction-chip.reacted { background: #4361ee; color: #fff; border-color: #4361ee; }

/* ═══════════════════════════════════════════════════════ */

body.dark-mode .cw-file-preview-strip { background: var(--chat-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-preview-item { background: var(--table-stripe) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-file-card { background: rgba(255,255,255,.07) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-msg-row.received .cw-file-card { background: var(--table-stripe) !important; border-color: var(--card-border) !important; }
.cw-inbox-msg-input { font-size: 14px; }
.cw-inbox-send-btn { width: 42px; height: 42px; font-size: 16px; }

/* Responsive */
@media (max-width: 640px) {
    .cw-inbox { flex-direction: column; width: 100vw; height: 100vh; border-radius: 0; }
    .cw-inbox-sidebar { width: 100%; height: 45%; border-right: none; border-bottom: 1px solid var(--card-border,rgba(0,0,0,.08)); }
    .cw-popover, .cw-chat-window { width: calc(100vw - 20px); }
    .cw-wrapper { right: 10px; bottom: 75px; }
}

/* ── SweetAlert above inbox overlay ─── */
.cw-swal-top.swal2-container { z-index: 9999 !important; }

/* ── In-conversation message search bar ─────────────────── */
.cw-msg-search-bar {
    padding: 7px 12px;
    border-bottom: 1px solid var(--card-border, rgba(0,0,0,.08));
    background: var(--card-bg, #fafafa);
    animation: cwSlideDown .18s ease;
}
@keyframes cwSlideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.cw-msg-search-inner {
    display: flex;
    align-items: center;
    gap: 5px;
    background: var(--input-bg, #f5f5f5);
    border: 1px solid var(--input-border, #ddd);
    border-radius: 20px;
    padding: 5px 10px;
    transition: border-color .2s;
}
.cw-msg-search-inner:focus-within { border-color: #4361ee; }
.cw-msg-search-icon {
    color: var(--text-muted, #888);
    font-size: 12px;
    flex-shrink: 0;
}
.cw-msg-search-field {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 13px;
    color: var(--input-color, #333);
    min-width: 0;
}
.cw-msg-search-counter {
    font-size: 11px;
    color: var(--text-muted, #999);
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 30px;
    text-align: center;
}
.cw-msg-search-nav, .cw-msg-search-clear {
    background: none;
    border: none;
    outline: none;
    cursor: pointer;
    color: var(--text-muted, #888);
    font-size: 11px;
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background .15s, color .15s;
    padding: 0;
}
.cw-msg-search-nav:hover, .cw-msg-search-clear:hover {
    background: var(--table-stripe, #eee);
    color: var(--text-primary, #333);
}
.cw-msg-search-nav:disabled { opacity: .35; cursor: default; }
/* Highlighted match */
.cw-search-highlight {
    background: #fff176;
    color: #111 !important;
    border-radius: 3px;
    padding: 0 1px;
}
/* Currently focused match */
.cw-search-highlight.cw-search-current {
    background: #ff9800;
    color: #fff !important;
}
/* Dark mode */
body.dark-mode .cw-msg-search-bar { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-msg-search-inner { background: var(--input-bg) !important; border-color: var(--input-border) !important; }
body.dark-mode .cw-msg-search-field { color: var(--input-color) !important; }
body.dark-mode .cw-search-highlight { background: #7c6f00; color: #ffe !important; }
body.dark-mode .cw-search-highlight.cw-search-current { background: #e65100; color: #fff !important; }

/* existing dark overrides */
body.dark-mode .cw-popover,
body.dark-mode .cw-chat-window,
body.dark-mode .cw-inbox { background: var(--chat-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-inbox-sidebar { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-popover-header,
body.dark-mode .cw-inbox-sidebar-header { border-color: var(--card-border) !important; }
body.dark-mode .cw-popover-title,
body.dark-mode .cw-inbox-sidebar-header h4 { color: var(--text-primary) !important; }
body.dark-mode .cw-user-item:hover,
body.dark-mode .cw-conv-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .cw-search-input,
body.dark-mode .cw-inbox-search { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .cw-messages-area,
body.dark-mode .cw-inbox-messages { background: var(--body-bg) !important; }
body.dark-mode .cw-msg-row.received .cw-bubble { background: var(--chat-msg-received-bg) !important; color: var(--chat-msg-received-color) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-msg-row.sent .cw-bubble { background: var(--chat-msg-sent, #4361ee) !important; }
body.dark-mode .cw-input-bar,
body.dark-mode .cw-inbox-input-bar { background: var(--chat-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-msg-input { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .cw-chat-header,
body.dark-mode .cw-inbox-convo-header { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .cw-status-dot { border-color: var(--chat-bg) !important; }
body.dark-mode .cw-inbox-overlay { background: rgba(0,0,0,.65); }
body.dark-mode .cw-msg-deleted .cw-bubble { border-color: var(--card-border) !important; }
body.dark-mode .cw-msg-action-btn { background: rgba(255,255,255,.12); }
body.dark-mode .cw-msg-action-btn:hover { background: rgba(255,255,255,.22); }
body.dark-mode .cw-seen-indicator { color: var(--text-muted); }
body.dark-mode .cw-seen-indicator.seen { color: #7aabdf; }

/* Ensure main content area has bottom spacing */
.content-wrapper,
.main-footer + .content-wrapper {
    padding-bottom: 70px !important;  /* Adjust if your footer height differs */
}

/* Footer should be relative, not fixed or absolute */
.main-footer {
    position: relative;
    clear: both;
    margin-top: 0;
    background: inherit; /* preserve existing background */
}

/* In case of wrapper layout, also push footer */
.wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.content-wrapper {
    flex: 1;
}

/* Prevent chat widget from overlapping footer (optional) */
.cw-wrapper {
    bottom: 90px !important; /* already set, but ensure it stays above */
}

/* Responsive adjustment */
@media (max-width: 768px) {
    .content-wrapper {
        padding-bottom: 80px !important;
    }
}
</style>

<!-- ═══════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════ -->
<script>
(function() {
'use strict';

// ── State ────────────────────────────────────────────────
const state = {
    currentUserId: <?php echo $_SESSION['emp_id'] ?? 0; ?>,
    allUsers: [],
    userUnreadCounts: new Map(),
    // Popover chat
    popover: {
        open: false,
        chatOpen: false,
        roomId: null,
        recipient: null,
        lastMsgId: 0,
        pollTimer: null,
    },
    // Inbox
    inbox: {
        open: false,
        roomId: null,
        recipient: null,
        lastMsgId: 0,
        pollTimer: null,
        activeUserId: null,
    },
    heartbeat: null,
    unreadTimer: null,
    // Reactions
    reactionSnapshot: {}, // msgId → total count (for pulse detection)
};

const AJAX = '../includes/chat_ajax.php';
const ALLOWED_EMOJIS = ['👍','❤️','😂','😮','😢','👏'];

// ── Helpers ──────────────────────────────────────────────
function post(data) {
    return $.post(AJAX, data, null, 'json');
}
function escHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}
function fmtTime(ts) {
    return new Date(ts).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
}
function avatarSrc(pic) {
    return pic ? `../dist/img/employees/${pic}` : '../dist/img/nialogo.png';
}
function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}
function scrollBottom(el) {
    el.scrollTop = el.scrollHeight;
}

// ── User list rendering ──────────────────────────────────
function renderUserList(users, container, clickFn) {
    const $c = $(container);
    $c.empty();
    if (!users.length) {
        $c.html('<li class="cw-list-loading">No users found</li>');
        return;
    }
    users.forEach(u => {
        const online = u.is_online == 1;
        const unread = state.userUnreadCounts.get(parseInt(u.emp_id)) || 0;
        const $li = $(`
            <li class="cw-user-item" data-uid="${u.emp_id}">
                <div class="cw-avatar-wrap">
                    <img class="cw-avatar" src="${avatarSrc(u.picture)}" alt="${escHtml(u.first_name)}" onerror="this.src='../dist/img/nialogo.png'">
                    <span class="cw-status-dot ${online ? 'online' : 'offline'}"></span>
                </div>
                <div class="cw-user-meta">
                    <div class="cw-user-name">${escHtml(u.first_name + ' ' + u.last_name)}</div>
                    <div class="cw-user-status ${online ? 'online-text' : ''}">${online ? 'Online' : 'Offline'}</div>
                </div>
                ${unread ? `<span class="cw-unread-pill">${unread > 99 ? '99+' : unread}</span>` : ''}
            </li>
        `);
        $li.on('click', () => clickFn(u));
        $c.append($li);
    });
}

// ── Messages rendering ───────────────────────────────────
const FILE_URL_BASE = '../uploads/chat/';

function formatBytes(b) {
    if (!b) return '';
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

function fileIcon(mime) {
    if (!mime) return 'fas fa-file';
    if (mime.startsWith('image/')) return 'fas fa-file-image';
    if (mime.startsWith('video/')) return 'fas fa-file-video';
    if (mime.startsWith('audio/')) return 'fas fa-file-audio';
    if (mime.includes('pdf'))      return 'fas fa-file-pdf';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word';
    if (mime.includes('excel') || mime.includes('sheet'))   return 'fas fa-file-excel';
    if (mime.includes('powerpoint') || mime.includes('presentation')) return 'fas fa-file-powerpoint';
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('7z')) return 'fas fa-file-archive';
    return 'fas fa-file-alt';
}

function buildMsgHtml(msg, currentUserId) {
    const sent = msg.sender_id == currentUserId;
    const time = fmtTime(msg.created_at);
    const deleted = msg.is_deleted == 1 || msg.is_deleted === true;

    // Build reaction picker HTML (prepended inside bubble)
    const pickerHtml = `<div class="cw-reaction-picker">
        ${ALLOWED_EMOJIS.map(e => `<button type="button" class="cw-reaction-btn" data-emoji="${e}" data-msgid="${msg.message_id}" aria-label="React with ${e}">${e}</button>`).join('')}
    </div>`;

    let bubbleContent;
    if (deleted) {
        const delText = sent ? 'You deleted this message' : 'This message was deleted';
        bubbleContent = `${pickerHtml}
                         <div class="cw-bubble-text cw-deleted-text"><i class="fas fa-ban"></i> ${delText}</div>
                         <div class="cw-bubble-time">${time}</div>`;
    } else if (msg.message_type === 'image' && msg.file_path) {
        const imgUrl = FILE_URL_BASE + escHtml(msg.file_path);
        const actions = sent ? `<div class="cw-msg-actions" data-msgid="${msg.message_id}">
                <button class="cw-msg-action-btn cw-delete-btn" title="Delete message"><i class="fas fa-trash-alt"></i></button>
            </div>` : '';
        bubbleContent = `${pickerHtml}<div class="cw-file-bubble">
            <img class="cw-img-msg" src="${imgUrl}" alt="${escHtml(msg.message)}" onclick="window.open(this.src,'_blank')">
            <div class="cw-bubble-time">${time}</div>
            ${actions}
        </div>`;
    } else if (msg.message_type === 'file' && msg.file_path) {
        const fileUrl = FILE_URL_BASE + escHtml(msg.file_path);
        const icon = fileIcon(msg.file_type || '');
        const sizeStr = formatBytes(msg.file_size);
        const actions = sent ? `<div class="cw-msg-actions" data-msgid="${msg.message_id}">
                <button class="cw-msg-action-btn cw-delete-btn" title="Delete message"><i class="fas fa-trash-alt"></i></button>
            </div>` : '';
        bubbleContent = `${pickerHtml}<div class="cw-file-bubble">
            <a class="cw-file-card" href="${fileUrl}" download="${escHtml(msg.message)}" target="_blank">
                <i class="${icon} cw-file-card-icon"></i>
                <div class="cw-file-card-info">
                    <div class="cw-file-card-name">${escHtml(msg.message)}</div>
                    ${sizeStr ? `<div class="cw-file-card-size">${sizeStr}</div>` : ''}
                </div>
                <i class="fas fa-download cw-file-card-dl"></i>
            </a>
            <div class="cw-bubble-time">${time}</div>
            ${actions}
        </div>`;
    } else {
        const actions = sent ? `
            <div class="cw-msg-actions" data-msgid="${msg.message_id}">
                <button class="cw-msg-action-btn cw-delete-btn" title="Delete message"><i class="fas fa-trash-alt"></i></button>
            </div>` : '';
        bubbleContent = `${pickerHtml}
            <div class="cw-bubble-text">${escHtml(msg.message)}</div>
            <div class="cw-bubble-time">${time}</div>
            ${actions}`;
    }

    // Seen placeholder — only injected for sent messages; updated by updateSeenIndicator()
    const seenHtml = sent ? `<div class="cw-seen-indicator" id="cw-seen-${msg.message_id}"></div>` : '';

    return `
        <div class="cw-msg-row ${sent ? 'sent' : 'received'} ${deleted ? 'cw-msg-deleted' : ''}" data-msgid="${msg.message_id}">
            <div class="cw-bubble">${bubbleContent}</div>
            <div class="cw-reactions-bar" id="cw-rxbar-${msg.message_id}"></div>
            ${seenHtml}
        </div>`;
}

function renderMessages(msgs, container, userId) {
    const $c = $(container);
    if (!msgs.length) {
        $c.html('<div class="cw-no-msgs"><i class="fas fa-comments"></i>No messages yet. Say hello!</div>');
        return;
    }
    $c.empty();
    msgs.forEach(m => $c.append(buildMsgHtml(m, userId)));
    scrollBottom(container);
}
function appendMessages(msgs, container, userId) {
    msgs.forEach(m => $(container).append(buildMsgHtml(m, userId)));
    scrollBottom(container);
}

// ═══════════════════════════════════════════════════════
// SEEN INDICATOR
// ═══════════════════════════════════════════════════════

/**
 * Fetch is_read status for the last sent message in the active room
 * and update the "Seen" indicator beneath it.
 * We reuse get_messages and check is_read on the last sent message.
 */
function updateSeenIndicator(container, roomId, recipientPic) {
    if (!roomId) return;

    // Find all sent message rows in this container
    const $rows = $(container).find('.cw-msg-row.sent[data-msgid]');
    if (!$rows.length) return;

    // Only show seen on the very last sent message
    // First clear all seen indicators
    $(container).find('.cw-seen-indicator').empty();

    const $lastSentRow = $rows.last();
    const lastSentId = parseInt($lastSentRow.data('msgid'));

    // Ask server: is this message read?
    post({ action: 'get_messages', room_id: roomId, last_message_id: lastSentId - 1 }).done(r => {
        // Actually we need a simpler check — use get_messages from 0 and find the last sent msg
    });

    // Better approach: fetch all messages and find our last sent one's is_read flag
    post({ action: 'get_messages', room_id: roomId, last_message_id: 0 }).done(r => {
        if (!r.success) return;
        const msgs = r.messages;
        // Find last sent message
        let lastSentMsg = null;
        for (let i = msgs.length - 1; i >= 0; i--) {
            if (msgs[i].sender_id == state.currentUserId) {
                lastSentMsg = msgs[i];
                break;
            }
        }
        if (!lastSentMsg) return;

        const $indicator = $(`#cw-seen-${lastSentMsg.message_id}`, container);
        if (!$indicator.length) return;

        if (lastSentMsg.is_read == 1) {
            const avatarHtml = recipientPic
                ? `<img class="cw-seen-avatar" src="${avatarSrc(recipientPic)}" alt="">`
                : '';
            $indicator.html(`${avatarHtml} <span>Seen</span>`).addClass('seen');
        } else {
            $indicator.html('<span>Sent</span>').removeClass('seen');
        }
    });
}

// ═══════════════════════════════════════════════════════
// REACTIONS
// ═══════════════════════════════════════════════════════

function fetchAndRenderReactions(roomId, container) {
    if (!roomId) return;
    post({ action: 'get_reactions', room_id: roomId }).done(r => {
        if (!r.success) return;
        const reactions = r.reactions || {};

        $(container).find('.cw-msg-row[data-msgid]').each(function() {
            const mid = parseInt($(this).data('msgid'));
            const $bar = $(`#cw-rxbar-${mid}`, container);
            if (!$bar.length) return;

            const isSent = $(this).hasClass('sent');

            // Pulse detection on sent messages
            if (isSent && reactions[mid]) {
                const newCount = Object.values(reactions[mid]).reduce((s, rx) => s + rx.count, 0);
                const prevCount = state.reactionSnapshot[mid] || 0;
                if (newCount > prevCount) {
                    $(this).find('.cw-bubble').addClass('cw-pulse');
                    setTimeout(() => $(this).find('.cw-bubble').removeClass('cw-pulse'), 700);
                }
                state.reactionSnapshot[mid] = newCount;
            }

            // Render chips
            $bar.empty();
            if (!reactions[mid]) return;
            Object.entries(reactions[mid]).forEach(([emoji, data]) => {
                const iMine = Array.isArray(data.users_ids) && data.users_ids.includes(state.currentUserId);
                const tooltip = data.users.join(', ');
                const $chip = $(`<button type="button" class="cw-reaction-chip ${iMine ? 'reacted' : ''}"
                                       data-msgid="${mid}"
                                       data-emoji="${emoji}"
                                       title="${tooltip}"
                                       aria-label="${tooltip} reacted with ${emoji}">
                                    ${emoji} <span class="cw-chip-count">${data.count}</span>
                                 </button>`);
                $bar.append($chip);
            });
        });
    });
}

// Toggle via picker emoji click
$(document).on('click', '.cw-reaction-picker button.cw-reaction-btn[data-emoji]', function(e) {
    e.stopPropagation();
    const emoji  = $(this).data('emoji');
    const msgId  = $(this).data('msgid');
    if (!emoji || !msgId) return;

    post({ action: 'toggle_reaction', message_id: msgId, emoji: emoji }).done(r => {
        if (!r.success) return;
        // Re-render reactions for the active panel
        const popRoom   = state.popover.roomId;
        const inboxRoom = state.inbox.roomId;
        if (popRoom)   fetchAndRenderReactions(popRoom,   document.getElementById('cwMessagesArea'));
        if (inboxRoom) fetchAndRenderReactions(inboxRoom, document.getElementById('cwInboxMessages'));
    });
});

// Toggle via chip click
$(document).on('click', 'button.cw-reaction-chip[data-emoji]', function(e) {
    e.stopPropagation();
    const msgId = $(this).data('msgid');
    const emoji = $(this).data('emoji');
    if (!msgId || !emoji) return;

    post({ action: 'toggle_reaction', message_id: msgId, emoji: emoji }).done(r => {
        if (!r.success) return;
        const popRoom   = state.popover.roomId;
        const inboxRoom = state.inbox.roomId;
        if (popRoom)   fetchAndRenderReactions(popRoom,   document.getElementById('cwMessagesArea'));
        if (inboxRoom) fetchAndRenderReactions(inboxRoom, document.getElementById('cwInboxMessages'));
    });
});

// ── Load all users ───────────────────────────────────────
function loadUsers() {
    return post({ action: 'get_online_users' }).done(r => {
        if (r.success) {
            state.allUsers = r.users;
        }
    });
}

// ── Unread counts ────────────────────────────────────────
function updateUnreadCounts() {
    post({ action: 'get_unread_counts' }).done(r => {
        if (!r.success) return;
        const total = r.total_unread || 0;
        const $badge = $('#cwFabBadge');
        if (total > 0) $badge.text(total > 99 ? '99+' : total).show();
        else $badge.hide();

        state.userUnreadCounts.clear();
        if (r.user_unread_counts) {
            Object.entries(r.user_unread_counts).forEach(([uid, cnt]) => {
                state.userUnreadCounts.set(parseInt(uid), cnt);
            });
        }
        if (state.popover.open && !state.popover.chatOpen) {
            renderUserList(state.allUsers, '#cwUserList', openPopoverChat);
        }
        if (state.inbox.open) {
            renderConvList(state.allUsers, '#cwInboxConvList');
        }
    });
}
function startUnreadTimer() {
    state.unreadTimer = setInterval(updateUnreadCounts, 5000);
    updateUnreadCounts();
}

// ── Heartbeat ────────────────────────────────────────────
function startHeartbeat() {
    post({ action: 'update_online_status' });
    state.heartbeat = setInterval(() => post({ action: 'update_online_status' }), 60000);
}

// ═══════════════ POPOVER ════════════════════════════════

function openPopover() {
    state.popover.open = true;
    $('#cwPopover').addClass('open');
    loadUsers().done(() => {
        renderUserList(state.allUsers, '#cwUserList', openPopoverChat);
        updateUnreadCounts();
    });
}
function closePopover() {
    state.popover.open = false;
    $('#cwPopover').removeClass('open');
}
function togglePopover() {
    if (state.popover.open) closePopover();
    else { closePopoverChat(); openPopover(); }
}

function openPopoverChat(user) {
    state.popover.recipient = user;
    state.popover.chatOpen = true;
    closePopover();
    const online = user.is_online == 1;
    $('#cwChatName').text(user.first_name + ' ' + user.last_name);
    $('#cwChatStatus').text(online ? 'Online' : 'Offline');
    $('#cwChatStatusDot').attr('class', `cw-status-dot ${online ? 'online' : 'offline'}`);
    $('#cwChatAvatar').attr('src', avatarSrc(user.picture));
    $('#cwChatWindow').show();
    $('#cwMessagesArea').html('<div class="cw-no-msgs"><i class="fas fa-circle-notch fa-spin"></i></div>');
    // Clear any active search when switching conversations
    msgSearchClose('cwMsgSearchBar','cwMsgSearchInput', popoverSearch,
        document.getElementById('cwMessagesArea'),
        'cwMsgSearchCounter','cwMsgSearchPrev','cwMsgSearchNext');

    post({ action: 'get_private_room', recipient_id: user.emp_id }).done(r => {
        if (r.success) {
            state.popover.roomId = r.room_id;
            state.popover.lastMsgId = 0;
            loadPopoverMessages(true);
            startPopoverPoll();
            post({ action: 'mark_read', room_id: r.room_id });
            state.userUnreadCounts.set(parseInt(user.emp_id), 0);
            updateUnreadCounts();
        }
    });
}

function closePopoverChat() {
    state.popover.chatOpen = false;
    state.popover.roomId = null;
    stopPopoverPoll();
    $('#cwChatWindow').hide();
}

function loadPopoverMessages(full) {
    if (!state.popover.roomId) return;
    const lastId = full ? 0 : state.popover.lastMsgId;
    post({ action: 'get_messages', room_id: state.popover.roomId, last_message_id: lastId }).done(r => {
        if (!r.success) return;
        const area = document.getElementById('cwMessagesArea');
        if (full) {
            renderMessages(r.messages, area, state.currentUserId);
        } else if (r.messages.length) {
            appendMessages(r.messages, area, state.currentUserId);
        }
        if (r.messages.length) {
            state.popover.lastMsgId = r.messages[r.messages.length - 1].message_id;
        }
        // Update reactions and seen after loading
        fetchAndRenderReactions(state.popover.roomId, area);
        updateSeenIndicator(area, state.popover.roomId, state.popover.recipient?.picture);
    });
}
function startPopoverPoll() {
    stopPopoverPoll();
    state.popover.pollTimer = setInterval(() => {
        loadPopoverMessages(false);
    }, 3000);
}
function stopPopoverPoll() {
    if (state.popover.pollTimer) { clearInterval(state.popover.pollTimer); state.popover.pollTimer = null; }
}

function sendPopoverMessage() {
    const $inp = $('#cwMsgInput');
    const msg = $inp.val().trim();
    if (!msg || !state.popover.roomId) return;
    $inp.val(''); autoGrow($inp[0]);
    post({ action: 'send_message', room_id: state.popover.roomId, message: msg }).done(r => {
        if (r.success) loadPopoverMessages(false);
    });
}

// ═══════════════ INBOX ══════════════════════════════════

function renderConvList(users, container) {
    const $c = $(container);
    $c.empty();
    if (!users.length) { $c.html('<li class="cw-list-loading">No conversations</li>'); return; }
    users.forEach(u => {
        const online = u.is_online == 1;
        const unread = state.userUnreadCounts.get(parseInt(u.emp_id)) || 0;
        const isActive = state.inbox.activeUserId == u.emp_id;
        const $li = $(`
            <li class="cw-conv-item ${isActive ? 'active' : ''}" data-uid="${u.emp_id}">
                <div class="cw-conv-avatar">
                    <img src="${avatarSrc(u.picture)}" alt="${escHtml(u.first_name)}" onerror="this.src='../dist/img/nialogo.png'">
                    <span class="cw-status-dot ${online ? 'online' : 'offline'}" style="border-color:var(--card-bg,#f9f9f9)"></span>
                </div>
                <div class="cw-conv-meta">
                    <div class="cw-conv-name">${escHtml(u.first_name + ' ' + u.last_name)}</div>
                    <div class="cw-conv-preview">${online ? '<span style="color:#2dc653">●</span> Online' : 'Offline'}</div>
                </div>
                ${unread ? `<span class="cw-conv-unread">${unread > 99 ? '99+' : unread}</span>` : ''}
            </li>`);
        $li.on('click', () => openInboxChat(u));
        $c.append($li);
    });
}

function openInbox(opts) {
    opts = opts || {};
    state.inbox.open = true;
    $('#cwInboxOverlay').show();
    loadUsers().done(() => {
        renderConvList(state.allUsers, '#cwInboxConvList');
        updateUnreadCounts();
        if (opts.user) openInboxChat(opts.user);
        else if (state.inbox.recipient) openInboxChat(state.inbox.recipient);
    });
    $('#cwInboxSearch').on('input', function() {
        const q = this.value.toLowerCase();
        const filtered = state.allUsers.filter(u =>
            (u.first_name + ' ' + u.last_name).toLowerCase().includes(q));
        renderConvList(filtered, '#cwInboxConvList');
    });
}
function closeInbox() {
    state.inbox.open = false;
    $('#cwInboxOverlay').hide();
    stopInboxPoll();
}

function openInboxChat(user) {
    state.inbox.recipient = user;
    state.inbox.activeUserId = user.emp_id;
    const online = user.is_online == 1;
    $('#cwInboxName').text(user.first_name + ' ' + user.last_name);
    $('#cwInboxStatus').text(online ? 'Online' : 'Offline');
    $('#cwInboxStatusDot').attr('class', `cw-status-dot ${online ? 'online' : 'offline'}`);
    $('#cwInboxAvatar').attr('src', avatarSrc(user.picture));
    $('#cwInboxEmpty').hide();
    $('#cwInboxConvo').show();
    $('#cwInboxMessages').html('<div class="cw-no-msgs"><i class="fas fa-circle-notch fa-spin"></i></div>');
    // Clear any active search when switching conversations
    msgSearchClose('cwInboxMsgSearchBar','cwInboxMsgSearchInput', inboxSearch,
        document.getElementById('cwInboxMessages'),
        'cwInboxMsgSearchCounter','cwInboxMsgSearchPrev','cwInboxMsgSearchNext');

    renderConvList(state.allUsers, '#cwInboxConvList');

    post({ action: 'get_private_room', recipient_id: user.emp_id }).done(r => {
        if (r.success) {
            state.inbox.roomId = r.room_id;
            state.inbox.lastMsgId = 0;
            loadInboxMessages(true);
            startInboxPoll();
            post({ action: 'mark_read', room_id: r.room_id });
            state.userUnreadCounts.set(parseInt(user.emp_id), 0);
            updateUnreadCounts();
        }
    });
}

function loadInboxMessages(full) {
    if (!state.inbox.roomId) return;
    const lastId = full ? 0 : state.inbox.lastMsgId;
    post({ action: 'get_messages', room_id: state.inbox.roomId, last_message_id: lastId }).done(r => {
        if (!r.success) return;
        const area = document.getElementById('cwInboxMessages');
        if (full) renderMessages(r.messages, area, state.currentUserId);
        else if (r.messages.length) appendMessages(r.messages, area, state.currentUserId);
        if (r.messages.length) state.inbox.lastMsgId = r.messages[r.messages.length - 1].message_id;

        // Update reactions and seen
        fetchAndRenderReactions(state.inbox.roomId, area);
        updateSeenIndicator(area, state.inbox.roomId, state.inbox.recipient?.picture);
    });
}
function startInboxPoll() {
    stopInboxPoll();
    state.inbox.pollTimer = setInterval(() => loadInboxMessages(false), 3000);
}
function stopInboxPoll() {
    if (state.inbox.pollTimer) { clearInterval(state.inbox.pollTimer); state.inbox.pollTimer = null; }
}

function sendInboxMessage() {
    const $inp = $('#cwInboxMsgInput');
    const msg = $inp.val().trim();
    if (!msg || !state.inbox.roomId) return;
    $inp.val(''); autoGrow($inp[0]);
    post({ action: 'send_message', room_id: state.inbox.roomId, message: msg }).done(r => {
        if (r.success) loadInboxMessages(false);
    });
}

// ═══════════════ IN-CONVERSATION MESSAGE SEARCH ══════════

/**
 * Generic search engine for a messages container.
 * @param {string}  query       - search term
 * @param {Element} container   - the scrollable messages div
 * @param {Object}  searchState - { matches: [], currentIdx: int }
 * @param {string}  counterId   - id of the counter <span>
 * @param {string}  prevBtnId   - id of prev <button>
 * @param {string}  nextBtnId   - id of next <button>
 */
function msgSearchRun(query, container, searchState, counterId, prevBtnId, nextBtnId) {
    // Clear previous highlights
    msgSearchClearHighlights(container);
    searchState.matches = [];
    searchState.currentIdx = -1;

    const $counter = $('#' + counterId);
    const $prev = $('#' + prevBtnId);
    const $next = $('#' + nextBtnId);

    if (!query || query.length < 1) {
        $counter.text('');
        $prev.prop('disabled', true);
        $next.prop('disabled', true);
        return;
    }

    const term = query.toLowerCase();

    // Walk text nodes inside .cw-bubble-text and text bubbles
    // We target the .cw-bubble elements but avoid pickers / action buttons
    $(container).find('.cw-msg-row:not(.cw-msg-deleted) .cw-bubble').each(function() {
        // Collect all direct text-holding children (not reaction pickers, not action wrappers)
        $(this).find('.cw-bubble-text, .cw-bubble-time').filter('.cw-bubble-text').each(function() {
            highlightTextNode(this, term, searchState.matches);
        });
        // Also handle plain text messages that don't have .cw-bubble-text wrapper
        // (older messages might have raw text)
    });

    // Also search inside plain text bubbles (no .cw-bubble-text wrapper — direct text)
    // We do a second pass via TreeWalker on each bubble
    $(container).find('.cw-msg-row:not(.cw-msg-deleted) .cw-bubble').each(function() {
        walkAndHighlight(this, term, searchState.matches);
    });

    // Deduplicate matches array (TreeWalker + jQuery may overlap — just keep unique spans)
    const seen = new Set();
    searchState.matches = searchState.matches.filter(el => {
        if (seen.has(el)) return false;
        seen.add(el);
        return true;
    });

    const count = searchState.matches.length;
    if (count === 0) {
        $counter.text('No results');
        $prev.prop('disabled', true);
        $next.prop('disabled', true);
        return;
    }

    $prev.prop('disabled', false);
    $next.prop('disabled', false);
    msgSearchGoTo(0, searchState, container, counterId);
}

/**
 * Walk all text nodes inside `el`, wrap matches in <mark class="cw-search-highlight">.
 * Pushes mark elements into `matches`.
 */
function walkAndHighlight(el, term, matches) {
    // Skip elements we already handled or that shouldn't be searched
    const skipTags = new Set(['SCRIPT','STYLE','INPUT','TEXTAREA','BUTTON','IMG']);
    const skipClasses = ['cw-reaction-picker','cw-msg-actions','cw-bubble-time','cw-reactions-bar','cw-seen-indicator'];

    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            // Skip if inside a skip-class parent
            let p = node.parentElement;
            while (p && p !== el) {
                if (skipTags.has(p.tagName)) return NodeFilter.FILTER_REJECT;
                for (const cls of skipClasses) {
                    if (p.classList.contains(cls)) return NodeFilter.FILTER_REJECT;
                }
                // Skip already-highlighted nodes
                if (p.classList.contains('cw-search-highlight')) return NodeFilter.FILTER_REJECT;
                p = p.parentElement;
            }
            return node.textContent.toLowerCase().includes(term)
                ? NodeFilter.FILTER_ACCEPT
                : NodeFilter.FILTER_SKIP;
        }
    });

    const nodesToReplace = [];
    let node;
    while ((node = walker.nextNode())) nodesToReplace.push(node);

    nodesToReplace.forEach(textNode => {
        const text = textNode.textContent;
        const lc   = text.toLowerCase();
        const frag = document.createDocumentFragment();
        let last = 0, idx;
        while ((idx = lc.indexOf(term, last)) !== -1) {
            if (idx > last) frag.appendChild(document.createTextNode(text.slice(last, idx)));
            const mark = document.createElement('mark');
            mark.className = 'cw-search-highlight';
            mark.textContent = text.slice(idx, idx + term.length);
            frag.appendChild(mark);
            matches.push(mark);
            last = idx + term.length;
        }
        if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
        textNode.parentNode.replaceChild(frag, textNode);
    });
}

// Legacy helper kept for safety (no-op now, walkAndHighlight does the work)
function highlightTextNode(el, term, matches) {}

/** Remove all highlights from a container, restoring plain text */
function msgSearchClearHighlights(container) {
    $(container).find('mark.cw-search-highlight').each(function() {
        const parent = this.parentNode;
        parent.replaceChild(document.createTextNode(this.textContent), this);
        parent.normalize();
    });
}

/** Jump to a specific match index */
function msgSearchGoTo(idx, searchState, container, counterId) {
    const count = searchState.matches.length;
    if (!count) return;
    // Clamp with wrap-around
    searchState.currentIdx = ((idx % count) + count) % count;
    // Clear current highlight on old
    searchState.matches.forEach(m => m.classList.remove('cw-search-current'));
    const target = searchState.matches[searchState.currentIdx];
    target.classList.add('cw-search-current');
    // Scroll into view
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
    $('#' + counterId).text((searchState.currentIdx + 1) + ' / ' + count);
}

/** Close search: hide bar, clear highlights, reset state */
function msgSearchClose(barId, inputId, searchState, container, counterId, prevBtnId, nextBtnId) {
    msgSearchClearHighlights(container);
    searchState.matches = [];
    searchState.currentIdx = -1;
    $('#' + barId).hide();
    $('#' + inputId).val('');
    $('#' + counterId).text('');
    $('#' + prevBtnId).prop('disabled', true);
    $('#' + nextBtnId).prop('disabled', true);
}

// Per-panel search state objects
const popoverSearch = { matches: [], currentIdx: -1 };
const inboxSearch   = { matches: [], currentIdx: -1 };

// ═══════════════ DELETE & FILE UPLOAD ════════════════════

function handleDeleteMessage(msgId, $msgRow) {
    Swal.fire({
        title: 'Delete message?',
        text: 'This will be removed for everyone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e63946',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        customClass: { container: 'cw-swal-top' },
    }).then(result => {
        if (!result.isConfirmed) return;
        post({ action: 'delete_message', message_id: msgId }).done(r => {
            if (r.success) {
                $msgRow.addClass('cw-msg-deleted');
                $msgRow.find('.cw-bubble').html(
                    `<div class="cw-bubble-text cw-deleted-text"><i class="fas fa-ban"></i> You deleted this message</div>`
                );
                $msgRow.find('.cw-msg-actions').remove();
            } else {
                toastr.error(r.error || 'Could not delete message.');
            }
        });
    });
}

// ── File queue ───────────────────────────────────────────
const pendingFiles = { popover: [], inbox: [] };

function formatBytesLocal(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

function fileIconClass(name) {
    const ext = name.split('.').pop().toLowerCase();
    const map = {
        pdf:'fas fa-file-pdf', doc:'fas fa-file-word', docx:'fas fa-file-word',
        xls:'fas fa-file-excel', xlsx:'fas fa-file-excel',
        ppt:'fas fa-file-powerpoint', pptx:'fas fa-file-powerpoint',
        zip:'fas fa-file-archive', rar:'fas fa-file-archive', '7z':'fas fa-file-archive',
        mp4:'fas fa-file-video', mov:'fas fa-file-video', avi:'fas fa-file-video',
        mp3:'fas fa-file-audio', wav:'fas fa-file-audio',
        txt:'fas fa-file-alt', csv:'fas fa-file-csv',
    };
    const imgExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
    if (imgExts.includes(ext)) return 'fas fa-file-image';
    return map[ext] || 'fas fa-file-alt';
}

function addFilesToQueue(files, panel) {
    const MAX = 50 * 1024 * 1024;
    let rejected = 0;
    Array.from(files).forEach(f => {
        if (f.size > MAX) { rejected++; return; }
        pendingFiles[panel].push(f);
    });
    if (rejected) toastr.warning(`${rejected} file(s) exceeded 50 MB and were skipped.`);
    renderFilePreview(panel);
}

function renderFilePreview(panel) {
    if (panel !== 'inbox') return;
    const $strip = $('#cwInboxFilePreview');
    const files = pendingFiles.inbox;
    if (!files.length) { $strip.hide().empty(); return; }
    $strip.empty().show();
    files.forEach((f, idx) => {
        const isImg = f.type.startsWith('image/');
        const $item = $(`<div class="cw-preview-item ${isImg ? 'is-image' : 'is-file'}"></div>`);
        if (isImg) {
            const reader = new FileReader();
            reader.onload = e => $item.prepend(`<img class="cw-preview-img" src="${e.target.result}">`);
            reader.readAsDataURL(f);
        } else {
            $item.append(`<i class="${fileIconClass(f.name)} cw-preview-icon"></i>
                <div class="cw-preview-file-info">
                    <div class="cw-preview-file-name">${escHtml(f.name)}</div>
                    <div class="cw-preview-file-size">${formatBytesLocal(f.size)}</div>
                </div>`);
        }
        const $rm = $(`<button class="cw-preview-remove" title="Remove"><i class="fas fa-times"></i></button>`);
        $rm.on('click', () => { pendingFiles.inbox.splice(idx, 1); renderFilePreview('inbox'); });
        $item.append($rm);
        $strip.append($item);
    });
}

function uploadFiles(panel, roomId) {
    const files = pendingFiles[panel].slice();
    if (!files.length) return;
    pendingFiles[panel] = [];
    renderFilePreview(panel);

    const area = panel === 'inbox'
        ? document.getElementById('cwInboxMessages')
        : document.getElementById('cwMessagesArea');

    files.forEach(f => {
        const placeholderId = 'upload-' + Date.now() + '-' + Math.random().toString(36).slice(2);
        const $placeholder = $(`
            <div class="cw-msg-row sent" id="${placeholderId}">
                <div class="cw-bubble">
                    <div class="cw-upload-progress">
                        <i class="${fileIconClass(f.name)}"></i>
                        <span>${escHtml(f.name)}</span>
                        <div class="cw-progress-bar-wrap"><div class="cw-progress-bar-fill"></div></div>
                    </div>
                </div>
            </div>`);
        $(area).append($placeholder);
        scrollBottom(area);

        const fd = new FormData();
        fd.append('action', 'send_file');
        fd.append('room_id', roomId);
        fd.append('file', f);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX);
        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                $placeholder.find('.cw-progress-bar-fill').css('width', pct + '%');
            }
        };
        xhr.onload = () => {
            $placeholder.remove();
            try {
                const r = JSON.parse(xhr.responseText);
                if (r.success) {
                    if (panel === 'inbox' && r.message_id > state.inbox.lastMsgId) {
                        post({ action: 'get_messages', room_id: roomId, last_message_id: r.message_id - 1 }).done(res => {
                            if (res.success && res.messages.length) {
                                const m = res.messages.find(x => x.message_id == r.message_id);
                                if (m) {
                                    $(area).append(buildMsgHtml(m, state.currentUserId));
                                    scrollBottom(area);
                                    state.inbox.lastMsgId = r.message_id;
                                }
                            }
                        });
                    } else if (panel === 'popover' && r.message_id > state.popover.lastMsgId) {
                        post({ action: 'get_messages', room_id: roomId, last_message_id: r.message_id - 1 }).done(res => {
                            if (res.success && res.messages.length) {
                                const m = res.messages.find(x => x.message_id == r.message_id);
                                if (m) {
                                    $(area).append(buildMsgHtml(m, state.currentUserId));
                                    scrollBottom(area);
                                    state.popover.lastMsgId = r.message_id;
                                }
                            }
                        });
                    }
                } else {
                    toastr.error(r.error || 'Upload failed.');
                }
            } catch(e) {
                toastr.error('Upload failed.');
            }
        };
        xhr.onerror = () => { $placeholder.remove(); toastr.error('Upload failed.'); };
        xhr.send(fd);
    });
}

// Delete delegated handler
$(document).on('click', '.cw-delete-btn', function(e) {
    e.stopPropagation();
    const $row = $(this).closest('.cw-msg-row');
    const msgId = $(this).closest('.cw-msg-actions').data('msgid');
    handleDeleteMessage(msgId, $row);
});

// ═══════════════ EVENTS ══════════════════════════════════

$(document).ready(function() {
    <?php if (isset($_SESSION['emp_id'])): ?>

    startHeartbeat();
    startUnreadTimer();

    // FAB toggle
    $('#cwFab').on('click', function(e) { e.stopPropagation(); togglePopover(); });

    // Popover close
    $('#cwClosePopover').on('click', closePopover);

    // Open inbox from popover header or chat header
    $('#cwOpenInbox, #cwMaximizeFromChat').on('click', function() {
        const user = state.popover.recipient;
        closePopoverChat();
        closePopover();
        openInbox(user ? { user } : {});
    });

    // Back to user list in popover
    $('#cwBackBtn').on('click', function() {
        closePopoverChat();
        openPopover();
    });

    // Popover search
    $('#cwSearch').on('input', function() {
        const q = this.value.toLowerCase();
        const filtered = state.allUsers.filter(u =>
            (u.first_name + ' ' + u.last_name).toLowerCase().includes(q));
        renderUserList(filtered, '#cwUserList', openPopoverChat);
    });

    // Popover send
    $('#cwSendBtn').on('click', sendPopoverMessage);
    $('#cwMsgInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendPopoverMessage(); }
    }).on('input', function() { autoGrow(this); });

    // Inbox close
    $('#cwInboxClose').on('click', closeInbox);
    $('#cwInboxOverlay').on('click', function(e) {
        if (e.target === this) closeInbox();
    });

    // Inbox send
    $('#cwInboxSendBtn').on('click', sendInboxMessage);
    $('#cwInboxMsgInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendInboxMessage(); }
    }).on('input', function() { autoGrow(this); });

    // ── File attach — popover ────────────────────────────
    $('.cw-popover-attach').on('click', function() { $('#cwFileInput').val('').trigger('click'); });
    $('#cwFileInput').on('change', function() {
        if (!this.files.length) return;
        if (!state.popover.roomId) { toastr.warning('Open a chat first.'); return; }
        addFilesToQueue(this.files, 'popover');
        uploadFiles('popover', state.popover.roomId);
    });

    // ── File attach — inbox ──────────────────────────────
    $('.cw-inbox-attach').on('click', function() { $('#cwInboxFileInput').val('').trigger('click'); });
    $('#cwInboxFileInput').on('change', function() {
        if (!this.files.length) return;
        addFilesToQueue(this.files, 'inbox');
    });

    // Send inbox files when send btn clicked (handles mixed text+files)
    const origSendInbox = sendInboxMessage;
    $('#cwInboxSendBtn').off('click').on('click', function() {
        if (pendingFiles.inbox.length && state.inbox.roomId) {
            uploadFiles('inbox', state.inbox.roomId);
        }
        origSendInbox();
    });
    $('#cwInboxMsgInput').off('keydown').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (pendingFiles.inbox.length && state.inbox.roomId) uploadFiles('inbox', state.inbox.roomId);
            origSendInbox();
        }
    }).on('input', function() { autoGrow(this); });

    // Drag-and-drop
    $('#cwInboxMessages, #cwMessagesArea').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('cw-drag-over');
    }).on('dragleave drop', function(e) {
        $(this).removeClass('cw-drag-over');
    });
    $('#cwInboxMessages').on('drop', function(e) {
        e.preventDefault();
        const files = e.originalEvent.dataTransfer.files;
        if (files.length && state.inbox.roomId) { addFilesToQueue(files, 'inbox'); }
    });
    $('#cwMessagesArea').on('drop', function(e) {
        e.preventDefault();
        const files = e.originalEvent.dataTransfer.files;
        if (files.length && state.popover.roomId) { addFilesToQueue(files, 'popover'); uploadFiles('popover', state.popover.roomId); }
    });

    // Close popover when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#cwWrapper').length) {
            if (state.popover.open) closePopover();
        }
    });

    // ── In-conversation message search — POPOVER ─────────
    $('#cwMsgSearchToggle').on('click', function(e) {
        e.stopPropagation();
        const $bar = $('#cwMsgSearchBar');
        if ($bar.is(':visible')) {
            msgSearchClose('cwMsgSearchBar','cwMsgSearchInput', popoverSearch,
                document.getElementById('cwMessagesArea'),
                'cwMsgSearchCounter','cwMsgSearchPrev','cwMsgSearchNext');
        } else {
            $bar.show();
            $('#cwMsgSearchInput').focus();
        }
    });
    $('#cwMsgSearchInput').on('input', function() {
        msgSearchRun(this.value.trim(), document.getElementById('cwMessagesArea'),
            popoverSearch, 'cwMsgSearchCounter', 'cwMsgSearchPrev', 'cwMsgSearchNext');
    }).on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (e.shiftKey) msgSearchGoTo(popoverSearch.currentIdx - 1, popoverSearch, document.getElementById('cwMessagesArea'), 'cwMsgSearchCounter');
            else            msgSearchGoTo(popoverSearch.currentIdx + 1, popoverSearch, document.getElementById('cwMessagesArea'), 'cwMsgSearchCounter');
        }
        if (e.key === 'Escape') {
            msgSearchClose('cwMsgSearchBar','cwMsgSearchInput', popoverSearch,
                document.getElementById('cwMessagesArea'),
                'cwMsgSearchCounter','cwMsgSearchPrev','cwMsgSearchNext');
        }
    });
    $('#cwMsgSearchPrev').on('click', function() {
        msgSearchGoTo(popoverSearch.currentIdx - 1, popoverSearch, document.getElementById('cwMessagesArea'), 'cwMsgSearchCounter');
    });
    $('#cwMsgSearchNext').on('click', function() {
        msgSearchGoTo(popoverSearch.currentIdx + 1, popoverSearch, document.getElementById('cwMessagesArea'), 'cwMsgSearchCounter');
    });
    $('#cwMsgSearchClose').on('click', function() {
        msgSearchClose('cwMsgSearchBar','cwMsgSearchInput', popoverSearch,
            document.getElementById('cwMessagesArea'),
            'cwMsgSearchCounter','cwMsgSearchPrev','cwMsgSearchNext');
    });

    // ── In-conversation message search — INBOX ───────────
    $('#cwInboxMsgSearchToggle').on('click', function(e) {
        e.stopPropagation();
        const $bar = $('#cwInboxMsgSearchBar');
        if ($bar.is(':visible')) {
            msgSearchClose('cwInboxMsgSearchBar','cwInboxMsgSearchInput', inboxSearch,
                document.getElementById('cwInboxMessages'),
                'cwInboxMsgSearchCounter','cwInboxMsgSearchPrev','cwInboxMsgSearchNext');
        } else {
            $bar.show();
            $('#cwInboxMsgSearchInput').focus();
        }
    });
    $('#cwInboxMsgSearchInput').on('input', function() {
        msgSearchRun(this.value.trim(), document.getElementById('cwInboxMessages'),
            inboxSearch, 'cwInboxMsgSearchCounter', 'cwInboxMsgSearchPrev', 'cwInboxMsgSearchNext');
    }).on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (e.shiftKey) msgSearchGoTo(inboxSearch.currentIdx - 1, inboxSearch, document.getElementById('cwInboxMessages'), 'cwInboxMsgSearchCounter');
            else            msgSearchGoTo(inboxSearch.currentIdx + 1, inboxSearch, document.getElementById('cwInboxMessages'), 'cwInboxMsgSearchCounter');
        }
        if (e.key === 'Escape') {
            msgSearchClose('cwInboxMsgSearchBar','cwInboxMsgSearchInput', inboxSearch,
                document.getElementById('cwInboxMessages'),
                'cwInboxMsgSearchCounter','cwInboxMsgSearchPrev','cwInboxMsgSearchNext');
        }
    });
    $('#cwInboxMsgSearchPrev').on('click', function() {
        msgSearchGoTo(inboxSearch.currentIdx - 1, inboxSearch, document.getElementById('cwInboxMessages'), 'cwInboxMsgSearchCounter');
    });
    $('#cwInboxMsgSearchNext').on('click', function() {
        msgSearchGoTo(inboxSearch.currentIdx + 1, inboxSearch, document.getElementById('cwInboxMessages'), 'cwInboxMsgSearchCounter');
    });
    $('#cwInboxMsgSearchClose').on('click', function() {
        msgSearchClose('cwInboxMsgSearchBar','cwInboxMsgSearchInput', inboxSearch,
            document.getElementById('cwInboxMessages'),
            'cwInboxMsgSearchCounter','cwInboxMsgSearchPrev','cwInboxMsgSearchNext');
    });

    // Set offline on unload
    $(window).on('beforeunload', function() {
        $.ajax({ url: AJAX, type: 'POST', async: false, data: { action: 'set_offline' } });
    });

    // Initial status update
    post({ action: 'update_online_status' });

    <?php endif; ?>
});

// Expose for external use
window.chatSystem = {
    openInbox: openInbox,
    openPopover: openPopover,
};

})();
</script>

<style>
/* Ensure chat widget clears the footer */
.cw-wrapper { z-index: 1051 !important; }
@media (max-width: 768px) {
    .cw-wrapper { bottom: 75px !important; right: 12px !important; }
}
</style>
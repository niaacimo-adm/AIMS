<!-- Google Font: Source Sans Pro -->
   <script src="../plugins/jquery/jquery.min.js"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <!-- IonIcons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <!-- Toastr -->
  <link rel="stylesheet" href="../plugins/toastr/toastr.min.css">
  <!-- Theme style -->
   <style>
.custom-file-input:lang(en)~.custom-file-label::after {
    content: "Browse";
}
#preview {
    max-width: 100%;
    height: auto;
}
/* Chat Widget Styles */
.chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.chat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    border: none;
}

.chat-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.chat-modal {
    display: none;
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    z-index: 1001;
    flex-direction: column;
    overflow: hidden;
}

.chat-header {
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header h5 {
    margin: 0;
    font-size: 16px;
}

.close-chat {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.chat-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.chat-users-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.user-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.users-header {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.search-container {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 14px;
}

.user-search {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}

.user-search:focus {
    border-color: #4361ee;
}

.users-list-container {
    flex: 1;
    overflow-y: auto;
    max-height: 300px;
}

/* User List */
.user-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s;
    position: relative;
}

.user-item:hover {
    background: #f8f9fa;
}

.user-item:last-child {
    border-bottom: none;
}

.user-avatar-container {
    position: relative;
    margin-right: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.default-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 500;
    font-size: 14px;
    color: #333;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-status {
    font-size: 12px;
    display: flex;
    align-items: center;
}

.status-online {
    color: #28a745;
}

.status-offline {
    color: #6c757d;
}

.user-unread-badge {
    background: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 500;
    min-width: 18px;
    text-align: center;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
}

/* Chat Panel */
.chat-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.active-chat-header {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    background: white;
}

.back-to-users {
    background: none;
    border: none;
    color: #4361ee;
    font-size: 16px;
    cursor: pointer;
    padding: 8px;
    margin-right: 10px;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.back-to-users:hover {
    background: #f8f9fa;
}

.active-user-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.active-user-avatar {
    position: relative;
    margin-right: 12px;
}

.active-user-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.active-user-status {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border: 2px solid white;
    border-radius: 50%;
}

.active-user-status.online {
    background: #28a745;
}

.active-user-status.offline {
    background: #6c757d;
}

.active-user-details {
    flex: 1;
}

.active-user-name {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

.active-user-status-text {
    font-size: 12px;
    color: #6c757d;
}

/* Messages Container */
.chat-messages-container {
    flex: 1;
    overflow-y: auto;
    background: #f8f9fa;
    max-height: 300px;
}

.chat-messages {
    padding: 15px;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.no-messages {
    text-align: center;
    color: #6c757d;
    padding: 40px 20px;
}

.no-messages i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.no-messages p {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.no-messages small {
    font-size: 12px;
    opacity: 0.7;
}

/* Messages */
.message {
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
}

.message.sent {
    justify-content: flex-end;
}

.message.received {
    justify-content: flex-start;
}

.message-bubble {
    max-width: 80%;
    padding: 10px 15px;
    border-radius: 18px;
    position: relative;
}

.message.sent .message-bubble {
    background: #4361ee;
    color: white;
    border-bottom-right-radius: 5px;
}

.message.received .message-bubble {
    background: white;
    color: #333;
    border: 1px solid #e9ecef;
    border-bottom-left-radius: 5px;
}

.message-sender {
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 2px;
}

.message-time {
    font-size: 10px;
    opacity: 0.7;
    text-align: right;
    margin-top: 5px;
}

/* Input Container */
.chat-input-container {
    padding: 15px;
    border-top: 1px solid #e9ecef;
    background: white;
}

.input-group {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.message-input {
    flex: 1;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    padding: 10px 15px;
    outline: none;
    resize: none;
    height: 40px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.message-input:focus {
    border-color: #4361ee;
}

.send-btn {
    background: linear-gradient(135deg, #4361ee, #3f37c9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.send-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 3px 10px rgba(67, 97, 238, 0.3);
}

/* No Chat Selected */
.no-chat-selected {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
    text-align: center;
    padding: 40px 20px;
}

.no-chat-selected i {
    opacity: 0.5;
    margin-bottom: 15px;
}

.no-chat-selected h5 {
    margin: 0 0 8px 0;
    font-weight: 600;
}

.no-chat-selected p {
    margin: 0;
    font-size: 14px;
    opacity: 0.7;
}

/* Online indicator */
.online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border: 2px solid white;
    border-radius: 50%;
    box-shadow: 0 0 3px rgba(0,0,0,0.3);
}

.online {
    background: #28a745;
}

.offline {
    background: #6c757d;
}

/* Scrollbar styling */
.users-list-container::-webkit-scrollbar,
.chat-messages-container::-webkit-scrollbar {
    width: 6px;
}

.users-list-container::-webkit-scrollbar-track,
.chat-messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.users-list-container::-webkit-scrollbar-thumb,
.chat-messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.users-list-container::-webkit-scrollbar-thumb:hover,
.chat-messages-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.typing-indicator {
    font-style: italic;
    color: #6c757d;
    font-size: 12px;
    padding: 5px 15px;
}


/* =========================================================
   HEADER.PHP CHAT WIDGET — Dark Mode Overrides
   ========================================================= */
body.dark-mode .chat-modal { background: var(--chat-bg) !important; }
body.dark-mode .chat-header { background: var(--chat-header-bg) !important; color: var(--chat-header-color) !important; }
body.dark-mode .users-header { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .user-search { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .user-item { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .user-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .user-name { color: var(--text-primary) !important; }
body.dark-mode .user-status { color: var(--text-muted) !important; }
body.dark-mode .chat-messages-container { background: var(--body-bg) !important; }
body.dark-mode .message.received .message-bubble { background: var(--chat-msg-received-bg) !important; color: var(--chat-msg-received-color) !important; border-color: var(--card-border) !important; }
body.dark-mode .message.sent .message-bubble { background: var(--chat-msg-sent) !important; color: #fff !important; }
body.dark-mode .chat-input-container { background: var(--chat-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .message-input { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .active-chat-header { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .active-user-name { color: var(--text-primary) !important; }
body.dark-mode .active-user-status-text { color: var(--text-muted) !important; }
body.dark-mode .no-chat-selected { color: var(--text-muted) !important; background: var(--card-bg) !important; }
body.dark-mode .no-messages { color: var(--text-muted) !important; }
body.dark-mode .typing-indicator { color: var(--text-muted) !important; }
body.dark-mode .search-icon { color: var(--text-muted) !important; }
body.dark-mode .back-to-users { color: #7aabdf !important; }
body.dark-mode .back-to-users:hover { background: var(--table-stripe) !important; }
body.dark-mode .message-time { opacity: 0.7; }


</style>
  <link rel="stylesheet" href="../plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="../plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
   <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
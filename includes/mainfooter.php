<!-- Main Footer -->
<footer class="main-footer" id="mainFooter">
    <strong>NIA - ACIMO Intelligent Management Solution (AIMS)</strong>
    
    <div class="float-right d-none d-sm-inline-block">
      <b>MDO</b>(2024)
    </div>
</footer>

<!-- Chat Widget -->
<div class="chat-widget">
    <button class="chat-icon" id="chatToggle">
        <i class="fas fa-comments"></i>
        <span class="badge badge-danger" id="chatNotificationBadge" style="display: none; position: absolute; top: -5px; right: -5px;"></span>
    </button>
    
    <div class="chat-modal" id="chatModal">
        <div class="chat-header">
            <h5 id="chatHeaderTitle">Chat</h5>
            <button class="close-chat">&times;</button>
        </div>
        
        <div class="chat-body">
            <!-- Users Panel -->
            <div class="chat-users-panel" id="usersPanel">
                <div class="users-header">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="user-search" id="userSearch" placeholder="Search users...">
                    </div>
                </div>
                <div class="users-list-container">
                    <ul class="user-list" id="userList">
                        <!-- Users will be loaded here -->
                    </ul>
                </div>
            </div>
            
            <!-- Chat Panel -->
            <div class="chat-panel" id="chatPanel" style="display: none;">
                <div class="active-chat-header">
                    <button class="back-to-users" id="backToUsers">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="active-user-info">
                        <div class="active-user-avatar">
                            <img src="" id="activeUserAvatar" alt="" onerror="this.src='../dist/img/nialogo.png'">
                            <span class="active-userChat-status" id="activeUserStatus"></span>
                        </div>
                        <div class="active-user-details">
                            <div class="active-user-name" id="activeUserName"></div>
                            <div class="active-user-status-text" id="activeUserStatusText"></div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages-container">
                    <div class="chat-messages" id="chatMessages">
                        <!-- Messages will be loaded here -->
                    </div>
                </div>
                
                <div class="chat-input-container">
                    <form class="message-form" id="messageForm">
                        <input type="hidden" id="currentRoomId">
                        <div class="input-group">
                            <textarea class="message-input" id="messageInput" placeholder="Type a message..." rows="1"></textarea>
                            <button type="submit" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- No Chat Selected -->
            <div class="no-chat-selected" id="noChatSelected">
                <div>
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <h5>Welcome to Chat</h5>
                    <p>Select a user to start chatting</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class ChatSystem {
    // Update online status when user becomes active - more sensitive
startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
        $.post('../includes/chat_ajax.php', { action: 'update_online_status' });
    }, 60000); // Update every minute
}

stopHeartbeat() {
    if (this.heartbeatInterval) {
        clearInterval(this.heartbeatInterval);
        this.heartbeatInterval = null;
    }
}
    constructor() {
        this.currentRoomId = null;
        this.currentRecipient = null;
        this.lastMessageId = 0;
        this.pollInterval = null;
        this.isOpen = false;
        this.currentUserId = <?php echo $_SESSION['emp_id'] ?? 0; ?>;
        this.activeChats = new Map();
        this.allUsers = [];
        this.userUnreadCounts = new Map();
        
        this.heartbeatInterval = null;
        this.initializeEventListeners();
        this.startOnlineStatusUpdates();
        this.startHeartbeat();
    }

    initializeEventListeners() {
        $('#chatToggle').on('click', () => this.toggleChat());
        $('.close-chat').on('click', () => this.closeChat());
        $('#messageForm').on('submit', (e) => this.sendMessage(e));
        $('#messageInput').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        $('#userSearch').on('input', (e) => this.filterUsers(e.target.value));
        $('#backToUsers').on('click', () => this.showUsersPanel());
        
        $(document).on('click', (e) => {
            if (!$(e.target).closest('.chat-widget').length && this.isOpen) {
                this.closeChat();
            }
        });
    }

    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }

    openChat() {
        $('#chatModal').show();
        this.isOpen = true;
        this.loadOnlineUsers();
        this.updateUnreadCount(); // Make sure this is called
        this.showUsersPanel();
        
        // Also update unread counts periodically when chat is open
        this.unreadUpdateInterval = setInterval(() => {
            this.updateUnreadCount();
        }, 5000); // Update every 5 seconds when chat is open
    }

    closeChat() {
        $('#chatModal').hide();
        this.isOpen = false;
        this.stopPolling();
        this.stopHeartbeat(); // Stop heartbeat
        this.currentRoomId = null;
        this.currentRecipient = null;
        this.showUsersPanel();
        
        // Clear the unread update interval
        if (this.unreadUpdateInterval) {
            clearInterval(this.unreadUpdateInterval);
            this.unreadUpdateInterval = null;
        }
    }

    showUsersPanel() {
        $('#usersPanel').show();
        $('#chatPanel').hide();
        $('#noChatSelected').hide();
        this.stopPolling();
        this.currentRoomId = null;
        this.currentRecipient = null;
    }

    showChatPanel() {
        $('#usersPanel').hide();
        $('#chatPanel').show();
        $('#noChatSelected').hide();
    }

    showNoChatSelected() {
        $('#usersPanel').hide();
        $('#chatPanel').hide();
        $('#noChatSelected').show();
    }

    loadOnlineUsers() {
        $.post('../includes/chat_ajax.php', { action: 'get_online_users' }, (response) => {
            if (response.success) {
                this.allUsers = response.users;
                this.renderUserList(this.allUsers);
                this.updateUnreadCount();
            } else {
                console.error('Failed to load users:', response.error);
                $('#userList').html('<li class="user-item"><div class="user-info"><div class="user-name">Error loading users</div></div></li>');
            }
        }, 'json').fail((xhr, status, error) => {
            console.error('AJAX error loading users:', error);
            $('#userList').html('<li class="user-item"><div class="user-info"><div class="user-name">Network error</div></div></li>');
        });
    }

    filterUsers(searchTerm) {
        const filteredUsers = this.allUsers.filter(user => {
            const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
            return fullName.includes(searchTerm.toLowerCase());
        });
        this.renderUserList(filteredUsers);
    }

    renderUserList(users) {
        const userList = $('#userList');
        userList.empty();

        if (users.length === 0) {
            userList.append('<li class="user-item no-users"><div class="user-info"><div class="user-name">No users found</div></div></li>');
            return;
        }

        users.forEach(user => {
            const isOnline = user.is_online == 1;
            const statusClass = isOnline ? 'status-online' : 'status-offline';
            const statusText = isOnline ? 'Online' : 'Offline';
            const unreadCount = this.userUnreadCounts.get(user.emp_id) || 0;
            
            const avatarHtml = user.picture ? 
                `<img src="../dist/img/employees/${user.picture}" class="user-avatar" alt="${user.first_name}" onerror="this.onerror=null; this.src='../dist/img/nialogo.png'">` :
                `<img class="user-avatar default-avatar" src="../dist/img/nialogo.png">`;

            const userHtml = `
                <li class="user-item" data-user-id="${user.emp_id}" data-user-name="${user.first_name} ${user.last_name}" data-user-online="${isOnline}" data-user-picture="${user.picture || ''}">
                    <div class="user-avatar-container">
                        ${avatarHtml}
                        <span class="online-indicator ${isOnline ? 'online' : 'offline'}"></span>
                    </div>
                    <div class="user-info">
                        <div class="user-name">${user.first_name} ${user.last_name}</div>
                        <div class="user-status ${statusClass}">
                            ${statusText}
                        </div>
                    </div>
                    ${unreadCount > 0 ? `<span class="user-unread-badge">${unreadCount}</span>` : ''}
                </li>
            `;
            userList.append(userHtml);
        });

        $('.user-item').on('click', (e) => {
            const userId = $(e.currentTarget).data('user-id');
            const userName = $(e.currentTarget).data('user-name');
            const userOnline = $(e.currentTarget).data('user-online');
            const userPicture = $(e.currentTarget).data('user-picture');
            this.selectUser(userId, userName, userOnline, userPicture);
        });
    }

    async selectUser(userId, userName, userOnline, userPicture) {
        this.currentRecipient = { 
            id: userId, 
            name: userName,
            online: userOnline,
            picture: userPicture
        };
        
        try {
            const roomId = await this.getPrivateRoom(userId);
            if (roomId) {
                this.currentRoomId = roomId;
                this.showChatInterface();
                this.loadMessages();
                this.startPolling();
                this.markMessagesAsRead();
                // Clear unread count for this user
                this.userUnreadCounts.set(userId, 0);
                this.renderUserList(this.allUsers);
            } else {
                console.error('Failed to get chat room');
                alert('Failed to start chat. Please try again.');
            }
        } catch (error) {
            console.error('Error selecting user:', error);
            alert('Error starting chat: ' + error);
        }
    }

    getPrivateRoom(recipientId) {
        return new Promise((resolve, reject) => {
            $.post('../includes/chat_ajax.php', {
                action: 'get_private_room',
                recipient_id: recipientId
            }, (response) => {
                if (response.success) {
                    resolve(response.room_id);
                } else {
                    console.error('Failed to create room:', response.error);
                    reject(response.error);
                }
            }, 'json').fail((xhr, status, error) => {
                console.error('AJAX error creating room:', error);
                reject(error);
            });
        });
    }

    showChatInterface() {
        this.showChatPanel();
        
        $('#activeUserName').text(this.currentRecipient.name);
        $('#activeUserStatusText').text(this.currentRecipient.online ? 'Online' : 'Offline');
        $('#activeUserStatus').attr('class', `active-user-status ${this.currentRecipient.online ? 'online' : 'offline'}`);
        
        const avatarSrc = this.currentRecipient.picture ? 
            `../dist/img/employees/${this.currentRecipient.picture}` : 
            '../dist/img/nialogo.png';
        $('#activeUserAvatar').attr('src', avatarSrc);
        
        $('#messageInput').focus();
    }

    loadMessages() {
        if (!this.currentRoomId) return;

        this.lastMessageId = 0;

        $.post('../includes/chat_ajax.php', {
            action: 'get_messages',
            room_id: this.currentRoomId,
            last_message_id: this.lastMessageId
        }, (response) => {
            if (response.success) {
                this.renderMessages(response.messages);
                if (response.messages.length > 0) {
                    this.lastMessageId = response.messages[response.messages.length - 1].message_id;
                }
                // Use setTimeout to ensure DOM is updated before scrolling
                setTimeout(() => this.scrollToBottom(), 100);
            } else {
                console.error('Failed to load messages:', response.error);
            }
        }, 'json').fail((xhr, status, error) => {
            console.error('AJAX error loading messages:', error);
        });
    }

    renderMessages(messages) {
        const messagesContainer = $('#chatMessages');
        messagesContainer.empty();

        if (messages.length === 0) {
            messagesContainer.html(`
                <div class="no-messages">
                    <i class="fas fa-comments fa-2x"></i>
                    <p>No messages yet</p>
                    <small>Start the conversation by sending a message!</small>
                </div>
            `);
            return;
        }

        messages.forEach(message => {
            const isSent = message.sender_id == this.currentUserId;
            const messageClass = isSent ? 'sent' : 'received';
            const time = new Date(message.created_at).toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageHtml = `
                <div class="message ${messageClass}">
                    <div class="message-bubble">
                        ${!isSent ? `
                            <div class="message-sender">
                                ${message.first_name} ${message.last_name}
                            </div>
                        ` : ''}
                        <div class="message-text">${this.escapeHtml(message.message)}</div>
                        <div class="message-time">${time}</div>
                    </div>
                </div>
            `;
            messagesContainer.append(messageHtml);
        });
    }

    sendMessage(e) {
        e.preventDefault();
        
        const messageInput = $('#messageInput');
        const message = messageInput.val().trim();
        
        if (!message || !this.currentRoomId) return;

        $.post('../includes/chat_ajax.php', {
            action: 'send_message',
            room_id: this.currentRoomId,
            message: message
        }, (response) => {
            if (response.success) {
                messageInput.val('');
                messageInput.height('auto');
                this.loadMessages();
            } else {
                console.error('Failed to send message:', response.error);
                alert('Failed to send message: ' + response.error);
            }
        }, 'json').fail((xhr, status, error) => {
            console.error('AJAX error sending message:', error);
            alert('Network error: ' + error);
        });
    }

    startPolling() {
        this.stopPolling();
        this.pollInterval = setInterval(() => {
            if (this.currentRoomId) {
                this.loadNewMessages();
            }
            this.updateUnreadCount();
        }, 3000);
    }

    // New method to only load new messages during polling
    loadNewMessages() {
        if (!this.currentRoomId) return;

        $.post('../includes/chat_ajax.php', {
            action: 'get_messages',
            room_id: this.currentRoomId,
            last_message_id: this.lastMessageId
        }, (response) => {
            if (response.success && response.messages.length > 0) {
                this.appendNewMessages(response.messages);
                this.lastMessageId = response.messages[response.messages.length - 1].message_id;
                this.scrollToBottom();
            }
        }, 'json');
    }

    // Append only new messages instead of re-rendering all
    appendNewMessages(messages) {
        const messagesContainer = $('#chatMessages');
        
        messages.forEach(message => {
            const isSent = message.sender_id == this.currentUserId;
            const messageClass = isSent ? 'sent' : 'received';
            const time = new Date(message.created_at).toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const messageHtml = `
                <div class="message ${messageClass}">
                    <div class="message-bubble">
                        ${!isSent ? `
                            <div class="message-sender">
                                ${message.first_name} ${message.last_name}
                            </div>
                        ` : ''}
                        <div class="message-text">${this.escapeHtml(message.message)}</div>
                        <div class="message-time">${time}</div>
                    </div>
                </div>
            `;
            messagesContainer.append(messageHtml);
        });
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    markMessagesAsRead() {
        if (this.currentRoomId) {
            $.post('../includes/chat_ajax.php', {
                action: 'mark_read',
                room_id: this.currentRoomId
            });
        }
    }

    updateUnreadCount() {
        // Use the new endpoint that returns per-user unread counts
        $.post('../includes/chat_ajax.php', { action: 'get_unread_counts' }, (response) => {
            console.log('Unread counts response:', response); // Debug log
            if (response.success) {
                // Update total badge on chat icon
                const totalUnread = response.total_unread || 0;
                const badge = $('#chatNotificationBadge');
                console.log('Total unread:', totalUnread); // Debug log
                
                if (totalUnread > 0) {
                    badge.text(totalUnread > 99 ? '99+' : totalUnread).show();
                } else {
                    badge.hide();
                }
                
                // Update user-specific unread counts for badges next to user names
                if (response.user_unread_counts) {
                    console.log('User unread counts:', response.user_unread_counts); // Debug log
                    this.userUnreadCounts.clear();
                    Object.entries(response.user_unread_counts).forEach(([userId, count]) => {
                        this.userUnreadCounts.set(parseInt(userId), count);
                    });
                    // Always update the user list when we have new unread counts
                    if (this.isOpen) {
                        this.renderUserList(this.allUsers);
                    }
                }
            } else {
                console.error('Failed to get unread counts:', response.error);
            }
        }, 'json').fail((xhr, status, error) => {
            console.error('Error fetching unread counts:', error);
        });
    }

    startOnlineStatusUpdates() {
        // Update more frequently - every 30 seconds
        setInterval(() => {
            if (this.isOpen) {
                $.post('../includes/chat_ajax.php', { action: 'update_online_status' });
            }
        }, 30000); // 30 seconds
        
        // Initial update
        $.post('../includes/chat_ajax.php', { action: 'update_online_status' });
    }

    scrollToBottom() {
        const messagesContainer = $('.chat-messages-container');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize chat system when document is ready
$(document).ready(() => {
    <?php if (isset($_SESSION['emp_id'])): ?>
    window.chatSystem = new ChatSystem();
    
    $.post('../includes/chat_ajax.php', { action: 'update_online_status' });
    <?php endif; ?>
});



$(window).on('beforeunload', () => {
    if (window.chatSystem && window.chatSystem.currentUserId) {
        // Use synchronous AJAX to ensure the request completes
        $.ajax({
            url: '../includes/chat_ajax.php',
            type: 'POST',
            async: false, // Make it synchronous
            data: {
                action: 'set_offline'
            },
            success: function(response) {
                console.log('User set offline');
            }
        });
    }
});

</script>

<style>
/* Footer theming */
.main-footer {
    background: linear-gradient(135deg, #4361ee, #3f37c9) !important;
    color: white;
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.main-footer.theme-admin {
    background: linear-gradient(135deg, #4361ee, #3f37c9) !important;
}

.main-footer.theme-service {
    background: linear-gradient(135deg, #ffc107, #fd7e14) !important;
    color: #212529 !important;
}

.main-footer.theme-inventory {
    background: linear-gradient(135deg, #28a745, #20c997) !important;
}

.main-footer.theme-file {
    background: linear-gradient(135deg, #800020, #5a0a1d) !important;
}

.main-footer.theme-ict {
    background: linear-gradient(135deg, #17a2b8, #138496) !important;
}
</style>
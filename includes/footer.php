<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE - REMOVE DUPLICATE -->
<script src="../dist/js/adminlte.min.js"></script>
<!-- OPTIONAL SCRIPTS -->
<script src="../plugins/chart.js/Chart.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../dist/js/demo.js"></script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="../dist/js/pages/dashboard3.js"></script>
<!-- DataTables -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../plugins/jszip/jszip.min.js"></script>
<script src="../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- Toastr -->
<script src="../plugins/toastr/toastr.min.js"></script>
<script src="../plugins/select2/js/select2.full.min.js"></script>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<!-- jQuery UI - COMMENT OUT IF CAUSING ISSUES -->
<!-- <script src="../plugins/jquery-ui/jquery-ui.min.js"></script> -->

<script>
// Footer scripts — sidebar toggle is handled exclusively in mainheader.php
$(document).ready(function() {
    const ALLOWED_EMOJIS = ['👍','❤️','😂','😮','😢','👏'];
        let lastReactionSnapshot = {}; // track previous state for pulse detection

        // Inject picker HTML into every message bubble (call after rendering messages)
        function attachReactionPickers() {
            $('.message-bubble').each(function() {
                if ($(this).find('.reaction-picker').length) return;
                const pickerHtml = `<div class="reaction-picker">
                    ${ALLOWED_EMOJIS.map(e => `<span data-emoji="${e}">${e}</span>`).join('')}
                </div>`;
                $(this).prepend(pickerHtml);
            });
        }

        // Handle emoji click in picker
        $(document).on('click', '.reaction-picker span', function(e) {
            e.stopPropagation();
            const emoji     = $(this).data('emoji');
            const messageEl = $(this).closest('.message');
            const messageId = messageEl.data('message-id');

            $.post('chat_ajax.php', {
                action:     'toggle_reaction',
                message_id: messageId,
                emoji:      emoji
            }, function(res) {
                if (res.success) fetchAndRenderReactions(currentRoomId);
            }, 'json');
        });

        // Fetch reactions and render chips
        function fetchAndRenderReactions(room_id) {
            $.post('chat_ajax.php', {
                action:  'get_reactions',
                room_id: room_id
            }, function(res) {
                if (!res.success) return;

                const reactions    = res.reactions;
                const currentEmpId = parseInt($('body').data('emp-id')); // set this on <body>

                // Detect new reactions on MY sent messages → trigger pulse
                $('.message.sent').each(function() {
                    const mid = $(this).data('message-id');
                    const prevCount = lastReactionSnapshot[mid] || 0;
                    const newCount  = reactions[mid]
                        ? Object.values(reactions[mid]).reduce((s, r) => s + r.count, 0)
                        : 0;
                    if (newCount > prevCount) {
                        $(this).find('.message-bubble').addClass('pulse');
                        setTimeout(() => $(this).find('.message-bubble').removeClass('pulse'), 700);
                    }
                    lastReactionSnapshot[mid] = newCount;
                });

                // Render chips per message
                $('.message').each(function() {
                    const mid = $(this).data('message-id');
                    $(this).find('.reactions-bar').remove();

                    if (!reactions[mid]) return;

                    let html = '<div class="reactions-bar">';
                    $.each(reactions[mid], function(emoji, data) {
                        const iMine   = data.users_ids && data.users_ids.includes(currentEmpId);
                        const tooltip = data.users.join(', ');
                        html += `<span class="reaction-chip ${iMine ? 'reacted' : ''}" 
                                    data-message-id="${mid}" 
                                    data-emoji="${emoji}"
                                    title="${tooltip}">
                                    ${emoji} ${data.count}
                                </span>`;
                    });
                    html += '</div>';
                    $(this).find('.message-bubble').append(html);
                });

            }, 'json');
        }

        // Clicking a chip also toggles (same as picker)
        $(document).on('click', '.reaction-chip', function(e) {
            e.stopPropagation();
            const messageId = $(this).data('message-id');
            const emoji     = $(this).data('emoji');
            $.post('chat_ajax.php', {
                action:     'toggle_reaction',
                message_id: messageId,
                emoji:      emoji
            }, function(res) {
                if (res.success) fetchAndRenderReactions(currentRoomId);
            }, 'json');
        });

        // Hook into your existing polling loop — add this alongside getMessages polling:
        // setInterval(() => fetchAndRenderReactions(currentRoomId), 3000);
        // And call attachReactionPickers() after every renderMessages() call.

    // Initialize all dropdowns properly
    $('.dropdown-toggle').dropdown();


    // Handle notification clicks
    $(document).on('click', '#notificationList .dropdown-item', function(e) {
        e.preventDefault();
        const notificationId = $(this).data('notification-id');
        
        // Mark as read
        $.post('mark_notification_read.php', {id: notificationId}, function(response) {
            if (response.success) {
                // Update UI
                $(this).removeClass('font-weight-bold');
                updateNotificationCount();
            }
        }.bind(this));
    });

    // Update notification count
    function updateNotificationCount() {
        $.get('get_notification_count.php', function(data) {
            const count = parseInt(data);
            if (count > 0) {
                $('#notificationCount').text(count);
                $('#notificationHeader').text(count + ' Notifications');
            } else {
                $('#notificationCount').remove();
                $('#notificationHeader').text('No Notifications');
            }
        });
    }

    // Periodically update notification count (every 30 seconds)
    setInterval(updateNotificationCount, 30000);
});
</script>
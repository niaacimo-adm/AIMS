<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="../dist/js/adminlte.js"></script>
<!-- OPTIONAL SCRIPTS -->
<script src="../plugins/chart.js/Chart.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../dist/js/demo.js"></script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="../dist/js/pages/dashboard3.js"></script>
<!-- jQuery -->
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
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../dist/js/demo.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- Toastr -->
<script src="../plugins/toastr/toastr.min.js"></script>
<script src="../plugins/select2/js/select2.full.min.js"></script>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
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
</script>

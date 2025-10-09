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
// Fix dropdowns and sidebar functionality
$(document).ready(function() {
    // Fix sidebar toggle
    $('[data-widget="pushmenu"]').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Use AdminLTE sidebar toggle
        if (typeof $ !== 'undefined' && $.fn.pushMenu) {
            $('body').pushMenu('toggle');
        } else {
            // Fallback manual toggle
            $('body').toggleClass('sidebar-collapse');
            $('body').toggleClass('sidebar-open');
        }
        
        // Update localStorage
        const isCollapsed = $('body').hasClass('sidebar-collapse');
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    });

    // Load saved sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        $('body').addClass('sidebar-collapse');
    }

    // Fix dropdown clicks - prevent closing when clicking inside
    $('.dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });

    // Initialize all dropdowns properly
    $('.dropdown-toggle').dropdown();

    // Rest of your existing notification code...
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
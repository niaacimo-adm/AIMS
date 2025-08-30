<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

$database = new Database();
$db = $database->getConnection();
checkPermission('view_calendar');

// Fetch service requests for the calendar
$query = "SELECT sr.*, 
          CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
          v.model as vehicle_model,
          v.plate_no,
          CONCAT(drv.first_name, ' ', drv.last_name) AS driver_name
          FROM service_requests sr
          JOIN employee req ON sr.requesting_emp_id = req.emp_id
          LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
          LEFT JOIN employee drv ON sr.driver_emp_id = drv.emp_id
          WHERE sr.status = 'approved'";
$service_requests = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Fetch approved passengers for each request
foreach ($service_requests as &$request) {
    $passenger_query = "SELECT CONCAT(e.first_name, ' ', e.last_name) AS passenger_name 
                       FROM service_request_passengers p
                       JOIN employee e ON p.emp_id = e.emp_id
                       WHERE p.request_id = ? AND p.approved = 1";
    $stmt = $db->prepare($passenger_query);
    $stmt->bind_param("i", $request['request_id']);
    $stmt->execute();
    $passengers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $request['passengers'] = array_column($passengers, 'passenger_name');
}
unset($request); // Break the reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transport Service Calendar</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <style>
    .calendar-container {
      display: flex;
      gap: 20px;
    }
    .sidebar-section {
      margin-bottom: 20px;
    }
    .request-card {
      border-left: 4px solid #007bff;
      margin-bottom: 10px;
      cursor: pointer;
    }
    .request-card:hover {
      background-color: #f8f9fa;
    }
    .calendar-stats {
      background-color: #e9ecef;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 15px;
    }
    .fc-event {
      cursor: pointer;
    }
    .passenger-list {
      max-height: 150px;
      overflow-y: auto;
    }
    .dataTables_wrapper {
      padding: 0 !important;
    }
    .dataTables_filter {
      padding: 10px;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar_service.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Transport Service Calendar</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Calendar</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-4">
            <div class="sticky-top" style="z-index: 1000; top: 20px;">
              
              <!-- Calendar Stats -->
              <div class="calendar-stats">
                <h5><i class="fas fa-chart-bar"></i> Statistics</h5>
                <div class="d-flex justify-content-between">
                  <span>This Month:</span>
                  <strong id="month-stats">0 trips</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>This Week:</span>
                  <strong id="week-stats">0 trips</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>Today:</span>
                  <strong id="today-stats">0 trips</strong>
                </div>
              </div>

              <!-- Upcoming Service Requests -->
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-list"></i> Upcoming Service Requests</h3>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm" id="upcoming-requests-table">
                      <thead>
                        <tr>
                          <th>Destination</th>
                          <th>Date & Time</th>
                          <th>Requester</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- Will be populated by JavaScript -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Recent Activity -->
              <div class="card mt-3">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm" id="recent-activity-table">
                    <thead>
                      <tr>
                        <th>Activity</th>
                        <th>Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Will be populated by JavaScript -->
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>

          <div class="col-md-8">
            <div class="card card-primary">
              <div class="card-body p-0">
                <div id="calendar" style="min-height: 600px;"></div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
  // Global calendar variable
  let calendar;
  let upcomingRequestsTable;
  let recentActivityTable;

  $(function () {
      // Initialize DataTables
      upcomingRequestsTable = $('#upcoming-requests-table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "order": [[1, 'asc']],
        "columns": [
          { "data": "destination" },
          { "data": "datetime" },
          { "data": "requester" }
        ]
      });
      
      recentActivityTable = $('#recent-activity-table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": false,
        "info": false,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "columns": [
          { "data": "activity" },
          { "data": "time" }
        ]
      });

      // Prepare service request data for calendar
      const serviceRequests = <?php echo json_encode($service_requests); ?>;
      const serviceRequestEvents = serviceRequests.map(request => {
        return {
          id: 'service_' + request.request_id,
          title: '🚗 ' + request.destination,
          start: request.date_of_travel + 'T' + request.time_departure,
          end: request.date_of_travel + 'T' + request.time_return,
          extendedProps: {
            type: 'service_request',
            request_id: request.request_id,
            requester: request.requester_name,
            vehicle: request.vehicle_model || 'N/A',
            plate_no: request.plate_no || 'N/A',
            driver: request.driver_name || 'N/A',
            destination: request.destination,
            purpose: request.purpose,
            passengers: request.passengers || []
          },
          backgroundColor: '#007bff',
          borderColor: '#0056b3',
          textColor: '#fff'
        };
      });
      
      // Initialize calendar
      const calendarEl = document.getElementById('calendar');
      calendar = new FullCalendar.Calendar(calendarEl, {
          headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
          },
          themeSystem: 'bootstrap',
          initialView: 'dayGridMonth',
          navLinks: true,
          editable: false,
          selectable: true,
          dayMaxEvents: 3,
          eventDisplay: 'block',
          eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
          },
          
          events: function(fetchInfo, successCallback, failureCallback) {
              $.when(
                  $.ajax({
                      url: 'get_events.php',
                      type: 'GET',
                      dataType: 'json'
                  }),
                  $.ajax({
                      url: 'get_birthdays.php',
                      type: 'GET',
                      dataType: 'json'
                  })
              ).then(function(eventsResponse, birthdaysResponse) {
                  let events = eventsResponse[0].data || [];
                  let birthdays = birthdaysResponse[0].data || [];
                  
                  const allEvents = events.concat(birthdays, serviceRequestEvents);
                  successCallback(allEvents);
                  
                  // Update statistics
                  updateStatistics(allEvents);
                  
              }).catch(function(error) {
                  console.error('Error loading events:', error);
                  failureCallback('Failed to load events');
              });
          },
          
          eventClick: function(info) {
              if (info.event.extendedProps.type === 'service_request') {
                  showServiceRequestDetails(info.event);
                  return;
              }
              
              if (info.event.extendedProps.type === 'birthday') {
                  return;
              }
              
              // Handle other events
              $('#event-id').val(info.event.id);
              $('#modal-title-input').val(info.event.title);
              $('#modal-type').val(info.event.extendedProps.type || 'event');
              $('#modal-start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
              $('#modal-end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
              $('#modal-description').val(info.event.extendedProps.description || '');
              $('#event-modal').modal('show');
          },
          
          dateClick: function(info) {
              const dateStr = moment(info.date).format('MMMM D, YYYY');
              $('#modal-date-title').text(dateStr);
              $('#quick-event-start-time').val('09:00');
              $('#quick-event-end-time').val('17:00');
              $('#quick-event-title').val('');
              $('#quick-event-type').val('event');
              $('#quick-event-description').val('');
              $('#day-click-modal').data('date', info.date);
              $('#day-click-modal').modal('show');
          },
          
          eventDidMount: function(info) {
              // Add tooltips to events
              if (info.event.extendedProps.type === 'service_request') {
                  $(info.el).attr('title', 
                    `Transport to ${info.event.extendedProps.destination}\n` +
                    `Driver: ${info.event.extendedProps.driver}\n` +
                    `Vehicle: ${info.event.extendedProps.vehicle}`
                  );
              }
          }
      });
      
      calendar.render();
      
      // Load initial data
      loadServiceRequestsTable();
      loadRecentActivity();
  });

  // Function to show service request details with passengers
  function showServiceRequestDetails(event) {
      const props = event.extendedProps;
      const passengersHtml = props.passengers.length > 0 
          ? props.passengers.map(p => `<li>${p}</li>`).join('')
          : '<li class="text-muted">No passengers</li>';
      
      const html = `
        <div class="service-request-details">
          <h4>Transport Request Details</h4>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Requester:</strong> ${props.requester}</p>
              <p><strong>Destination:</strong> ${props.destination}</p>
              <p><strong>Vehicle:</strong> ${props.vehicle}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Plate Number:</strong> ${props.plate_no}</p>
              <p><strong>Driver:</strong> ${props.driver}</p>
              <p><strong>Time:</strong> ${moment(event.start).format('h:mm A')} - ${moment(event.end).format('h:mm A')}</p>
            </div>
          </div>
          <p><strong>Purpose:</strong> ${props.purpose}</p>
          <p><strong>Approved Passengers:</strong></p>
          <ul class="passenger-list">${passengersHtml}</ul>
        </div>
      `;
      
      Swal.fire({
        title: 'Transport Request',
        html: html,
        icon: 'info',
        width: '700px',
        confirmButtonText: 'Close',
        showCloseButton: true
      });
  }

  // Function to update calendar statistics
  function updateStatistics(events) {
      const today = moment().startOf('day');
      const weekStart = moment().startOf('week');
      const weekEnd = moment().endOf('week');
      const monthStart = moment().startOf('month');
      const monthEnd = moment().endOf('month');
      
      const todayTrips = events.filter(event => 
          event.extendedProps?.type === 'service_request' &&
          moment(event.start).isSame(today, 'day')
      ).length;
      
      const weekTrips = events.filter(event => 
          event.extendedProps?.type === 'service_request' &&
          moment(event.start).isBetween(weekStart, weekEnd, null, '[]')
      ).length;
      
      const monthTrips = events.filter(event => 
          event.extendedProps?.type === 'service_request' &&
          moment(event.start).isBetween(monthStart, monthEnd, null, '[]')
      ).length;
      
      $('#today-stats').text(`${todayTrips} trip${todayTrips !== 1 ? 's' : ''}`);
      $('#week-stats').text(`${weekTrips} trip${weekTrips !== 1 ? 's' : ''}`);
      $('#month-stats').text(`${monthTrips} trip${monthTrips !== 1 ? 's' : ''}`);
  }

  // Function to load service requests into the DataTable
  function loadServiceRequestsTable() {
      const serviceRequests = <?php echo json_encode($service_requests); ?>;
      const today = new Date();
      const upcomingRequests = serviceRequests
          .filter(request => new Date(request.date_of_travel) >= today)
          .sort((a, b) => new Date(a.date_of_travel) - new Date(b.date_of_travel));
      
      upcomingRequestsTable.clear();
      
      if (upcomingRequests.length > 0) {
          upcomingRequests.forEach(request => {
              const formattedDate = moment(request.date_of_travel).format('MMM D');
              const timeRange = moment(request.time_departure, 'HH:mm:ss').format('h:mm A') + 
                               ' - ' + 
                               moment(request.time_return, 'HH:mm:ss').format('h:mm A');
              
              upcomingRequestsTable.row.add({
                "destination": `<strong>${request.destination}</strong>`,
                "datetime": `${formattedDate}<br><small class="text-muted">${timeRange}</small>`,
                "requester": `${request.requester_name}<br><small class="text-success">${request.vehicle_model || 'No vehicle'}</small>`
              }).draw();
          });
      }
  }

  // Function to focus on a specific request in the calendar
  function focusOnRequest(requestId) {
      const event = calendar.getEventById('service_' + requestId);
      if (event) {
          calendar.changeView('timeGridDay');
          calendar.gotoDate(event.start);
          event.setProp('backgroundColor', '#dc3545');
          setTimeout(() => event.setProp('backgroundColor', '#007bff'), 1000);
      }
  }

  // Function to load recent activity into DataTable
    function loadRecentActivity() {
        $.ajax({
            url: 'get_recent_activity.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                recentActivityTable.clear();
                
                if (response.data && response.data.length > 0) {
                    response.data.forEach(activity => {
                        let icon = 'fa-info-circle';
                        let color = 'text-info';
                        
                        if (activity.type === 'approval') {
                            icon = 'fa-check-circle';
                            color = 'text-success';
                        } else if (activity.type === 'completion') {
                            icon = 'fa-flag-checkered';
                            color = 'text-primary';
                        } else if (activity.type === 'rejection') {
                            icon = 'fa-times-circle';
                            color = 'text-danger';
                        } else if (activity.type === 'creation') {
                            icon = 'fa-plus-circle';
                            color = 'text-warning';
                        }
                        
                        recentActivityTable.row.add({
                            "activity": `<i class="fas ${icon} ${color} mr-2"></i> ${activity.message}`,
                            "time": activity.time
                        }).draw();
                    });
                } else {
                    recentActivityTable.row.add({
                        "activity": `<i class="fas fa-info-circle text-info mr-2"></i> No recent activity`,
                        "time": ""
                    }).draw();
                }
            },
            error: function() {
                console.error('Failed to load recent activity');
                recentActivityTable.row.add({
                    "activity": `<i class="fas fa-exclamation-triangle text-danger mr-2"></i> Error loading activity`,
                    "time": ""
                }).draw();
            }
        });
    }

  // Export calendar function
  function exportCalendar() {
      Swal.fire({
          title: 'Export Calendar',
          html: `
              <div class="form-group">
                  <label>Select Format:</label>
                  <select class="form-control" id="export-format">
                      <option value="pdf">PDF</option>
                      <option value="excel">Excel</option>
                      <option value="csv">CSV</option>
                  </select>
              </div>
              <div class="form-group">
                  <label>Date Range:</label>
                  <select class="form-control" id="export-range">
                      <option value="month">Current Month</option>
                      <option value="week">Current Week</option>
                      <option value="custom">Custom Range</option>
                  </select>
              </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Export',
          preConfirm: () => {
              const format = $('#export-format').val();
              const range = $('#export-range').val();
              // Here you would implement the actual export functionality
              Swal.fire('Exported!', `Calendar exported as ${format.toUpperCase()}`, 'success');
          }
      });
  }
</script>
</body>
</html>
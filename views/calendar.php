<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
// session_start();

$database = new Database();
$db = $database->getConnection();
checkPermission('view_calendar');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Calendar</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ── Design Tokens ─────────────────────────────────────────── */
    :root {
        --primary:        #2563eb;
        --primary-dark:   #1d4ed8;
        --primary-light:  #eff6ff;
        --accent:         #06b6d4;
        --success:        #10b981;
        --warning:        #f59e0b;
        --danger:         #ef4444;
        --birthday:       #8b5cf6;
        --meeting:        #1d4ed8;
        --holiday:        #f59e0b;
        --event:          #2563eb;

        --bg:             #f0f4f8;
        --surface:        #ffffff;
        --surface-2:      #f8fafc;
        --border:         #e2e8f0;
        --border-subtle:  #f1f5f9;
        --text-primary:   #0f172a;
        --text-secondary: #475569;
        --text-muted:     #94a3b8;

        --radius-sm:  6px;
        --radius:     12px;
        --radius-lg:  18px;
        --shadow-sm:  0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --shadow:     0 4px 16px rgba(0,0,0,.08);
        --shadow-lg:  0 12px 40px rgba(0,0,0,.12);

        --font-ui:    'DM Sans', sans-serif;
        --font-head:  'Syne', sans-serif;
    }

    /* ── Base overrides ─────────────────────────────────────────── */
    body, .content-wrapper { background: var(--bg) !important; font-family: var(--font-ui) !important; }

    /* ── Page header ────────────────────────────────────────────── */
    .content-header h1 {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-primary);
        letter-spacing: -0.03em;
    }

    /* ── Cards ──────────────────────────────────────────────────── */
    .card {
        border: 1px solid var(--border) !important;
        border-radius: var(--radius-lg) !important;
        box-shadow: var(--shadow-sm) !important;
        overflow: hidden;
        background: var(--surface) !important;
        transition: box-shadow .2s ease;
    }
    .card:hover { box-shadow: var(--shadow) !important; }

    .card-primary { border-color: var(--border) !important; }

    .card-header {
        background: var(--surface) !important;
        border-bottom: 1px solid var(--border-subtle) !important;
        padding: 1rem 1.25rem !important;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .card-header h3,
    .card-header h4,
    .card-header .card-title {
        font-family: var(--font-head);
        font-size: .95rem !important;
        font-weight: 700 !important;
        color: var(--text-primary) !important;
        letter-spacing: -.01em;
        margin: 0 !important;
    }
    /* Coloured left accent bar on card headers */
    .card-header::before {
        content: '';
        display: inline-block;
        width: 4px;
        height: 18px;
        border-radius: 4px;
        background: linear-gradient(160deg, var(--primary), var(--accent));
        flex-shrink: 0;
    }

    /* ── Sidebar form ───────────────────────────────────────────── */
    .form-group label {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: .3rem;
    }
    .form-control {
        border: 1.5px solid var(--border) !important;
        border-radius: var(--radius-sm) !important;
        font-family: var(--font-ui) !important;
        font-size: .875rem !important;
        color: var(--text-primary) !important;
        padding: .5rem .75rem !important;
        background: var(--surface-2) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(37,99,235,.12) !important;
        background: var(--surface) !important;
    }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* ── Buttons ────────────────────────────────────────────────── */
    .btn {
        font-family: var(--font-ui) !important;
        font-weight: 600 !important;
        font-size: .85rem !important;
        border-radius: var(--radius-sm) !important;
        transition: all .18s ease !important;
        letter-spacing: .01em;
    }
    .btn-primary, .btn-primary.btn-block {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        border: none !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(37,99,235,.3) !important;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-dark), #1e3a8a) !important;
        box-shadow: 0 4px 16px rgba(37,99,235,.4) !important;
        transform: translateY(-1px);
    }
    .btn-danger {
        background: linear-gradient(135deg, var(--danger), #dc2626) !important;
        border: none !important;
        box-shadow: 0 2px 8px rgba(239,68,68,.3) !important;
    }
    .btn-secondary {
        background: var(--surface-2) !important;
        border: 1.5px solid var(--border) !important;
        color: var(--text-secondary) !important;
    }
    .btn-secondary:hover {
        background: var(--border-subtle) !important;
        color: var(--text-primary) !important;
    }

    /* ── FullCalendar toolbar ───────────────────────────────────── */
    .fc .fc-toolbar { padding: 1rem 1.25rem .5rem; }
    .fc .fc-toolbar-title {
        font-family: var(--font-head) !important;
        font-size: 1.15rem !important;
        font-weight: 800 !important;
        color: var(--text-primary) !important;
        letter-spacing: -.02em;
    }
    .fc .fc-button {
        border-radius: var(--radius-sm) !important;
        font-family: var(--font-ui) !important;
        font-size: .8rem !important;
        font-weight: 600 !important;
        padding: .35rem .75rem !important;
        transition: all .15s !important;
    }
    .fc .fc-button-primary {
        background: var(--surface-2) !important;
        border: 1.5px solid var(--border) !important;
        color: var(--text-secondary) !important;
        box-shadow: none !important;
    }
    .fc .fc-button-primary:hover,
    .fc .fc-button-primary:not(:disabled):active,
    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(37,99,235,.3) !important;
    }
    .fc .fc-today-button {
        background: linear-gradient(135deg, var(--accent), #0891b2) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(6,182,212,.3) !important;
    }
    .fc .fc-today-button:hover {
        background: linear-gradient(135deg, #0891b2, #0e7490) !important;
    }

    /* ── Calendar grid ──────────────────────────────────────────── */
    .fc-day-today { background: var(--primary-light) !important; }
    .fc-day-today .fc-daygrid-day-number {
        background: var(--primary);
        color: #fff !important;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: .82rem;
    }
    .fc-col-header-cell {
        background: var(--surface-2) !important;
        font-family: var(--font-ui) !important;
        font-size: .78rem !important;
        font-weight: 700 !important;
        color: var(--text-muted) !important;
        text-transform: uppercase;
        letter-spacing: .07em;
        border-color: var(--border) !important;
    }
    .fc-daygrid-day-number {
        font-size: .82rem;
        font-weight: 500;
        color: var(--text-secondary) !important;
        padding: 6px 8px !important;
    }
    .fc td, .fc th { border-color: var(--border-subtle) !important; }

    /* ── Event pills ────────────────────────────────────────────── */
    .fc-event {
        border-radius: 5px !important;
        border: none !important;
        font-size: .76rem !important;
        font-weight: 600 !important;
        padding: 2px 6px !important;
    }
    .fc-event-title { white-space: normal !important; }
    .birthday-event  { background: var(--birthday)  !important; }
    .holiday-event   { background: var(--holiday)   !important; }
    .meeting-event   { background: var(--meeting)   !important; }
    .event-event     { background: var(--event)     !important; }

    /* ── Tables ─────────────────────────────────────────────────── */
    .table { font-size: .86rem; color: var(--text-primary); }
    .table thead th {
        background: var(--surface-2) !important;
        border-bottom: 2px solid var(--border) !important;
        font-weight: 700;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--text-muted) !important;
        padding: .75rem 1rem;
        white-space: nowrap;
    }
    .table tbody td { padding: .7rem 1rem; border-color: var(--border-subtle) !important; vertical-align: middle; }
    .table-hover tbody tr { transition: background .15s; }
    .table-hover tbody tr:hover td { background: var(--primary-light) !important; }
    #events-table tr.table-info td { background: rgba(37,99,235,.06) !important; }
    #birthdays-table tr:hover td   { background: var(--primary-light) !important; }

    /* ── Badges ─────────────────────────────────────────────────── */
    .badge {
        border-radius: 20px !important;
        font-size: .72rem !important;
        font-weight: 700 !important;
        padding: .28em .7em !important;
        letter-spacing: .03em;
    }
    .badge-primary { background: var(--primary) !important; color: #fff; }
    span.badge[style*="background-color:#4361ee"] { background: var(--event)   !important; }
    span.badge[style*="background-color:#3f37c9"] { background: var(--meeting) !important; }
    span.badge[style*="background-color:#ffa500"] { background: var(--holiday) !important; }
    span.badge[style*="background-color:#4895ef"] { background: var(--birthday)!important; }

    /* ── Loading spinner ────────────────────────────────────────── */
    .calendar-loading { position: relative; min-height: 300px; }
    .calendar-loading::after {
        content: "";
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        width: 36px; height: 36px;
        border: 3px solid var(--border);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        z-index: 1000;
    }
    @keyframes spin {
        to { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* ── Error box ──────────────────────────────────────────────── */
    #calendar-error {
        margin: 1rem;
        padding: 1rem 1.25rem;
        border-left: 4px solid var(--danger);
        background: #fef2f2;
        color: #7f1d1d;
        border-radius: var(--radius-sm);
        font-size: .875rem;
    }
    #calendar-error .close { color: #7f1d1d; opacity: .7; }
    #calendar-error .btn   { margin-left: .5rem; }

    /* ── Modals ─────────────────────────────────────────────────── */
    .modal-content {
        border: none !important;
        border-radius: var(--radius-lg) !important;
        box-shadow: var(--shadow-lg) !important;
        overflow: hidden;
    }
    .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        border: none !important;
        padding: 1.1rem 1.5rem !important;
    }
    .modal-title {
        font-family: var(--font-head) !important;
        font-weight: 700 !important;
        font-size: 1rem !important;
        color: #fff !important;
        letter-spacing: -.01em;
    }
    .modal-header .close { color: rgba(255,255,255,.8) !important; text-shadow: none !important; font-size: 1.4rem; }
    .modal-header .close:hover { color: #fff !important; }
    .modal-body  { padding: 1.5rem !important; background: var(--surface); }
    .modal-footer {
        padding: 1rem 1.5rem !important;
        border-top: 1px solid var(--border-subtle) !important;
        background: var(--surface-2);
        gap: .5rem;
        display: flex;
    }

    /* ── DataTables tweaks ──────────────────────────────────────── */
    div.dataTables_wrapper div.dataTables_length select,
    div.dataTables_wrapper div.dataTables_filter input {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .3rem .6rem;
        font-size: .82rem;
        font-family: var(--font-ui);
        color: var(--text-primary);
        background: var(--surface-2);
    }
    div.dataTables_wrapper div.dataTables_info,
    div.dataTables_wrapper div.dataTables_length label,
    div.dataTables_wrapper div.dataTables_filter label {
        font-size: .8rem;
        color: var(--text-muted);
        font-family: var(--font-ui);
    }
    .paginate_button { border-radius: var(--radius-sm) !important; font-size: .8rem !important; }
    .paginate_button.current {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
    }

    /* ── Misc ───────────────────────────────────────────────────── */
    .sticky-top { top: 1rem; }
    .toast { font-size: .875rem; padding: 1rem; }
    #events-table { width: 100% !important; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Calendar</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">
            <div class="sticky-top mb-3">
              <div class="card card-primary">
                <div class="card-header">
                  <h3 class="card-title">Employee Birthdays</h3>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table id="birthdays-table" class="table table-hover table-striped" style="width:100%">
                      <thead>
                        <tr>
                          <th>Employee</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- AJAX DATA -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
                              
              <div class="card card-primary">
                <div class="card-header">
                  <h3 class="card-title">Create Event</h3>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <label>Event Title</label>
                    <input id="event-title" type="text" class="form-control" placeholder="Event Title">
                  </div>
                  <div class="form-group">
                    <label>Event Type</label>
                    <select id="event-type" class="form-control">
                      <option value="event">General Event</option>
                      <option value="meeting">Meeting</option>
                      <option value="holiday">Holiday</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Start Date/Time</label>
                    <input id="event-start" type="datetime-local" class="form-control">
                  </div>
                  <div class="form-group">
                    <label>End Date/Time</label>
                    <input id="event-end" type="datetime-local" class="form-control">
                  </div>
                  <div class="form-group">
                    <label>Description</label>
                    <textarea id="event-description" class="form-control" rows="3" placeholder="Enter description"></textarea>
                  </div>
                  <button id="add-event" class="btn btn-primary btn-block">Add Event</button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-body p-0">
                <div id="calendar"></div>
              </div>
            </div>
            
            <div class="card card-primary mt-3">
                <div class="card-header">
                    <h4 class="card-title">Upcoming Events</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="events-table" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody id="events-table-body">
                                <!-- AJAX DATA -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Event Modal -->
  <div class="modal fade" id="event-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Event Details</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="event-id">
          <div class="form-group">
            <label>Title</label>
            <input id="modal-title-input" class="form-control">
          </div>
          <div class="form-group">
            <label>Type</label>
            <select id="modal-type" class="form-control">
              <option value="event">General Event</option>
              <option value="meeting">Meeting</option>
              <option value="holiday">Holiday</option>
            </select>
          </div>
          <div class="form-group">
            <label>Start Date/ Time</label>
            <input id="modal-start" class="form-control" type="datetime-local">
          </div>
          <div class="form-group">
            <label>End Date/ Time</label>
            <input id="modal-end" class="form-control" type="datetime-local">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea id="modal-description" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" id="delete-event">Delete</button>
          <button type="button" class="btn btn-primary" id="save-event">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Day Click Modal -->
  <div class="modal fade" id="day-click-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Event for <span id="modal-date-title"></span></h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Event Title</label>
            <input id="quick-event-title" type="text" class="form-control" placeholder="Event Title">
          </div>
          <div class="form-group">
            <label>Event Type</label>
            <select id="quick-event-type" class="form-control">
              <option value="event">General Event</option>
              <option value="meeting">Meeting</option>
              <option value="holiday">Holiday</option>
            </select>
          </div>
          <div class="form-group">
            <label>Start Time</label>
            <input id="quick-event-start-time" type="time" class="form-control">
          </div>
          <div class="form-group">
            <label>End Time</label>
            <input id="quick-event-end-time" type="time" class="form-control">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea id="quick-event-description" class="form-control" rows="3" placeholder="Enter description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="save-quick-event">Save Event</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>

<script>
  $(function () {
      // Set admin theme
      setAdminTheme();
      
      // Initialize calendar with loading state
      $('#calendar').addClass('calendar-loading');
      
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
          headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
          },
          themeSystem: 'bootstrap',
          initialView: 'dayGridMonth',
          navLinks: true,
          editable: true,
          selectable: true,
          eventLimit: true,
          
          // Event styling based on type
          eventDidMount: function(info) {
              // Add custom classes based on event type
              if (info.event.extendedProps.type === 'birthday') {
                  info.el.classList.add('birthday-event');
              } else if (info.event.extendedProps.type === 'holiday') {
                  info.el.classList.add('holiday-event');
              } else if (info.event.extendedProps.type === 'meeting') {
                  info.el.classList.add('meeting-event');
              } else {
                  info.el.classList.add('event-event');
              }
          },
          
          // Updated events loading with proper error handling
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
                  $('#calendar').removeClass('calendar-loading');
                  
                  // Check if both requests succeeded
                  if (!eventsResponse[0].success || !birthdaysResponse[0].success) {
                      showCalendarError('Failed to load some events. Please try again.');
                      failureCallback('Failed to load events');
                      return;
                  }
                  
                  var events = eventsResponse[0].data || [];
                  var birthdays = birthdaysResponse[0].data || [];
                  successCallback(events.concat(birthdays));
                  
              }, function(jqXHR, textStatus, errorThrown) {
                  $('#calendar').removeClass('calendar-loading');
                  showCalendarError('Failed to load calendar events: ' + textStatus);
                  failureCallback('Failed to load events');
              });
          },
          
          eventClick: function(info) {
              // Skip modal for birthday events
              if (info.event.extendedProps.type === 'birthday') {
                  return;
              }
              
              $('#event-id').val(info.event.id);
              $('#modal-title-input').val(info.event.title);
              $('#modal-type').val(info.event.extendedProps.type || 'event');
              $('#modal-start').val(moment(info.event.start).format('YYYY-MM-DDTHH:mm'));
              $('#modal-end').val(info.event.end ? moment(info.event.end).format('YYYY-MM-DDTHH:mm') : '');
              $('#modal-description').val(info.event.extendedProps.description || '');
              $('#event-modal').modal('show');
          },
          
          dateClick: function(info) {
              // Format the date for display
              var dateStr = moment(info.date).format('MMMM D, YYYY');
              $('#modal-date-title').text(dateStr);
              
              // Set default times (9am-5pm)
              $('#quick-event-start-time').val('09:00');
              $('#quick-event-end-time').val('17:00');
              
              // Clear other fields
              $('#quick-event-title').val('');
              $('#quick-event-type').val('event');
              $('#quick-event-description').val('');
              
              // Store the clicked date in the modal for later use
              $('#day-click-modal').data('date', info.date);
              
              // Show the modal
              $('#day-click-modal').modal('show');
          },
      });
      
      calendar.render();
      
      // Function to set admin theme
      function setAdminTheme() {
          localStorage.setItem('currentTheme', 'admin');
          $('body').addClass('theme-admin');
      }
      
      // Quick event save handler
      $('#save-quick-event').click(function() {
          var title = $('#quick-event-title').val().trim();
          var type = $('#quick-event-type').val();
          var startTime = $('#quick-event-start-time').val();
          var endTime = $('#quick-event-end-time').val();
          var description = $('#quick-event-description').val().trim();
          var date = $('#day-click-modal').data('date');
          
          if (!title) {
              alert('Title is required');
              return;
          }
          
          // Combine date with time
          var startDateTime = moment(date).format('YYYY-MM-DD') + 'T' + startTime;
          var endDateTime = moment(date).format('YYYY-MM-DD') + 'T' + endTime;
          
          $.ajax({
              url: 'add_event.php',
              type: 'POST',
              data: {
                  title: title,
                  type: type,
                  start: startDateTime,
                  end: endDateTime,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();
                      $('#day-click-modal').modal('hide');
                      
                      Swal.fire(
                          'Success!',
                          'Event added successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to add event';
                  Swal.fire(
                      'Error!',
                      errorMsg,
                      'error'
                  );
              }
          });
      });
      
      // Function to show error messages
      function showCalendarError(message) {
          var errorHtml = `
              <div id="calendar-error" class="alert alert-danger alert-dismissible">
                  <button type="button" class="close" data-dismiss="alert">&times;</button>
                  <strong>Error!</strong> ${message}
                  <button class="btn btn-sm btn-default" onclick="location.reload()">Reload</button>
              </div>
          `;
          $('#calendar').before(errorHtml);
      }

      // Add event handler
      $('#add-event').click(function() {
          var title = $('#event-title').val().trim();
          var type = $('#event-type').val();
          var start = $('#event-start').val();
          var end = $('#event-end').val();
          var description = $('#event-description').val().trim();
          
          if (!title) {
              alert('Title is required');
              return;
          }
          
          if (!start) {
              alert('Start date is required');
              return;
          }
          
          if (end && new Date(end) < new Date(start)) {
              alert('End date must be after start date');
              return;
          }
          
          $.ajax({
              url: 'add_event.php',
              type: 'POST',
              data: {
                  title: title,
                  type: type,
                  start: start,
                  end: end,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();
                      $('#event-title, #event-description').val('');
                      $('#event-type').val('event');
                      
                      Swal.fire(
                          'Success!',
                          'Event added successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to add event';
                  alert('Error: ' + errorMsg);
              }
          });
      });
      
      // Save event handler
      $('#save-event').click(function() {
          var eventId = $('#event-id').val();
          var title = $('#modal-title-input').val().trim();
          var type = $('#modal-type').val();
          var start = $('#modal-start').val();
          var end = $('#modal-end').val();
          var description = $('#modal-description').val().trim();
          
          if (!title) {
              alert('Title is required');
              return;
          }
          
          if (!start) {
              alert('Start date is required');
              return;
          }
          
          if (end && new Date(end) < new Date(start)) {
              alert('End date must be after start date');
              return;
          }
          
          $.ajax({
              url: 'update_event.php',
              type: 'POST',
              data: {
                  id: eventId,
                  title: title,
                  type: type,
                  start: start,
                  end: end,
                  description: description
              },
              success: function(response) {
                  if (response.status === 'success') {
                      calendar.refetchEvents();
                      loadEventsTable();
                      $('#event-modal').modal('hide');
                      
                      Swal.fire(
                          'Success!',
                          'Event updated successfully',
                          'success'
                      );
                  } else {
                      Swal.fire(
                          'Error!',
                          response.message,
                          'error'
                      );
                  }
              },
              error: function(xhr) {
                  var errorMsg = xhr.responseJSON?.message || 'Failed to update event';
                  alert('Error: ' + errorMsg);
              }
          });
      });
      
      // Delete event handler
      $('#delete-event').click(function() {
          Swal.fire({
              title: 'Are you sure?',
              text: "You won't be able to revert this!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
          }).then((result) => {
              if (result.isConfirmed) {
                  var eventId = $('#event-id').val();
                  
                  $.ajax({
                      url: 'delete_events.php',
                      type: 'POST',
                      data: { id: eventId },
                      dataType: 'json',
                      success: function(response) {
                          if (response.status === 'success') {
                              calendar.refetchEvents();
                              $('#event-modal').modal('hide');
                              loadEventsTable();
                              
                              Swal.fire(
                                  'Deleted!',
                                  'Your event has been deleted.',
                                  'success'
                              );
                          } else {
                              Swal.fire(
                                  'Error!',
                                  response.message,
                                  'error'
                              );
                          }
                      },
                      error: function(xhr) {
                          var errorMsg = xhr.responseJSON?.message || 'Failed to delete event';
                          Swal.fire(
                              'Error!',
                              errorMsg,
                              'error'
                          );
                      }
                  });
              }
          });
      });

      // Initialize DataTable for events
      var eventsTable = $('#events-table').DataTable({
          responsive: true,
          order: [[3, 'asc']],
          columns: [
              { data: 'title' },
              { 
                  data: 'type',
                  render: function(data, type, row) {
                      var badgeColor = '#4361ee'; // default admin blue
                      if (data === 'meeting') badgeColor = '#3f37c9';
                      if (data === 'holiday') badgeColor = '#ffa500';
                      if (data === 'birthday') badgeColor = '#4895ef';
                      
                      return '<span class="badge" style="background-color:' + badgeColor + '; color: white;">' + data + '</span>';
                  }
              },
              { data: 'description' },
              { 
                  data: 'start',
                  render: function(data) {
                      return data ? moment(data).format('MMM D, YYYY h:mm A') : '';
                  }
              },
              { 
                  data: 'end',
                  render: function(data) {
                      return data ? moment(data).format('MMM D, YYYY h:mm A') : '';
                  }
              }
          ],
          language: {
              emptyTable: "No upcoming events found",
              zeroRecords: "No matching events found"
          }
      });

      // Function to load events into the DataTable
      function loadEventsTable() {
          $.ajax({
              url: 'get_events.php?t=' + new Date().getTime(),
              type: 'GET',
              dataType: 'json',
              success: function(response) {
                  if (response.success) {
                      eventsTable.clear();
                      
                      if (response.data && response.data.length > 0) {
                          eventsTable.rows.add(response.data).draw();
                          
                          // Highlight today's events
                          var today = moment().startOf('day');
                          eventsTable.rows().every(function() {
                              var rowData = this.data();
                              var eventDate = moment(rowData.start).startOf('day');
                              if (eventDate.isSame(today)) {
                                  $(this.node()).addClass('table-info');
                              }
                          });
                      }
                  } else {
                      console.error('Error loading events:', response.message);
                  }
              },
              error: function(xhr, status, error) {
                  console.error('AJAX error loading events:', error);
                  eventsTable.clear().draw();
              }
          });
      }

      // Initialize Birthdays DataTable
      var birthdaysTable = $('#birthdays-table').DataTable({
          responsive: true,
          lengthChange: true,
          autoWidth: false,
          pageLength: 5,
          lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
          columns: [
              { 
                  data: 'name',
                  render: function(data, type, row) {
                      return '<strong>' + data + '</strong>';
                  }
              },
              { 
                  data: 'date',
                  render: function(data) {
                      return '<span class="badge badge-primary">' + moment(data).format('MMMM D') + '</span>';
                  }
              }
          ],
          dom: '<"top"lf>rt<"bottom"ip>',
          language: {
              lengthMenu: "Show _MENU_ entries per page",
              paginate: {
                  previous: "&laquo;",
                  next: "&raquo;"
              }
          }
      });

// Function to load birthdays into the DataTable (current month only)
function loadBirthdaysTable() {
    $.ajax({
        url: 'get_birthdays.php?t=' + new Date().getTime(),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                birthdaysTable.clear();
                
                if (response.data && response.data.length > 0) {
                    var currentMonth = new Date().getMonth(); // Get current month (0-11)
                    var currentYear = new Date().getFullYear();
                    
                    var processedData = response.data.map(function(birthday) {
                        var date = new Date(birthday.start);
                        var today = new Date();
                        var nextBirthday = new Date(today.getFullYear(), date.getMonth(), date.getDate());
                        
                        if (nextBirthday < today) {
                            nextBirthday.setFullYear(nextBirthday.getFullYear() + 1);
                        }
                        
                        var daysUntil = Math.ceil((nextBirthday - today) / (1000 * 60 * 60 * 24));
                        var age = (today.getFullYear() - date.getFullYear());
                        
                        if (today < new Date(today.getFullYear(), date.getMonth(), date.getDate())) {
                            age--;
                        }
                        
                        return {
                            name: birthday.title.replace("'s Birthday", ""),
                            date: birthday.start,
                            age: age,
                            days_until: daysUntil,
                            birthMonth: date.getMonth() // Store the birth month for filtering
                        };
                    });
                    
                    // Filter birthdays to only show current month
                    var currentMonthBirthdays = processedData.filter(function(birthday) {
                        return birthday.birthMonth === currentMonth;
                    });
                    
                    // Sort by day of month
                    currentMonthBirthdays.sort(function(a, b) {
                        var dateA = new Date(a.date);
                        var dateB = new Date(b.date);
                        return dateA.getDate() - dateB.getDate();
                    });
                    
                    birthdaysTable.rows.add(currentMonthBirthdays).draw();
                }
            } else {
                console.error('Error loading birthdays:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error loading birthdays:', error);
            birthdaysTable.clear().draw();
        }
    });
}

      // Load initial data
      loadEventsTable();
      loadBirthdaysTable();
  });
</script>
</body>
</html>
<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

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
  <style>
    #birthday-list .list-group-item {
        border-left: 3px solid #ff69b4;
        margin-bottom: 5px;
    }

    .badge.bg-pink {
        background-color: #ff69b4;
        color: white;
    }

    #birthday-list small.text-muted {
        font-size: 0.8em;
    }
        .birthday-event {
          background-color: #ff69b4 !important;
          border-color: #ff69b4 !important;
        }
        .holiday-event {
          background-color: #ffa500 !important;
          border-color: #ffa500 !important;
        }
        .meeting-event {
          background-color: #4682b4 !important;
          border-color: #4682b4 !important;
        }
        .fc-event-title {
          white-space: normal !important;
        }
        
    .calendar-loading {
      position: relative;
      min-height: 300px;
    }

    .calendar-loading:after {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 40px;
      height: 40px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #3498db;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      z-index: 1000;
    }

    @keyframes spin {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* Error Message Styles */
    #calendar-error {
      margin: 15px;
      padding: 15px;
      border-left: 4px solid #dc3545;
      background-color: #f8d7da;
      color: #721c24;
      border-radius: 4px;
    }

    #calendar-error .close {
      color: #721c24;
      opacity: 0.8;
    }

    #calendar-error .btn {
      margin-left: 10px;
    }
    /* Toastr notification styling */
    .toast {
        font-size: 14px;
        padding: 15px;
    }
    /* DataTables styling */
    #events-table {
        width: 100% !important;
    }

    #events-table th {
        white-space: nowrap;
    }

    #events-table .badge {
        font-size: 0.85em;
        padding: 0.35em 0.65em;
    }

    #events-table tr.table-info td {
        background-color: rgba(23, 162, 184, 0.1) !important;
    }

    #birthdays-table .badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
    }

    #birthdays-table tr td {
        vertical-align: middle;
    }

    #birthdays-table tr:hover td {
        background-color: rgba(255, 105, 180, 0.1) !important;
    }
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
              <div class="card">
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
                          <!-- <th>Age</th>
                          <th>Days Until</th> -->
                        </tr>
                      </thead>
                      <tbody>
                        <!-- AJAX DATA -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
                              
              <div class="card">
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
              <div class="card-body p-02">
                <div id="calendar"></div>
                <div class="card">
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

        </div>
      </div>
    </section>
  </div>

  <!-- Event Modal -->
  <div class="modal fade" id="event-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-title">Event Details</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Event for <span id="modal-date-title"></span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
          
          // Rest of your calendar configuration...
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
          // Other existing callbacks...
      });
      
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
    calendar.render();
    
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

    // Updated add event function
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
                    loadEventsTable(); // Refresh the events table
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
    
    // Updated save event function
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
                    loadEventsTable(); // Refresh the events table
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
                            loadEventsTable(); // Refresh the events table
                            
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

    // Helper function to update event
    function updateEvent(event) {
      $.ajax({
        url: 'update_event.php',
        type: 'POST',
        data: {
          id: event.id,
          title: event.title,
          type: event.extendedProps?.type || 'event',
          start: event.start ? moment(event.start).format('YYYY-MM-DDTHH:mm:ss') : null,
          end: event.end ? moment(event.end).format('YYYY-MM-DDTHH:mm:ss') : null,
          description: event.extendedProps?.description || ''
        },
        success: function() {
          calendar.refetchEvents();
        }
      });
    }

    // Helper function to get color based on event type
    function getEventColor(type) {
      switch(type) {
        case 'birthday': return '#ff69b4';
        case 'holiday': return '#ffa500';
        case 'meeting': return '#4682b4';
        default: return '#3c8dbc';
      }
    }

    // Initialize draggable events
    function ini_events(ele) {
      ele.each(function() {
        var eventObject = {
          title: $.trim($(this).text())
        };
        $(this).data('eventObject', eventObject);
        $(this).draggable({
          zIndex: 1070,
          revert: true,
          revertDuration: 0
        });
      });
    }
    
    ini_events($('#external-events div.external-event'));

    // Function to load and display birthdays in the sidebar
    function loadBirthdayList() {
        $.ajax({
            url: 'get_birthdays.php?t=' + new Date().getTime(),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                var $birthdayList = $('#birthday-list');
                $birthdayList.empty();
                
                if (response.success && response.data && response.data.length > 0) {
                    // Sort birthdays by day of month
                    response.data.sort(function(a, b) {
                        return new Date(a.start).getDate() - new Date(b.start).getDate();
                    });
                    
                    // Get current month name for header
                    var monthName = new Date().toLocaleString('default', { month: 'long' });
                    $('#empbday').text(monthName + ' Birthdays');
                    
                    // Display birthdays
                    response.data.forEach(function(birthday) {
                        var date = moment(birthday.start).format('MMMM D');
                        var name = birthday.title.replace("'s Birthday", "");
                        
                        $birthdayList.append(
                            '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                            '<div>' +
                            '<strong>' + name + '</strong><br>' +
                            '<span class="badge bg-red">' + date + '</span>' +
                            '</div>' +
                            '<span class="badge bg-pink"><i class="fas fa-birthday-cake"></i></span>' +
                            '</li>'
                        );
                    });
                } else {
                    $birthdayList.html(
                        '<li class="list-group-item text-muted">No birthdays this month</li>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading birthdays:', error);
                $('#birthday-list').html(
                    '<li class="list-group-item text-danger">Error loading birthdays</li>'
                );
            }
        });
    }
    // Call the function when the page loads
    loadBirthdayList();
    });

    // Initialize DataTable
    var eventsTable = $('#events-table').DataTable({
        responsive: true,
        order: [[3, 'asc']], // Sort by start date ascending by default
        columns: [
            { data: 'title' },
            { 
                data: 'type',
                render: function(data, type, row) {
                    return '<span class="badge" style="background-color:' + row.backgroundColor + '">' + data + '</span>';
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
                    // Clear and redraw the table with new data
                    eventsTable.clear();
                    
                    if (response.data && response.data.length > 0) {
                        // Process and add the events
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
    // Call this when the page loads
    loadEventsTable();
  
</script>
<script>
$(document).ready(function() {
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
                    return moment(data).format('MMMM D');
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

    // Function to load birthdays into the DataTable
    function loadBirthdaysTable() {
        $.ajax({
            url: 'get_birthdays.php?t=' + new Date().getTime(),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    birthdaysTable.clear();
                    
                    if (response.data && response.data.length > 0) {
                        // Process birthday data for the table
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
                                days_until: daysUntil
                            };
                        });
                        
                        birthdaysTable.rows.add(processedData).draw();
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

    // Call this when the page loads
    loadBirthdaysTable();
});
</script>
</body>
</html>
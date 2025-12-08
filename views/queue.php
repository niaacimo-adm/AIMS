<?php
// queue.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set theme for this module
$_SESSION['current_theme'] = 'queue';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get all employees for "Person to Visit" dropdown
$employees = [];
$employee_query = "SELECT emp_id, first_name, last_name, position_id, section_id 
                   FROM employee 
                   WHERE employment_status_id = 1 
                   ORDER BY last_name, first_name";
$employee_result = $db->query($employee_query);
if ($employee_result) {
    $employees = $employee_result->fetch_all(MYSQLI_ASSOC);
}

// Get all sections for "Department" dropdown
$sections = [];
$section_query = "SELECT section_id, section_name, section_code 
                  FROM section 
                  WHERE office_id = 1 
                  ORDER BY section_name";
$section_result = $db->query($section_query);
if ($section_result) {
    $sections = $section_result->fetch_all(MYSQLI_ASSOC);
}

// Get all units
$units = [];
$unit_query = "SELECT unit_id, unit_name, unit_code, section_id 
               FROM unit_section 
               ORDER BY unit_name";
$unit_result = $db->query($unit_query);
if ($unit_result) {
    $units = $unit_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Queue Management System | NIA-ACIMO AIMS</title>

    <?php include '../includes/header.php'; ?>

    <style>
        .queue-theme {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
        }

        .queue-badge {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
        }

        .qr-code-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .queue-display {
            font-size: 72px;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin: 20px 0;
        }

        .section-counter {
            background: #34495e;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .counter-number {
            font-size: 36px;
            font-weight: bold;
            text-align: center;
        }

        .visitor-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin: 10px auto;
            display: block;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_queue.php'; ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Queue Management System</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active">Queue Management</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Visitor Registration</h3>
                                </div>
                                <div class="card-body">
                                    <form id="visitorForm">
                                        <div class="form-group">
                                            <label for="visitorName">Full Name *</label>
                                            <input type="text" class="form-control" id="visitorName" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="visitorCompany">Company/Organization</label>
                                            <input type="text" class="form-control" id="visitorCompany">
                                        </div>
                                        <div class="form-group">
                                            <label for="purpose">Purpose of Visit *</label>
                                            <select class="form-control" id="purpose" required>
                                                <option value="">Select Purpose</option>
                                                <option value="Meeting">Meeting</option>
                                                <option value="Document Submission">Document Submission</option>
                                                <option value="Inquiry">Inquiry</option>
                                                <option value="Payment">Payment</option>
                                                <option value="Follow-up">Follow-up</option>
                                                <option value="Interview">Interview</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="personToVisit">Person to Visit *</label>
                                            <select class="form-control select2" id="personToVisit" required style="width: 100%;">
                                                <option value="">Select Employee</option>
                                                <?php foreach ($employees as $emp): ?>
                                                    <option value="<?= $emp['emp_id'] ?>">
                                                        <?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="section">Department/Section *</label>
                                            <select class="form-control select2" id="section" required style="width: 100%;">
                                                <option value="">Select Section/Unit</option>
                                                <optgroup label="Manager's Office">
                                                    <option value="manager_office">IMO Office</option>
                                                </optgroup>
                                                <optgroup label="Sections">
                                                    <?php foreach ($sections as $section): ?>
                                                        <option value="section_<?= $section['section_id'] ?>">
                                                            <?= htmlspecialchars($section['section_name']) ?> (<?= $section['section_code'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <optgroup label="Units">
                                                    <?php foreach ($units as $unit):
                                                        $section_name = '';
                                                        foreach ($sections as $sec) {
                                                            if ($sec['section_id'] == $unit['section_id']) {
                                                                $section_name = $sec['section_name'];
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                        <option value="unit_<?= $unit['unit_id'] ?>">
                                                            <?= htmlspecialchars($unit['unit_name']) ?> (<?= $unit['unit_code'] ?>) - <?= $section_name ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="contactNumber">Contact Number</label>
                                            <input type="text" class="form-control" id="contactNumber">
                                        </div>
                                        <div class="form-group">
                                            <label for="remarks">Remarks/Notes</label>
                                            <textarea class="form-control" id="remarks" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-ticket-alt"></i> Generate Queue Number
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">Current Queue Status</h3>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h2>Now Serving</h2>
                                        <div class="queue-display" id="nowServing">---</div>
                                        <div id="currentVisitorInfo" class="text-muted">No visitor being served</div>
                                    </div>
                                    <div class="text-center">
                                        <h4>Next in Line</h4>
                                        <div class="h3" id="nextInLine">---</div>
                                    </div>
                                    <div class="text-center mt-4">
                                        <h5>Estimated Wait Time</h5>
                                        <div class="h4" id="waitTime">Calculating...</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Active Queue List</h3>
                                    <div class="card-tools">
                                        <button class="btn btn-sm btn-success" id="callNextBtn">
                                            <i class="fas fa-bullhorn"></i> Call Next
                                        </button>
                                        <button class="btn btn-sm btn-info" id="refreshQueue">
                                            <i class="fas fa-sync"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover" id="queueTable">
                                            <thead>
                                                <tr>
                                                    <th>Queue No.</th>
                                                    <th>Visitor Name</th>
                                                    <th>Purpose</th>
                                                    <th>Section/Unit</th>
                                                    <th>Person to Visit</th>
                                                    <th>Time In</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Queue data will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">Visitor Pass with QR Code</h3>
                                        </div>
                                        <div class="card-body text-center">
                                            <div id="visitorPass" style="display: none;">
                                                <div class="qr-code-container mb-3">
                                                    <div id="qrcode"></div>
                                                </div>
                                                <div class="visitor-info">
                                                    <h4 id="passVisitorName"></h4>
                                                    <p><strong>Queue No:</strong> <span id="passQueueNumber" class="font-weight-bold"></span></p>
                                                    <p><strong>Section/Unit:</strong> <span id="passSection"></span></p>
                                                    <p><strong>Time In:</strong> <span id="passTimeIn"></span></p>
                                                    <button class="btn btn-primary" onclick="printVisitorPass()">
                                                        <i class="fas fa-print"></i> Print Pass
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="noPassMessage">
                                                <p class="text-muted">
                                                    <i class="fas fa-ticket-alt fa-2x mb-2"></i><br>
                                                    Generate a queue number to see the visitor pass
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">Section/Unit Counters</h3>
                                        </div>
                                        <div class="card-body">
                                            <div id="sectionCounters">
                                                <!-- Section counters will be loaded here -->
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

        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- QR Code Library -->
    <script src="../libs/phpqrcode/phpqrcode.php"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4'
            });

// Replace the DataTables initialization section with this:
var queueTable = $('#queueTable').DataTable({
    "processing": true,
    "serverSide": false,
    "ajax": {
        "url": "../includes/queue_ajax.php?action=get_queue",
        "type": "GET",
        "dataSrc": function(json) {
            console.log('AJAX Response:', json); // Debug logging
            
            // Check if we got a valid response
            if (json && json.success === true && json.data) {
                return json.data;
            } else {
                // Show user-friendly error
                if (json && json.message) {
                    console.error('Server error:', json.message);
                    Swal.fire({
                        title: 'Error',
                        text: json.message,
                        icon: 'error'
                    });
                } else {
                    console.error('Invalid response format:', json);
                    Swal.fire({
                        title: 'Error',
                        text: 'Invalid response from server',
                        icon: 'error'
                    });
                }
                return [];
            }
        },
        "error": function(xhr, error, thrown) {
            console.error('DataTables AJAX error:', error, thrown);
            console.error('Response:', xhr.responseText);
            
            // Parse error message from response if possible
            let errorMsg = 'Failed to load queue data. Please check your connection and try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMsg = response.message;
                }
            } catch (e) {
                // If not JSON, use default message
            }
            
            Swal.fire({
                title: 'Connection Error',
                text: errorMsg,
                icon: 'error'
            });
            
            // Show error in table
            $('#queueTable tbody').html(`
                <tr>
                    <td colspan="8" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        ${errorMsg}
                    </td>
                </tr>
            `);
        }
    },
    "columns": [{
            "data": "queue_number",
            "render": function(data) {
                return `<span class="badge badge-dark" style="font-size: 14px;">${data}</span>`;
            }
        },
        {
            "data": "visitor_name"
        },
        {
            "data": "purpose"
        },
        {
            "data": null,
            "render": function(data) {
                if (data.section_name && data.section_name !== '') {
                    return data.section_name;
                } else if (data.unit_name && data.unit_name !== '') {
                    return data.unit_name;
                }
                return 'N/A';
            }
        },
        {
            "data": null,
            "render": function(data) {
                if (data.employee_first_name && data.employee_last_name) {
                    return data.employee_last_name + ', ' + data.employee_first_name;
                }
                return data.employee_name || 'N/A';
            }
        },
        {
            "data": "time_in",
            "render": function(data) {
                if (!data) return '';
                const date = new Date(data);
                let hours = date.getHours();
                let minutes = date.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12;
                minutes = minutes < 10 ? '0' + minutes : minutes;

                return hours + ':' + minutes + ' ' + ampm;
            }
        },
        {
            "data": "status",
            "render": function(data) {
                let badgeClass = 'secondary';
                let statusText = data;

                switch (data) {
                    case 'waiting':
                        badgeClass = 'warning';
                        statusText = 'Waiting';
                        break;
                    case 'called':
                        badgeClass = 'info';
                        statusText = 'Called';
                        break;
                    case 'serving':
                        badgeClass = 'primary';
                        statusText = 'Serving';
                        break;
                    case 'completed':
                        badgeClass = 'success';
                        statusText = 'Completed';
                        break;
                    case 'cancelled':
                        badgeClass = 'danger';
                        statusText = 'Cancelled';
                        break;
                }

                return `<span class="badge badge-${badgeClass}">${statusText}</span>`;
            }
        },
        {
            "data": null,
            "render": function(data, type, row) {
                let actions = '';
                if (row.status === 'waiting') {
                    actions += `<button class="btn btn-sm btn-info call-btn" data-id="${row.id}">
                        <i class="fas fa-bullhorn"></i> Call
                        </button> `;
                    actions += `<button class="btn btn-sm btn-warning edit-btn" data-id="${row.id}">
                        <i class="fas fa-edit"></i>
                        </button> `;
                    actions += `<button class="btn btn-sm btn-danger cancel-btn" data-id="${row.id}">
                        <i class="fas fa-times"></i>
                        </button>`;
                } else if (row.status === 'called') {
                    actions += `<button class="btn btn-sm btn-primary serve-btn" data-id="${row.id}">
                        <i class="fas fa-user-check"></i> Serve
                        </button> `;
                } else if (row.status === 'serving') {
                    actions += `<button class="btn btn-sm btn-success complete-btn" data-id="${row.id}">
                        <i class="fas fa-check"></i> Complete
                        </button>`;
                }
                return actions;
            }
        }
    ],
    "language": {
        "emptyTable": "No visitors in queue",
        "loadingRecords": "Loading queue data...",
        "processing": "Processing..."
    },
    "pageLength": 10,
    "responsive": true,
    "searching": true,
    "ordering": true,
    "info": true,
    "autoWidth": false
});

            function formatTime12Hour(date) {
                let hours = date.getHours();
                let minutes = date.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'

                minutes = minutes < 10 ? '0' + minutes : minutes;

                return hours + ':' + minutes + ' ' + ampm;
            }
            // Generate Queue Number
            $('#visitorForm').on('submit', function(e) {
                e.preventDefault();

                const formData = {
                    visitor_name: $('#visitorName').val().trim(),
                    company: $('#visitorCompany').val().trim(),
                    purpose: $('#purpose').val(),
                    person_to_visit: $('#personToVisit').val(),
                    section: $('#section').val(),
                    contact_number: $('#contactNumber').val().trim(),
                    remarks: $('#remarks').val().trim()
                };

                // Validate form
                if (!formData.visitor_name) {
                    Swal.fire('Error', 'Please enter visitor name', 'error');
                    return;
                }
                if (!formData.purpose) {
                    Swal.fire('Error', 'Please select purpose of visit', 'error');
                    return;
                }
                if (!formData.person_to_visit) {
                    Swal.fire('Error', 'Please select person to visit', 'error');
                    return;
                }
                if (!formData.section) {
                    Swal.fire('Error', 'Please select department/section', 'error');
                    return;
                }

                $.ajax({
                    url: '../includes/queue_ajax.php?action=add_to_queue',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                html: `Queue Number: <h2 class="text-primary">${response.queue_number}</h2>`,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });

                            // Generate QR Code
                            generateQRCode(response.queue_id, response.queue_number);

                            // Show visitor pass
                            $('#passVisitorName').text(response.visitor_name);
                            $('#passQueueNumber').text(response.queue_number);
                            $('#passSection').text(response.section_name || response.unit_name);
                            $('#passTimeIn').text(formatTime12Hour(new Date(response.time_in)));
                            $('#visitorPass').show();
                            $('#noPassMessage').hide();

                            // Refresh queue table
                            $('#queueTable').DataTable().ajax.reload();

                            // Update section counters
                            loadSectionCounters();

                            // Clear form
                            $('#visitorForm')[0].reset();
                            $('.select2').val(null).trigger('change');
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    }
                });
            });

            $('#callNextBtn').click(function() {
                const section = prompt('Enter Section/Unit to call from (or leave empty for any):');

                $.ajax({
                    url: '../includes/queue_ajax.php?action=call_next',
                    type: 'POST',
                    data: {
                        section: section || ''
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Call next response:', response);

                        if (response.success) {
                            // Update display
                            $('#nowServing').text(response.queue_number);
                            $('#currentVisitorInfo').html(`
                    <strong>${response.visitor_name}</strong><br>
                    <small>${response.section_name || response.unit_name || 'N/A'}</small>
                `);

                            // Also update queue display
                            updateQueueStatus();

                            // Refresh queue table
                            $('#queueTable').DataTable().ajax.reload();

                            // Update section counters
                            loadSectionCounters();

                            // Play sound
                            playNotificationSound();

                            // Show notification
                            Swal.fire({
                                title: 'Visitor Called!',
                                html: `Queue Number: <h3>${response.queue_number}</h3>`,
                                icon: 'info',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Info',
                                text: response.message || 'No visitors in queue',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to call next visitor', 'error');
                    }
                });
            });
            // Action buttons
            $(document).on('click', '.call-btn', function() {
                const queueId = $(this).data('id');
                callVisitor(queueId);
            });

            $(document).on('click', '.serve-btn', function() {
                const queueId = $(this).data('id');
                serveVisitor(queueId);
            });

            $(document).on('click', '.complete-btn', function() {
                const queueId = $(this).data('id');
                completeVisitor(queueId);
            });

            $(document).on('click', '.edit-btn', function() {
                const queueId = $(this).data('id');
                editVisitor(queueId);
            });

            $(document).on('click', '.cancel-btn', function() {
                const queueId = $(this).data('id');
                cancelVisitor(queueId);
            });

            // Refresh queue
            $('#refreshQueue').click(function() {
                $('#queueTable').DataTable().ajax.reload();
                updateQueueStatus();
                loadSectionCounters();
            });

            // Auto-refresh every 30 seconds
            setInterval(function() {
                $('#queueTable').DataTable().ajax.reload(null, false);
                updateQueueStatus();
                loadSectionCounters();
            }, 30000);

            // Initial load
            updateQueueStatus();
            loadSectionCounters();
        });

        function generateQRCode(queueId, queueNumber) {
            $('#qrcode').empty();

            // Create a new image element
            const qrData = `NIA-QUEUE:${queueId}:${queueNumber}`;
            const qrUrl = `../includes/generate_qrcode.php?data=${encodeURIComponent(qrData)}`;

            // Create an image and set its source
            const img = document.createElement('img');
            img.src = qrUrl;
            img.alt = `QR Code for ${queueNumber}`;
            img.style.width = '180px';
            img.style.height = '180px';

            $('#qrcode').html(img);
        }

        // In queue.php, modify the callVisitor function:
        function callVisitor(queueId) {
            $.ajax({
                url: '../includes/queue_ajax.php?action=call_visitor',
                type: 'POST',
                data: {
                    queue_id: queueId
                },
                dataType: 'json', // Add this to expect JSON
                success: function(response) {
                    console.log('Call response:', response); // Add for debugging

                    if (response.success) {
                        $('#nowServing').text(response.queue_number);
                        $('#currentVisitorInfo').html(`
                    <strong>${response.visitor_name}</strong><br>
                    <small>${response.section_name || response.unit_name || 'N/A'}</small>
                `);

                        // Update the queue display as well
                        updateQueueStatus();

                        $('#queueTable').DataTable().ajax.reload(null, false);
                        loadSectionCounters();
                        playNotificationSound();

                        Swal.fire({
                            title: 'Visitor Called!',
                            html: `Queue Number: <h3>${response.queue_number}</h3>`,
                            icon: 'info',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to call visitor', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    Swal.fire('Error', 'Failed to call visitor. Please try again.', 'error');
                }
            });
        }

        function serveVisitor(queueId) {
            $.ajax({
                url: '../includes/queue_ajax.php?action=serve_visitor',
                type: 'POST',
                data: {
                    queue_id: queueId
                },
                dataType: 'json', // Add this line
                success: function(response) {
                    if (response.success) {
                        $('#queueTable').DataTable().ajax.reload();
                        loadSectionCounters();
                        updateQueueStatus();

                        // Show success message
                        Swal.fire({
                            title: 'Success!',
                            text: response.message || 'Visitor is now being served',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to serve visitor', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to serve visitor', 'error');
                }
            });
        }

        function completeVisitor(queueId) {
            $.ajax({
                url: '../includes/queue_ajax.php?action=complete_visitor',
                type: 'POST',
                data: {
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        $('#queueTable').DataTable().ajax.reload();
                        updateQueueStatus();
                        loadSectionCounters();

                        Swal.fire({
                            title: 'Completed!',
                            text: 'Visitor service completed',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                }
            });
        }

        function editVisitor(queueId) {
            // Load visitor data and show edit modal
            $.ajax({
                url: '../includes/queue_ajax.php?action=get_visitor_details',
                type: 'POST',
                data: {
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        // Show edit form in modal
                        Swal.fire({
                            title: 'Edit Visitor Details',
                            html: `
                        <div class="text-left">
                            <div class="form-group">
                                <label>Visitor Name</label>
                                <input type="text" class="form-control" id="editVisitorName" value="${response.visitor_name}">
                            </div>
                            <div class="form-group">
                                <label>Company</label>
                                <input type="text" class="form-control" id="editCompany" value="${response.company || ''}">
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" class="form-control" id="editContact" value="${response.contact_number || ''}">
                            </div>
                        </div>
                    `,
                            showCancelButton: true,
                            confirmButtonText: 'Save',
                            preConfirm: () => {
                                return {
                                    visitor_name: $('#editVisitorName').val(),
                                    company: $('#editCompany').val(),
                                    contact_number: $('#editContact').val()
                                }
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const data = result.value;
                                data.queue_id = queueId;

                                $.ajax({
                                    url: '../includes/queue_ajax.php?action=update_visitor',
                                    type: 'POST',
                                    data: data,
                                    success: function(response) {
                                        if (response.success) {
                                            $('#queueTable').DataTable().ajax.reload();
                                            Swal.fire('Success', 'Visitor details updated', 'success');
                                        }
                                    }
                                });
                            }
                        });
                    }
                }
            });
        }

        function cancelVisitor(queueId) {
            Swal.fire({
                title: 'Cancel Visitor?',
                text: "This will remove the visitor from the queue",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../includes/queue_ajax.php?action=cancel_visitor',
                        type: 'POST',
                        data: {
                            queue_id: queueId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#queueTable').DataTable().ajax.reload();
                                updateQueueStatus();
                                loadSectionCounters();

                                Swal.fire(
                                    'Cancelled!',
                                    'Visitor has been removed from queue',
                                    'success'
                                );
                            }
                        }
                    });
                }
            });
        }

        function updateQueueStatus() {
            $.ajax({
                url: '../includes/queue_ajax.php?action=get_queue_status',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        // Update current serving
                        if (response.now_serving) {
                            $('#nowServing').text(response.now_serving.queue_number);
                            $('#currentVisitorInfo').html(`
                        <strong>${response.now_serving.visitor_name}</strong><br>
                        <small>${response.now_serving.section_name || response.now_serving.unit_name}</small>
                    `);
                        } else {
                            $('#nowServing').text('---');
                            $('#currentVisitorInfo').html('No visitor being served');
                        }

                        // Update next in line
                        $('#nextInLine').text(response.next_in_line || '---');

                        // Calculate wait time
                        if (response.waiting_count > 0) {
                            const avgTime = response.average_wait_time || 5;
                            const waitMinutes = response.waiting_count * avgTime;
                            $('#waitTime').text(`${waitMinutes} minutes`);
                        } else {
                            $('#waitTime').text('No wait');
                        }
                    }
                }
            });
        }

        function loadSectionCounters() {
            $.ajax({
                url: '../includes/queue_ajax.php?action=get_section_counters',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.counters.forEach(function(counter) {
                            const waiting = counter.waiting_count || 0;
                            const serving = counter.serving_count || 0;
                            const total = waiting + serving;

                            html += `
                        <div class="section-counter">
                            <h5>${counter.name}</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <small>Now Serving</small>
                                        <div class="counter-number text-primary">${counter.current_serving || '---'}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <small>Waiting</small>
                                        <div class="counter-number text-warning">${waiting}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-center">
                                <small>Total Today: ${counter.total_today || 0}</small>
                            </div>
                        </div>
                    `;
                        });
                        $('#sectionCounters').html(html);
                    }
                }
            });
        }

        function playNotificationSound() {
            const audio = new Audio('../dist/sounds/bell.wav');
            audio.play().catch(e => console.log("Audio play failed:", e));
        }

        function printVisitorPass() {
            const printContent = document.getElementById('visitorPass').innerHTML;
            const originalContent = document.body.innerHTML;

            // Get the current time in 12-hour format
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;

            const currentTime = hours + ':' + minutes + ' ' + ampm;

            document.body.innerHTML = `
                <div style="padding: 20px; text-align: center; font-family: Arial, sans-serif;">
                    <div style="border: 2px solid #2c3e50; border-radius: 10px; padding: 20px; max-width: 400px; margin: 0 auto;">
                        <h2 style="color: #2c3e50; margin-bottom: 20px;">NIA-ACIMO VISITOR PASS</h2>
                        ${printContent}
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ccc;">
                            <p style="font-size: 12px; color: #666; margin: 5px 0;">
                                Please present this pass at the reception<br>
                                Valid for today only
                            </p>
                            <p style="font-size: 11px; color: #999; margin-top: 10px;">
                                Generated: ${currentTime}
                            </p>
                        </div>
                    </div>
                </div>
            `;

            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }

        // Handle section selection change to filter employees
        $('#section').on('change', function() {
            const sectionValue = $(this).val();
            if (sectionValue.startsWith('section_')) {
                const sectionId = sectionValue.replace('section_', '');
                // You can implement filtering of employees by section here
            }
        });
        // Auto-select section based on employee
        $('#personToVisit').on('change', function() {
            const empId = $(this).val();

            if (!empId) {
                return;
            }

            // Get employee details via AJAX
            $.ajax({
                url: '../includes/get_employee_section.php',
                type: 'POST',
                data: {
                    emp_id: empId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let sectionValue = '';

                        if (response.is_manager_office_staff) {
                            // Manager's office staff
                            sectionValue = 'manager_office';
                        } else if (response.section_id) {
                            // Regular section
                            sectionValue = 'section_' + response.section_id;
                        } else if (response.unit_section_id) {
                            // Unit section
                            sectionValue = 'unit_' + response.unit_section_id;
                        }

                        // Set the section dropdown
                        if (sectionValue) {
                            $('#section').val(sectionValue).trigger('change');
                        }
                    }
                },
                error: function() {
                    console.log('Failed to get employee details');
                }
            });
        });
    </script>

</body>

</html>
<?php
// section_queue.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get the current employee's section/unit
$current_emp_id = $_SESSION['emp_id'];
$current_section_id = null;
$current_unit_id = null;
$current_section_name = '';
$current_unit_name = '';
$is_manager_staff = false;

// Check if employee is in manager's office staff
$query = "SELECT * FROM managers_office_staff WHERE emp_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $current_emp_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $is_manager_staff = true;
}

// Get employee's section/unit if not manager staff
if (!$is_manager_staff) {
    $query = "SELECT e.section_id, e.unit_section_id, s.section_name, u.unit_name 
              FROM employee e 
              LEFT JOIN section s ON e.section_id = s.section_id 
              LEFT JOIN unit_section u ON e.unit_section_id = u.unit_id 
              WHERE e.emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $current_emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $current_section_id = $row['section_id'];
        $current_unit_id = $row['unit_section_id'];
        $current_section_name = $row['section_name'] ?? '';
        $current_unit_name = $row['unit_name'] ?? '';
    }
}

// Determine what queue to show
$queue_type = '';
$queue_name = '';

if ($is_manager_staff) {
    $queue_type = 'imo';
    $queue_name = 'IMO Office';
} elseif ($current_unit_id) {
    $queue_type = 'unit';
    $queue_name = $current_unit_name . ' Unit';
} elseif ($current_section_id) {
    $queue_type = 'section';
    $queue_name = $current_section_name . ' Section';
} else {
    $queue_name = 'General Queue';
}

// Set theme for this module
$_SESSION['current_theme'] = 'queue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($queue_name) ?> Queue | NIA-ACIMO AIMS</title>

    <?php include '../includes/header.php'; ?>

    <style>
        .queue-theme {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
        }

        .queue-badge {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
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

        .priority-queue {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            color: white;
        }

        .priority-badge {
            background: #dc3545 !important;
            color: white;
            font-weight: bold;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .current-serving-card {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .current-number {
            font-size: 96px;
            font-weight: bold;
            text-align: center;
            line-height: 1;
            margin: 10px 0;
        }

        .visitor-details {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .status-badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-buttons .btn {
            flex: 1;
        }

        .empty-queue {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .empty-queue i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .waiting-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .queue-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .queue-item:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .queue-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            min-width: 80px;
        }

        .queue-info {
            flex: 1;
            margin: 0 15px;
        }

        .queue-actions {
            min-width: 120px;
            text-align: right;
        }

        .priority-indicator {
            color: #dc3545;
            font-weight: bold;
            font-size: 12px;
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
                            <h1 class="m-0"><?= htmlspecialchars($queue_name) ?> Queue</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="queue.php">Queue Management</a></li>
                                <li class="breadcrumb-item active">Section Queue</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-4">
                            <!-- Current Serving Card -->
                            <div class="current-serving-card">
                                <h3 class="text-center mb-4">Now Serving</h3>
                                <div class="current-number" id="currentServingNumber">---</div>
                                <div class="visitor-details" id="currentVisitorDetails">
                                    <p class="text-center mb-0">No visitor being served</p>
                                </div>
                                <div class="action-buttons" id="currentActionButtons" style="display: none;">
                                    <button class="btn btn-success btn-lg" id="completeCurrentBtn">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                    <button class="btn btn-warning btn-lg" id="recallCurrentBtn">
                                        <i class="fas fa-redo"></i> Recall
                                    </button>
                                </div>
                            </div>

                            <!-- Section Statistics -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Queue Statistics</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="section-counter">
                                                <h5>Waiting</h5>
                                                <div class="counter-number text-warning" id="waitingCount">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="section-counter">
                                                <h5>Serving</h5>
                                                <div class="counter-number text-primary" id="servingCount">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row text-center mt-3">
                                        <div class="col-12">
                                            <div class="section-counter">
                                                <h5>Total Today</h5>
                                                <div class="counter-number text-success" id="totalToday">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Quick Actions</h3>
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-primary btn-block btn-lg mb-3" id="callNextBtn">
                                        <i class="fas fa-bullhorn"></i> Call Next Visitor
                                    </button>
                                    <button class="btn btn-info btn-block btn-lg mb-3" id="refreshQueueBtn">
                                        <i class="fas fa-sync-alt"></i> Refresh Queue
                                    </button>
                                    <?php if ($queue_type == 'section' || $queue_type == 'unit'): ?>
                                    <button class="btn btn-warning btn-block btn-lg" id="transferQueueBtn">
                                        <i class="fas fa-exchange-alt"></i> Transfer Queue
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <!-- Waiting List -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Waiting List</h3>
                                    <div class="card-tools">
                                        <div class="input-group input-group-sm" style="width: 200px;">
                                            <input type="text" name="table_search" class="form-control float-right" placeholder="Search visitor..." id="searchVisitor">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="waitingListContainer">
                                        <!-- Waiting list will be loaded here -->
                                        <div class="empty-queue" id="emptyQueueMessage">
                                            <i class="fas fa-users-slash"></i>
                                            <h4>No visitors waiting</h4>
                                            <p>Visitors will appear here when they register for your section</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Completed Visitors Today -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Completed Today</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="completedTable">
                                            <thead>
                                                <tr>
                                                    <th>Queue No.</th>
                                                    <th>Visitor Name</th>
                                                    <th>Purpose</th>
                                                    <th>Time In</th>
                                                    <th>Time Out</th>
                                                    <th>Duration</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Completed visitors will be loaded here -->
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

        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Transfer Queue Modal -->
    <div class="modal fade" id="transferModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transfer Queue</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Section/Unit to Transfer To:</label>
                        <select class="form-control select2" id="transferSection" style="width: 100%;">
                            <option value="">Select Section/Unit</option>
                            <?php
                            // Get all sections except current
                            $query = "SELECT section_id, section_name, section_code 
                                      FROM section 
                                      WHERE office_id = 1 
                                      AND section_id != ? 
                                      ORDER BY section_name";
                            $stmt = $db->prepare($query);
                            $stmt->bind_param("i", $current_section_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            echo '<optgroup label="Sections">';
                            while ($section = $result->fetch_assoc()) {
                                echo '<option value="section_' . $section['section_id'] . '">';
                                echo htmlspecialchars($section['section_name'] . ' (' . $section['section_code'] . ')');
                                echo '</option>';
                            }
                            echo '</optgroup>';
                            
                            // Get all units except current
                            $query = "SELECT unit_id, unit_name, unit_code, section_id 
                                      FROM unit_section 
                                      WHERE unit_id != ? 
                                      ORDER BY unit_name";
                            $stmt = $db->prepare($query);
                            $stmt->bind_param("i", $current_unit_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            echo '<optgroup label="Units">';
                            while ($unit = $result->fetch_assoc()) {
                                $section_name = '';
                                $query2 = "SELECT section_name FROM section WHERE section_id = ?";
                                $stmt2 = $db->prepare($query2);
                                $stmt2->bind_param("i", $unit['section_id']);
                                $stmt2->execute();
                                $result2 = $stmt2->get_result();
                                if ($row2 = $result2->fetch_assoc()) {
                                    $section_name = $row2['section_name'];
                                }
                                
                                echo '<option value="unit_' . $unit['unit_id'] . '">';
                                echo htmlspecialchars($unit['unit_name'] . ' (' . $unit['unit_code'] . ') - ' . $section_name);
                                echo '</option>';
                            }
                            echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason for Transfer:</label>
                        <textarea class="form-control" id="transferReason" rows="3" placeholder="Enter reason for transfer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmTransfer">Transfer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4'
            });

const queueInfo = {
    type: '<?= $queue_type ?>',
    section_id: <?= $current_section_id ?: 'null' ?>,
    unit_id: <?= $current_unit_id ?: 'null' ?>,
    is_manager_staff: <?= $is_manager_staff ? 'true' : 'false' ?>
};

            // Load initial data
            loadQueueData();
            loadCompletedVisitors();

            // Auto-refresh every 15 seconds
            setInterval(loadQueueData, 15000);
            setInterval(loadCompletedVisitors, 30000);

            // Call Next button
            $('#callNextBtn').click(function() {
                callNextVisitor();
            });

            // Refresh button
            $('#refreshQueueBtn').click(function() {
                loadQueueData();
                loadCompletedVisitors();
                Swal.fire({
                    title: 'Refreshed!',
                    text: 'Queue data has been refreshed',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            });

            // Transfer button
            $('#transferQueueBtn').click(function() {
                $('#transferModal').modal('show');
            });

            // Complete current visitor
            $('#completeCurrentBtn').click(function() {
                completeCurrentVisitor();
            });

            // Recall current visitor
            $('#recallCurrentBtn').click(function() {
                recallCurrentVisitor();
            });

            // Confirm transfer
            $('#confirmTransfer').click(function() {
                const targetSection = $('#transferSection').val();
                const reason = $('#transferReason').val().trim();

                if (!targetSection) {
                    Swal.fire('Error', 'Please select a section/unit to transfer to', 'error');
                    return;
                }

                if (!reason) {
                    Swal.fire('Error', 'Please enter a reason for transfer', 'error');
                    return;
                }

                transferQueue(targetSection, reason);
            });

            // Search functionality
            $('#searchVisitor').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.queue-item').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });

            // Load queue data
            function loadQueueData() {
                $.ajax({
                    url: '../includes/queue_ajax.php?action=get_section_queue',
                    type: 'POST',
                    data: queueInfo,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateCurrentServing(response.current_serving);
                            updateWaitingList(response.waiting_list);
                            updateStatistics(response.statistics);
                        } else {
                            console.error('Error loading queue data:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }

            // Load completed visitors
            function loadCompletedVisitors() {
                $.ajax({
                    url: '../includes/queue_ajax.php?action=get_completed_today',
                    type: 'POST',
                    data: queueInfo,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateCompletedTable(response.completed_visitors);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }

            // Update current serving display
            function updateCurrentServing(current) {
                const currentNumber = $('#currentServingNumber');
                const currentDetails = $('#currentVisitorDetails');
                const actionButtons = $('#currentActionButtons');
                
                if (current) {
                    currentNumber.text(current.queue_number);
                    
                    let detailsHtml = `
                        <div class="row">
                            <div class="col-12">
                                <h5><strong>${current.visitor_name}</strong></h5>
                                <p class="mb-1"><small>Purpose: ${current.purpose}</small></p>
                        `;
                    
                    if (current.company) {
                        detailsHtml += `<p class="mb-1"><small>Company: ${current.company}</small></p>`;
                    }
                    
                    if (current.person_to_visit) {
                        detailsHtml += `<p class="mb-1"><small>For: ${current.person_to_visit}</small></p>`;
                    }
                    
                    detailsHtml += `
                            </div>
                        </div>
                    `;
                    
                    currentDetails.html(detailsHtml);
                    actionButtons.show();
                    
                    // Update current queue ID
                    actionButtons.data('queue-id', current.id);
                } else {
                    currentNumber.text('---');
                    currentDetails.html('<p class="text-center mb-0">No visitor being served</p>');
                    actionButtons.hide();
                }
            }

            // Update waiting list
            function updateWaitingList(waitingList) {
                const container = $('#waitingListContainer');
                const emptyMessage = $('#emptyQueueMessage');
                
                if (waitingList.length === 0) {
                    container.html(emptyMessage.show());
                    return;
                }
                
                emptyMessage.hide();
                
                let html = '';
                waitingList.forEach(visitor => {
                    const isPriority = visitor.is_priority == '1';
                    const priorityBadge = isPriority ? 
                        '<span class="badge badge-danger priority-badge mr-2">PRIORITY</span>' : '';
                    
                    html += `
                        <div class="queue-item" data-queue-id="${visitor.id}">
                            <div class="queue-number">
                                ${priorityBadge}
                                ${visitor.queue_number}
                            </div>
                            <div class="queue-info">
                                <h6 class="mb-1"><strong>${visitor.visitor_name}</strong></h6>
                                <p class="mb-1 text-muted" style="font-size: 14px;">
                                    Purpose: ${visitor.purpose}
                                    ${visitor.company ? ' | Company: ' + visitor.company : ''}
                                </p>
                                <small class="text-muted">
                                    <i class="far fa-clock"></i> Waiting since: ${formatTime(visitor.time_in)}
                                </small>
                            </div>
                            <div class="queue-actions">
                                <button class="btn btn-sm btn-primary call-visitor-btn" data-id="${visitor.id}">
                                    <i class="fas fa-bullhorn"></i> Call
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                container.html(html);
                
                // Add click event to call buttons
                $('.call-visitor-btn').click(function() {
                    const queueId = $(this).data('id');
                    callSpecificVisitor(queueId);
                });
            }

            // Update statistics
            function updateStatistics(stats) {
                $('#waitingCount').text(stats.waiting_count || 0);
                $('#servingCount').text(stats.serving_count || 0);
                $('#totalToday').text(stats.total_today || 0);
            }

            // Update completed table
            function updateCompletedTable(completed) {
                const tbody = $('#completedTable tbody');
                
                if (completed.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center">No completed visitors today</td></tr>');
                    return;
                }
                
                let html = '';
                completed.forEach(visitor => {
                    const duration = calculateDuration(visitor.time_in, visitor.time_out);
                    
                    html += `
                        <tr>
                            <td><span class="badge badge-dark">${visitor.queue_number}</span></td>
                            <td>${visitor.visitor_name}</td>
                            <td>${visitor.purpose}</td>
                            <td>${formatTime(visitor.time_in)}</td>
                            <td>${formatTime(visitor.time_out)}</td>
                            <td>${duration}</td>
                        </tr>
                    `;
                });
                
                tbody.html(html);
            }

            // Call next visitor
            function callNextVisitor() {
                $.ajax({
                    url: '../includes/queue_ajax.php?action=call_next_section',
                    type: 'POST',
                    data: queueInfo,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadQueueData();
                            playNotificationSound();
                            
                            Swal.fire({
                                title: 'Visitor Called!',
                                html: `Queue Number: <h3 class="text-primary">${response.queue_number}</h3>`,
                                icon: 'info',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Info',
                                text: response.message || 'No visitors waiting in queue',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to call next visitor', 'error');
                    }
                });
            }

            // Call specific visitor
            function callSpecificVisitor(queueId) {
                $.ajax({
                    url: '../includes/queue_ajax.php?action=call_specific_visitor',
                    type: 'POST',
                    data: {
                        queue_id: queueId,
                        ...queueInfo
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadQueueData();
                            playNotificationSound();
                            
                            Swal.fire({
                                title: 'Visitor Called!',
                                html: `Queue Number: <h3 class="text-primary">${response.queue_number}</h3>`,
                                icon: 'info',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to call visitor', 'error');
                    }
                });
            }

            // Complete current visitor
            function completeCurrentVisitor() {
                const queueId = $('#currentActionButtons').data('queue-id');
                
                if (!queueId) {
                    Swal.fire('Error', 'No visitor to complete', 'error');
                    return;
                }
                
                $.ajax({
                    url: '../includes/queue_ajax.php?action=complete_visitor',
                    type: 'POST',
                    data: {
                        queue_id: queueId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadQueueData();
                            loadCompletedVisitors();
                            
                            Swal.fire({
                                title: 'Completed!',
                                text: 'Visitor service completed successfully',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to complete visitor', 'error');
                    }
                });
            }

            // Recall current visitor
            function recallCurrentVisitor() {
                const queueId = $('#currentActionButtons').data('queue-id');
                
                if (!queueId) {
                    Swal.fire('Error', 'No visitor to recall', 'error');
                    return;
                }
                
                $.ajax({
                    url: '../includes/queue_ajax.php?action=recall_visitor',
                    type: 'POST',
                    data: {
                        queue_id: queueId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            playNotificationSound();
                            
                            Swal.fire({
                                title: 'Visitor Recalled!',
                                html: `Queue Number: <h3 class="text-primary">${response.queue_number}</h3>`,
                                icon: 'info',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to recall visitor', 'error');
                    }
                });
            }

            // Transfer queue
            function transferQueue(targetSection, reason) {
                $.ajax({
                    url: '../includes/queue_ajax.php?action=transfer_queue',
                    type: 'POST',
                    data: {
                        target_section: targetSection,
                        reason: reason,
                        ...queueInfo
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#transferModal').modal('hide');
                            loadQueueData();
                            
                            Swal.fire({
                                title: 'Transferred!',
                                text: 'Queue has been transferred successfully',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to transfer queue', 'error');
                    }
                });
            }

            // Utility functions
            function formatTime(dateTime) {
                if (!dateTime) return 'N/A';
                const date = new Date(dateTime);
                let hours = date.getHours();
                let minutes = date.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                
                hours = hours % 12;
                hours = hours ? hours : 12;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                
                return hours + ':' + minutes + ' ' + ampm;
            }

            function calculateDuration(start, end) {
                if (!start || !end) return 'N/A';
                
                const startTime = new Date(start);
                const endTime = new Date(end);
                const diffMs = endTime - startTime;
                const diffMins = Math.floor(diffMs / 60000);
                
                if (diffMins < 60) {
                    return diffMins + ' mins';
                } else {
                    const hours = Math.floor(diffMins / 60);
                    const mins = diffMins % 60;
                    return hours + 'h ' + mins + 'm';
                }
            }

            function playNotificationSound() {
                const audio = new Audio('../dist/sounds/bell.wav');
                audio.play().catch(e => console.log("Audio play failed:", e));
            }
        });
    </script>
</body>
</html>
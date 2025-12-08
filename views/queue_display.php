<?php
// queue_display.php
require_once '../config/database.php';

// Connect to database to get initial data
$database = new Database();
$db = $database->getConnection();

// Get sections for the display
$sections = [];
$query = "SELECT section_id, section_name, section_code FROM section WHERE office_id = 1 ORDER BY section_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
}

// Get units for the display
$units = [];
$query = "SELECT unit_id, unit_name, unit_code FROM unit_section ORDER BY unit_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
}

// Add manager's office as a virtual section with IMO code
$manager_office = [
    'section_id' => 0,
    'section_name' => "IMO Office",
    'section_code' => 'IMO'
];
array_unshift($sections, $manager_office);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Queue Display | NIA-ACIMO AIMS</title>

    <?php include '../includes/header.php'; ?>

    <style>
        .queue-display {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            min-height: 100vh;
            padding: 20px;
        }

        .now-serving {
            background: #e74c3c;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            50% {
                box-shadow: 0 10px 30px rgba(231, 76, 60, 0.8);
            }

            100% {
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }
        }

        .now-serving-number {
            font-size: 120px;
            font-weight: bold;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .upcoming-queue {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            min-height: 350px;
        }

        .queue-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .queue-item.called {
            background: rgba(52, 152, 219, 0.3);
            border-left: 4px solid #3498db;
        }

        .queue-item.waiting {
            background: rgba(243, 156, 18, 0.3);
            border-left: 4px solid #f39c12;
        }

        .queue-item.served {
            background: rgba(46, 204, 113, 0.3);
            border-left: 4px solid #2ecc71;
        }

        .queue-number {
            font-size: 24px;
            font-weight: bold;
            min-width: 120px;
        }

        .section-display {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #3498db;
            transition: all 0.3s ease;
        }

        .section-display.active {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .unit-display {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #9b59b6;
            transition: all 0.3s ease;
        }

        .section-number {
            font-size: 48px;
            font-weight: bold;
            margin: 10px 0;
        }

        .marquee {
            background: #f39c12;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }

        .marquee-content {
            display: inline-block;
            white-space: nowrap;
            animation: marquee 30s linear infinite;
            color: #fff;
            font-weight: bold;
            font-size: 18px;
        }

        @keyframes marquee {
            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        .clock-display {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 10px;
            display: inline-block;
        }

        .date-display {
            font-size: 24px;
            text-align: center;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-waiting {
            background: #f39c12;
        }

        .status-called {
            background: #3498db;
        }

        .status-serving {
            background: #9b59b6;
        }

        .counter-info {
            font-size: 14px;
            margin-top: 5px;
        }

        .counter-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .counter-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .counter-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .counter-number {
            font-size: 36px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }

        .counter-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
        }

        .counter-stat {
            text-align: center;
        }

        .counter-stat-value {
            font-size: 20px;
            font-weight: bold;
        }

        .counter-stat-label {
            font-size: 12px;
            opacity: 0.8;
        }
    </style>
</head>

<body class="queue-display">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 text-center mb-4">
                <h1 class="display-4 mb-3">NIA-ACIMO QUEUE SYSTEM</h1>
                <p class="lead mb-4">Visitor Management Queue Display</p>
                <div class="d-flex justify-content-center align-items-center mb-4">
                    <div class="clock-display mr-3" id="digitalClock">--:--:--</div>
                    <div class="clock-display" id="currentDate">-- --- ----</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="now-serving">
                    <h2>NOW SERVING</h2>
                    <div class="now-serving-number" id="currentQueue">---</div>
                    <h3 id="currentVisitor">Please proceed to the assigned section</h3>
                    <div id="currentSection" class="h4">---</div>
                    <div id="currentCounter" class="mt-3">
                        <span class="badge badge-light" style="font-size: 16px; padding: 8px 15px;">
                            <i class="fas fa-clock mr-2"></i> Waiting Time: <span id="waitTime">0 min</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="upcoming-queue">
                    <h3><i class="fas fa-clock"></i> WAITING QUEUE <span class="badge badge-warning ml-2" id="waitingCount">0</span></h3>
                    <div id="waitingQueue" class="mt-3">
                        <!-- Waiting queue will be loaded here -->
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors waiting</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="upcoming-queue">
                    <h3><i class="fas fa-user-check"></i> RECENTLY SERVED <span class="badge badge-success ml-2" id="servedCount">0</span></h3>
                    <div id="servedQueue" class="mt-3">
                        <!-- Served queue will be loaded here -->
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors served yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="marquee">
                    <div class="marquee-content">
                        <i class="fas fa-info-circle"></i> Welcome to NIA-ACIMO. Please have your visitor pass ready. Thank you for your patience. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <i class="fas fa-info-circle"></i> Please maintain social distancing and wear your mask properly. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <i class="fas fa-info-circle"></i> For inquiries, please approach the Information Desk. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Counters Section -->
        <div class="row mt-4">
            <div class="col-12">
                <h3 class="text-center mb-4"><i class="fas fa-tachometer-alt"></i> SECTION & UNIT COUNTERS</h3>
                <div id="dynamicCounters" class="counter-container">

                    <?php foreach ($sections as $section): ?>
                        <div class="counter-card" id="counter-<?= $section['section_code'] ?>" data-type="section" data-id="<?= $section['section_id'] ?>">
                            <div class="counter-title">
                                <span><?= htmlspecialchars($section['section_name']) ?></span>
                                <span class="badge badge-info"><?= $section['section_code'] ?></span>
                            </div>
                            <div class="counter-number" id="number-<?= $section['section_code'] ?>">---</div>
                            <div class="counter-stats">
                                <div class="counter-stat">
                                    <div class="counter-stat-value" id="waiting-<?= $section['section_code'] ?>">0</div>
                                    <div class="counter-stat-label">Waiting</div>
                                </div>
                                <div class="counter-stat">
                                    <div class="counter-stat-value" id="serving-<?= $section['section_code'] ?>">0</div>
                                    <div class="counter-stat-label">Serving</div>
                                </div>
                                <div class="counter-stat">
                                    <div class="counter-stat-value" id="today-<?= $section['section_code'] ?>">0</div>
                                    <div class="counter-stat-label">Today</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

    <script>
        $(document).ready(function() {
            let lastQueueNumber = '';
            let refreshInterval = 5000; // Refresh every 5 seconds
            let currentServingSectionCode = '';
            let currentServingUnitCode = '';

            // Update digital clock
            function updateClock() {
                const now = new Date();

                // Format time in 12-hour format with AM/PM
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                // Convert to 12-hour format
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'

                // Add leading zeros
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;

                const time = hours + ':' + minutes + ':' + seconds + ' ' + ampm;

                const date = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                $('#digitalClock').text(time);
                $('#currentDate').text(date);
            }

            // Update clock immediately and every second
            updateClock();
            setInterval(updateClock, 1000);

            // Initial load
            updateQueueDisplay();

            // Auto-refresh
            setInterval(updateQueueDisplay, refreshInterval);

            // Play sound when queue updates
            function playQueueSound() {
                try {
                    const audio = new Audio('../dist/sounds/queue-bell.mp3');
                    audio.volume = 0.5;
                    audio.play().catch(e => console.log("Audio play failed:", e));
                } catch (e) {
                    console.log("Sound error:", e);
                }
            }

            // Update display with current queue data
            function updateQueueDisplay() {
                console.log('Fetching queue data...');

                $.ajax({
                    url: '../includes/queue_ajax.php?action=get_display_data',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Queue data response:', response);

                        if (response.success) {
                            // Update current serving
                            if (response.current_serving && response.current_serving.queue_number) {
                                $('#currentQueue').text(response.current_serving.queue_number);
                                $('#currentVisitor').text(response.current_serving.visitor_name);

                                const sectionName = response.current_serving.section_name || '';
                                const unitName = response.current_serving.unit_name || '';
                                const sectionCode = response.current_serving.section_code || '';
                                const unitCode = response.current_serving.unit_code || '';

                                $('#currentSection').text(
                                    sectionName || unitName || 'General Queue'
                                );

                                // Check if queue number changed
                                if (lastQueueNumber !== response.current_serving.queue_number) {
                                    lastQueueNumber = response.current_serving.queue_number;
                                    playQueueSound();

                                    // Add visual effect
                                    $('#currentQueue').css({
                                        'transform': 'scale(1.1)',
                                        'color': '#fff'
                                    });
                                    setTimeout(() => {
                                        $('#currentQueue').css({
                                            'transform': 'scale(1)',
                                            'color': '#fff'
                                        });
                                    }, 500);

                                    // Update active counter highlight
                                    updateActiveCounter(sectionCode, unitCode);
                                }

                                // Store current serving codes
                                currentServingSectionCode = sectionCode;
                                currentServingUnitCode = unitCode;
                            } else {
                                $('#currentQueue').text('---');
                                $('#currentVisitor').text('No visitors in queue');
                                $('#currentSection').text('Please wait for your turn');
                                currentServingSectionCode = '';
                                currentServingUnitCode = '';

                                // Remove active highlight
                                $('.counter-card').removeClass('active');
                            }

                            // Update waiting queue
                            if (response.waiting_queue && response.waiting_queue.length > 0) {
                                let waitingHtml = '';
                                response.waiting_queue.forEach(function(visitor) {
                                    const statusClass = visitor.status === 'called' ? 'called' : 'waiting';
                                    const statusText = visitor.status === 'called' ? 'CALLED' : 'WAITING';
                                    const statusColor = visitor.status === 'called' ? 'status-called' : 'status-waiting';

                                    waitingHtml += `
                            <div class="queue-item ${statusClass}">
                                <div class="queue-number">${visitor.queue_number}</div>
                                <div class="flex-grow-1 ml-3">
                                    <div class="font-weight-bold">${visitor.visitor_name}</div>
                                    <div class="small">
                                        <span class="${statusColor} status-badge">${statusText}</span>
                                        <span class="ml-2">${visitor.section_name || visitor.unit_name || 'General'}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="small">${formatTime(visitor.time_in)}</div>
                                    <div class="small text-muted">${visitor.purpose || ''}</div>
                                </div>
                            </div>
                        `;
                                });
                                $('#waitingQueue').html(waitingHtml);
                                $('#waitingCount').text(response.waiting_queue.length);
                            } else {
                                $('#waitingQueue').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors waiting</p>
                        </div>
                    `);
                                $('#waitingCount').text('0');
                            }

                            // Update served queue
                            if (response.served_queue && response.served_queue.length > 0) {
                                let servedHtml = '';
                                response.served_queue.forEach(function(visitor) {
                                    servedHtml += `
                            <div class="queue-item served">
                                <div class="queue-number">${visitor.queue_number}</div>
                                <div class="flex-grow-1 ml-3">
                                    <div class="font-weight-bold">${visitor.visitor_name}</div>
                                    <div class="small">
                                        ${visitor.section_name || visitor.unit_name || 'General'}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="small">${formatTime(visitor.time_completed || visitor.time_in)}</div>
                                    <div class="small text-success">
                                        <i class="fas fa-check-circle"></i> Served
                                    </div>
                                </div>
                            </div>
                        `;
                                });
                                $('#servedQueue').html(servedHtml);
                                $('#servedCount').text(response.served_queue.length);
                            } else {
                                $('#servedQueue').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors served yet</p>
                        </div>
                    `);
                                $('#servedCount').text('0');
                            }

                            // Debug: Check what sections are returned
                            console.log('Sections in response:', response.sections);
                            console.log('Looking for IMO section:', response.sections?.find(s => s.section_code === 'IMO'));

                            // Update dynamic section counters
                            if (response.sections && response.sections.length > 0) {
                                response.sections.forEach(function(section) {
                                    const sectionCode = section.section_code || '';
                                    const sectionId = sectionCode.toUpperCase();

                                    // Find the counter element
                                    const counterElement = $(`#counter-${sectionId}`);

                                    if (counterElement.length) {
                                        // Update current serving number
                                        const currentElement = $(`#number-${sectionId}`);
                                        if (section.current_serving && section.current_serving !== '---') {
                                            currentElement.text(section.current_serving);
                                            currentElement.css('color', '#fff');
                                        } else {
                                            currentElement.text('---');
                                            currentElement.css('color', 'rgba(255,255,255,0.7)');
                                        }

                                        // Update waiting count
                                        const waitingElement = $(`#waiting-${sectionId}`);
                                        if (waitingElement.length) {
                                            waitingElement.text(section.waiting_count || '0');
                                        }

                                        // Update serving count
                                        const servingElement = $(`#serving-${sectionId}`);
                                        if (servingElement.length) {
                                            servingElement.text(section.serving_count || '0');
                                        }

                                        // Update total today
                                        const todayElement = $(`#today-${sectionId}`);
                                        if (todayElement.length) {
                                            todayElement.text(section.total_today || '0');
                                        }
                                    } else {
                                        console.warn(`Counter element not found for: ${sectionId}`);
                                        console.warn('Available section codes:', response.sections?.map(s => s.section_code));
                                    }
                                });
                            }

                            // Update dynamic unit counters
                            if (response.units && response.units.length > 0) {
                                response.units.forEach(function(unit) {
                                    const unitCode = unit.unit_code || '';
                                    const unitId = unitCode.toUpperCase();

                                    // Find the counter element
                                    const counterElement = $(`#counter-${unitId}`);

                                    if (counterElement.length) {
                                        // Update current serving number
                                        const currentElement = $(`#number-${unitId}`);
                                        if (unit.current_serving && unit.current_serving !== '---') {
                                            currentElement.text(unit.current_serving);
                                            currentElement.css('color', '#fff');
                                        } else {
                                            currentElement.text('---');
                                            currentElement.css('color', 'rgba(255,255,255,0.7)');
                                        }

                                        // Update waiting count
                                        const waitingElement = $(`#waiting-${unitId}`);
                                        if (waitingElement.length) {
                                            waitingElement.text(unit.waiting_count || '0');
                                        }

                                        // Update serving count
                                        const servingElement = $(`#serving-${unitId}`);
                                        if (servingElement.length) {
                                            servingElement.text(unit.serving_count || '0');
                                        }

                                        // Update total today
                                        const todayElement = $(`#today-${unitId}`);
                                        if (todayElement.length) {
                                            todayElement.text(unit.total_today || '0');
                                        }
                                    }
                                });
                            }

                            // Update wait time estimate
                            updateWaitTime(response.waiting_queue ? response.waiting_queue.length : 0);

                        } else {
                            console.error('Response success false:', response.message);
                            showErrorMessage('Failed to load queue data: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        showErrorMessage('Connection error. Retrying...');
                    }
                });
            }

            // Update active counter highlight
            function updateActiveCounter(sectionCode, unitCode) {
                // Remove active class from all counters
                $('.counter-card').removeClass('active');

                // Add active class to the current serving counter
                if (sectionCode) {
                    $(`#counter-${sectionCode}`).addClass('active');
                    $(`#counter-${sectionCode}`).css('background', '#e74c3c');
                } else if (unitCode) {
                    $(`#counter-${unitCode}`).addClass('active');
                    $(`#counter-${unitCode}`).css('background', '#e74c3c');
                }
            }

            // Format time to HH:MM AM/PM
            function formatTime(timeString) {
                if (!timeString) return '--:--';
                const date = new Date(timeString);
                let hours = date.getHours();
                let minutes = date.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12;

                minutes = minutes < 10 ? '0' + minutes : minutes;

                return hours + ':' + minutes + ' ' + ampm;
            }

            // Calculate wait time
            function updateWaitTime(waitingCount) {
                const avgWaitTime = 5; // minutes per visitor
                const waitTime = waitingCount * avgWaitTime;

                if (waitTime === 0) {
                    $('#waitTime').text('Ready');
                } else if (waitTime < 60) {
                    $('#waitTime').text(waitTime + ' min');
                } else {
                    const hours = Math.floor(waitTime / 60);
                    const minutes = waitTime % 60;
                    $('#waitTime').text(hours + 'h ' + minutes + 'm');
                }
            }

            // Show error message
            function showErrorMessage(message) {
                $('#currentQueue').text('ERR');
                $('#currentVisitor').text(message);
                $('#currentSection').text('Please refresh the page');
                $('#currentQueue').css('color', '#ff6b6b');
            }

            // Manual refresh button (optional - add if needed)
            $(document).on('keypress', function(e) {
                if (e.key === 'r' || e.key === 'R') {
                    updateQueueDisplay();
                    showToast('Refreshing queue data...');
                }
            });

            // Show toast notification
            function showToast(message) {
                const toast = $(`
                <div class="toast" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
                    <div class="toast-body bg-dark text-white">
                        ${message}
                    </div>
                </div>
            `);
                $('body').append(toast);
                toast.toast({
                    delay: 2000
                });
                toast.toast('show');
                setTimeout(() => toast.remove(), 2000);
            }

            // Initial load message
            showToast('Queue display loaded. Auto-refreshing every 5 seconds...');
        });
    </script>
</body>

</html>
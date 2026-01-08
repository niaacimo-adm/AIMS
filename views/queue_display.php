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

// Get units for the display, organized by section
$units_by_section = [];
$query = "SELECT unit_id, unit_name, unit_code, section_id FROM unit_section ORDER BY unit_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $section_id = $row['section_id'];
        if (!isset($units_by_section[$section_id])) {
            $units_by_section[$section_id] = [];
        }
        $units_by_section[$section_id][] = $row;
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

// Get units for the display, organized by section
$units_by_section = [];
$query = "SELECT unit_id, unit_name, unit_code, section_id FROM unit_section ORDER BY unit_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $section_id = $row['section_id'];
        if (!isset($units_by_section[$section_id])) {
            $units_by_section[$section_id] = [];
        }
        $units_by_section[$section_id][] = $row;
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

// Get units for the display, organized by section
$units_by_section = [];
$query = "SELECT unit_id, unit_name, unit_code, section_id FROM unit_section ORDER BY unit_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $section_id = $row['section_id'];
        if (!isset($units_by_section[$section_id])) {
            $units_by_section[$section_id] = [];
        }
        $units_by_section[$section_id][] = $row;
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

// Get units for the display, organized by section
$units_by_section = [];
$query = "SELECT unit_id, unit_name, unit_code, section_id FROM unit_section ORDER BY unit_name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $section_id = $row['section_id'];
        if (!isset($units_by_section[$section_id])) {
            $units_by_section[$section_id] = [];
        }
        $units_by_section[$section_id][] = $row;
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

        /* NOW SERVING CARD STYLES */
        .now-serving {
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: pulse 2s infinite;
            transition: all 0.3s ease;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .now-serving.priority {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            /* Red gradient for priority */
        }

        .now-serving.regular {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            /* Sky blue gradient for regular */
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            50% {
                box-shadow: 0 10px 30px rgba(231, 76, 60, 0.6);
            }

            100% {
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }
        }

        .now-serving-number {
            font-size: 100px;
            font-weight: bold;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            margin: 10px 0;
        }

        .queue-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .queue-badge.priority {
            background: rgba(255, 255, 255, 0.3);
            color: #ffdddd;
            border: 2px solid #ffdddd;
        }

        .queue-badge.regular {
            background: rgba(255, 255, 255, 0.3);
            color: #ddddff;
            border: 2px solid #ddddff;
        }

        .serving-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .serving-card {
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .serving-card.priority {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            border: 3px solid #ff6b6b;
        }

        .serving-card.regular {
            background: linear-gradient(135deg, rgb(30, 60, 114), rgb(42, 82, 152));
            border: 3px solid #3474dbff;
        }

        .serving-card.empty {
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .serving-card-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .serving-number {
            font-size: 80px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .visitor-info {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-label {
            font-weight: bold;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .info-value {
            font-weight: normal;
            color: white;
            font-size: 14px;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 18px;
            opacity: 0.7;
        }

        .upcoming-queue {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            min-height: 350px;
        }

        /* Updated queue items grid */
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .queue-grid-item {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 80px;
        }

        .queue-grid-item.priority {
            background: rgba(231, 76, 60, 0.3);
            border: 2px solid #e74c3c;
        }

        .queue-grid-item.regular {
            background: rgba(52, 152, 219, 0.3);
            border: 2px solid #3498db;
        }

        .queue-grid-item.called {
            background: rgba(52, 152, 219, 0.5);
            border: 2px solid #3498db;
        }

        .queue-grid-item.waiting {
            background: rgba(243, 156, 18, 0.3);
            border: 2px solid #f39c12;
        }

        .queue-grid-item.served {
            background: rgba(46, 204, 113, 0.3);
            border: 2px solid #2ecc71;
        }

        .queue-grid-number {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .queue-grid-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 5px;
        }

        .more-count {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-top: 10px;
            font-size: 16px;
            font-weight: bold;
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
            border-left: 4px solid #505151ff;
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
            font-size: 20px;
            font-weight: bold;
            min-width: 120px;
        }

        .queue-number .badge {
            font-size: 10px;
            padding: 2px 6px;
            margin-bottom: 3px;
        }

        .section-card {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #505151ff;
            transition: all 0.3s ease;
        }

        .section-card.active {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .section-card.active.priority {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
        }

        .section-card.active.regular {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }

        .section-card.active.both {
            background: linear-gradient(135deg, #c0392b, #e74c3c, #1e3c72, #2a5298);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-name {
            font-size: 18px;
            font-weight: bold;
        }

        .section-code {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }

        .section-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
        }

        .stat-label {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 5px;
        }

        .unit-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            border-left: 4px solid #9b59b6;
            transition: all 0.3s ease;
        }

        .unit-card.active {
            transform: scale(1.02);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .unit-card.active.priority {
            background: rgba(231, 76, 60, 0.6);
            border-left: 4px solid #e74c3c;
        }

        .unit-card.active.regular {
            background: rgba(52, 152, 219, 0.6);
            border-left: 4px solid #3498db;
        }

        .unit-card.active.both {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.6), rgba(52, 152, 219, 0.6));
            border-left: 4px solid #9b59b6;
        }

        .unit-list {
            margin-top: 10px;
        }

        .unit-header {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
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
            font-size: 16px;
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
            font-size: 40px;
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
            font-size: 20px;
            text-align: center;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-waiting {
            background: #f39c12;
        }

        .status-called {
            background: #505151ff;
        }

        .status-serving {
            background: #9b59b6;
        }

        .counter-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .counter-number {
            font-size: 30px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }

        .no-units {
            text-align: center;
            padding: 15px;
            font-style: italic;
            opacity: 0.7;
            font-size: 13px;
        }

        .waiting-time {
            background: rgba(0, 0, 0, 0.2);
            padding: 10px 15px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 15px;
            font-size: 16px;
        }

        .current-serving-title {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #f1c40f;
        }

        /* Priority indicator in queue lists */
        .priority-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .priority-indicator.priority {
            background: #e74c3c;
            box-shadow: 0 0 5px #e74c3c;
        }

        .priority-indicator.regular {
            background: #3498db;
            box-shadow: 0 0 5px #3498db;
        }
        /* Add these styles if they don't exist */
        .section-card.active.both {
            background: linear-gradient(135deg, #c0392b, #e74c3c, #1e3c72, #2a5298);
        }

        .unit-card.active.both {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.6), rgba(52, 152, 219, 0.6));
            border-left: 4px solid #9b59b6;
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

        <!-- NOW SERVING SECTION -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="current-serving-title">
                    <i class="fas fa-user-check"></i> CURRENTLY SERVING
                </h2>
            </div>
        </div>

        <!-- SEPARATE CARDS FOR PRIORITY AND REGULAR -->
        <div class="row">
            <div class="col-md-6">
                <!-- PRIORITY SERVING CARD -->
                <div class="serving-card priority" id="priorityServingCard">
                    <div class="serving-card-title">
                        <i class="fas fa-star mr-2"></i> PRIORITY SERVICE
                    </div>
                    <div class="serving-number" id="priorityCurrentQueue">---</div>
                    <div class="text-center mt-3">
                        <span class="badge waiting-time">
                            <i class="fas fa-clock mr-2"></i>
                            Priority Queue: <span id="priorityWaitTime">Ready</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- REGULAR SERVING CARD -->
                <div class="serving-card regular" id="regularServingCard">
                    <div class="serving-card-title">
                        <i class="fas fa-users mr-2"></i> REGULAR SERVICE
                    </div>
                    <div class="serving-number" id="regularCurrentQueue">---</div>
                    <div class="text-center mt-3">
                        <span class="badge waiting-time">
                            <i class="fas fa-clock mr-2"></i>
                            Estimated Wait: <span id="regularWaitTime">0 min</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- WAITING AND SERVED QUEUES -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="upcoming-queue">
                    <h3>
                        <i class="fas fa-clock"></i> WAITING QUEUE
                        <span class="badge badge-warning ml-2" id="waitingCount">0</span>
                    </h3>
                    <div id="waitingQueue" class="mt-3">
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors waiting</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="upcoming-queue">
                    <h3>
                        <i class="fas fa-user-check"></i> RECENTLY SERVED
                        <span class="badge badge-success ml-2" id="servedCount">0</span>
                    </h3>
                    <div id="servedQueue" class="mt-3">
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x mb-3" style="opacity: 0.5;"></i>
                            <p>No visitors served yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MARQUEE -->
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

        <!-- SECTION & UNIT COUNTERS -->
        <div class="row mt-4">
            <div class="col-12">
                <h3 class="text-center mb-4">
                    <i class="fas fa-tachometer-alt"></i> SECTION & UNIT COUNTERS
                </h3>
                <div id="dynamicCounters" class="counter-container">
                    <?php foreach ($sections as $section): ?>
                        <div class="section-card" id="section-<?= $section['section_code'] ?>"
                            data-section-id="<?= $section['section_id'] ?>">
                            <div class="section-header">
                                <div class="section-name"><?= htmlspecialchars($section['section_name']) ?></div>
                                <div class="section-code"><?= $section['section_code'] ?></div>
                            </div>

                            <div class="section-stats">
                                <div class="stat-box">
                                    <div class="stat-value" id="current-<?= $section['section_code'] ?>">---</div>
                                    <div class="stat-label">Now Serving</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="waiting-<?= $section['section_code'] ?>">0</div>
                                    <div class="stat-label">Waiting</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="today-<?= $section['section_code'] ?>">0</div>
                                    <div class="stat-label">Today</div>
                                </div>
                            </div>

                            <?php if (isset($units_by_section[$section['section_id']]) && !empty($units_by_section[$section['section_id']])): ?>
                                <div class="unit-list">
                                    <div class="unit-header">Units:</div>
                                    <?php foreach ($units_by_section[$section['section_id']] as $unit): ?>
                                        <div class="unit-card" id="unit-<?= $unit['unit_code'] ?>"
                                            data-unit-id="<?= $unit['unit_id'] ?>"
                                            data-parent-section="<?= $section['section_code'] ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($unit['unit_name']) ?></strong>
                                                    <span class="ml-2">(<?= $unit['unit_code'] ?>)</span>
                                                </div>
                                                <div class="unit-status">
                                                    <span class="badge badge-info" id="unit-waiting-<?= $unit['unit_code'] ?>">0</span>
                                                </div>
                                            </div>
                                            <div class="text-center mt-2">
                                                <div class="unit-serving" id="unit-current-<?= $unit['unit_code'] ?>">
                                                    ---
                                                </div>
                                                <small class="text-muted">Now Serving</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-units">
                                    <i class="fas fa-info-circle"></i> No units assigned to this section
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        $(document).ready(function() {
            let lastPriorityQueueNumber = '';
            let lastRegularQueueNumber = '';
            let refreshInterval = 5000; // Refresh every 5 seconds
            let priorityWaitingCount = 0;
            let regularWaitingCount = 0;

            // Update digital clock
            function updateClock() {
                const now = new Date();
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12;
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
                            // Store current priority and regular section/unit codes
                            let currentPrioritySectionCode = null;
                            let currentPriorityUnitCode = null;
                            let currentRegularSectionCode = null;
                            let currentRegularUnitCode = null;

                            // Update priority serving
                            if (response.current_priority && response.current_priority.queue_number) {
                                const current = response.current_priority;
                                $('#priorityCurrentQueue').text(current.queue_number);

                                // Update visitor info for priority
                                const priorityInfoHtml = `
                                    <div class="info-row">
                                        <span class="info-label">Visitor:</span>
                                        <span class="info-value">${current.visitor_name}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Section/Unit:</span>
                                        <span class="info-value">${current.section_name || current.unit_name || 'General'}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Purpose:</span>
                                        <span class="info-value">${current.purpose || ''}</span>
                                    </div>
                                `;
                                $('#priorityVisitorInfo').html(priorityInfoHtml);

                                // Store section/unit codes for priority
                                currentPrioritySectionCode = current.section_code;
                                currentPriorityUnitCode = current.unit_code;

                                // Check if queue number changed
                                if (lastPriorityQueueNumber !== current.queue_number) {
                                    lastPriorityQueueNumber = current.queue_number;
                                    playQueueSound();

                                    // Add visual effect
                                    $('#priorityCurrentQueue').css({
                                        'transform': 'scale(1.1)',
                                        'color': '#fff'
                                    });
                                    setTimeout(() => {
                                        $('#priorityCurrentQueue').css({
                                            'transform': 'scale(1)',
                                            'color': '#fff'
                                        });
                                    }, 500);
                                }
                            } else {
                                $('#priorityCurrentQueue').text('---');
                                $('#priorityVisitorInfo').html(`
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-user-clock"></i>
                                        </div>
                                        <div class="empty-text">
                                            No priority visitors currently being served
                                        </div>
                                    </div>
                                `);
                            }

                            // Update regular serving
                            if (response.current_regular && response.current_regular.queue_number) {
                                const current = response.current_regular;
                                $('#regularCurrentQueue').text(current.queue_number);

                                // Update visitor info for regular
                                const regularInfoHtml = `
                                    <div class="info-row">
                                        <span class="info-label">Visitor:</span>
                                        <span class="info-value">${current.visitor_name}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Section/Unit:</span>
                                        <span class="info-value">${current.section_name || current.unit_name || 'General'}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Purpose:</span>
                                        <span class="info-value">${current.purpose || ''}</span>
                                    </div>
                                `;
                                $('#regularVisitorInfo').html(regularInfoHtml);

                                // Store section/unit codes for regular
                                currentRegularSectionCode = current.section_code;
                                currentRegularUnitCode = current.unit_code;

                                // Check if queue number changed
                                if (lastRegularQueueNumber !== current.queue_number) {
                                    lastRegularQueueNumber = current.queue_number;
                                    playQueueSound();

                                    // Add visual effect
                                    $('#regularCurrentQueue').css({
                                        'transform': 'scale(1.1)',
                                        'color': '#fff'
                                    });
                                    setTimeout(() => {
                                        $('#regularCurrentQueue').css({
                                            'transform': 'scale(1)',
                                            'color': '#fff'
                                        });
                                    }, 500);
                                }
                            } else {
                                $('#regularCurrentQueue').text('---');
                                $('#regularVisitorInfo').html(`
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-user-clock"></i>
                                        </div>
                                        <div class="empty-text">
                                            No regular visitors currently being served
                                        </div>
                                    </div>
                                `);
                            }

                            // Update active highlights for BOTH priority and regular
                            updateActiveHighlight(currentPrioritySectionCode, currentPriorityUnitCode,
                                currentRegularSectionCode, currentRegularUnitCode);

                            // Update waiting queue (Show only 6 in 3 columns)
                            if (response.waiting_queue && response.waiting_queue.length > 0) {
                                let waitingHtml = '<div class="queue-grid">';
                                let priorityCount = 0;
                                let regularCount = 0;

                                // Show only first 6 visitors
                                const maxDisplay = 6;
                                const displayQueue = response.waiting_queue.slice(0, maxDisplay);

                                displayQueue.forEach(function(visitor, index) {
                                    const statusClass = visitor.status === 'called' ? 'called' : 'waiting';
                                    // FIX: Use explicit check for priority
                                    const isPriority = (visitor.is_priority === true || visitor.is_priority === '1' || visitor.queue_type === 'priority');

                                    if (isPriority) priorityCount++;
                                    else regularCount++;

                                    waitingHtml += `
        <div class="queue-grid-item ${statusClass} ${isPriority ? 'priority' : 'regular'}">
            <div class="queue-grid-number">${visitor.queue_number}</div>
            ${isPriority ? 
                '<span class="badge badge-danger queue-grid-badge"><i class="fas fa-star mr-1"></i> PRIORITY</span>' : 
                '<span class="badge badge-primary queue-grid-badge">REGULAR</span>'}
        </div>
    `;
                                });

                                // Fill empty slots if less than 6
                                for (let i = displayQueue.length; i < maxDisplay; i++) {
                                    waitingHtml += `
                                        <div class="queue-grid-item" style="background: rgba(255,255,255,0.05); border: 2px dashed rgba(255,255,255,0.2);">
                                            <div class="queue-grid-number" style="opacity: 0.5;">---</div>
                                            <span class="badge badge-secondary queue-grid-badge" style="opacity: 0.5;">EMPTY</span>
                                        </div>
                                    `;
                                }

                                waitingHtml += '</div>';

                                // Show count of remaining visitors
                                const remainingCount = response.waiting_queue.length - maxDisplay;
                                if (remainingCount > 0) {
                                    waitingHtml += `
                                        <div class="more-count">
                                            <i class="fas fa-ellipsis-h mr-2"></i>
                                            ${remainingCount} more visitor${remainingCount > 1 ? 's' : ''} waiting
                                        </div>
                                    `;
                                }

                                $('#waitingQueue').html(waitingHtml);
                                $('#waitingCount').text(response.waiting_queue.length);

                                // Store counts for wait time calculation
                                priorityWaitingCount = priorityCount;
                                regularWaitingCount = regularCount;
                            } else {
                                $('#waitingQueue').html(`
                                    <div class="text-center py-5">
                                        <i class="fas fa-users fa-3x mb-3" style="opacity: 0.5;"></i>
                                        <p>No visitors waiting</p>
                                    </div>
                                `);
                                $('#waitingCount').text('0');
                                priorityWaitingCount = 0;
                                regularWaitingCount = 0;
                            }

                            // Update served queue (Show only 6 in 3 columns)
                            if (response.served_queue && response.served_queue.length > 0) {
                                let servedHtml = '<div class="queue-grid">';

                                // Show only first 6 served visitors
                                const maxDisplay = 6;
                                const displayQueue = response.served_queue.slice(0, maxDisplay);

                                displayQueue.forEach(function(visitor, index) {
                                    // FIX: Use explicit check for priority
                                    const isPriority = (visitor.is_priority === true || visitor.is_priority === '1' || visitor.queue_type === 'priority');

                                    servedHtml += `
                                        <div class="queue-grid-item served ${isPriority ? 'priority' : 'regular'}">
                                            <div class="queue-grid-number">${visitor.queue_number}</div>
                                            ${isPriority ? 
                                                '<span class="badge badge-danger queue-grid-badge"><i class="fas fa-star mr-1"></i> PRIORITY</span>' : 
                                                '<span class="badge badge-primary queue-grid-badge">REGULAR</span>'}
                                        </div>
                                    `;
                                });

                                // Fill empty slots if less than 6
                                for (let i = displayQueue.length; i < maxDisplay; i++) {
                                    servedHtml += `
                                        <div class="queue-grid-item" style="background: rgba(255,255,255,0.05); border: 2px dashed rgba(255,255,255,0.2);">
                                            <div class="queue-grid-number" style="opacity: 0.5;">---</div>
                                            <span class="badge badge-secondary queue-grid-badge" style="opacity: 0.5;">EMPTY</span>
                                        </div>
                                    `;
                                }

                                servedHtml += '</div>';

                                // Show count of remaining served visitors
                                const remainingCount = response.served_queue.length - maxDisplay;
                                if (remainingCount > 0) {
                                    servedHtml += `
                                        <div class="more-count">
                                            <i class="fas fa-ellipsis-h mr-2"></i>
                                            ${remainingCount} more visitor${remainingCount > 1 ? 's' : ''} served
                                        </div>
                                    `;
                                }

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

                            // Update wait times
                            updateWaitTimes(priorityWaitingCount, regularWaitingCount);

                            // Update section cards
                            if (response.sections && response.sections.length > 0) {
                                response.sections.forEach(function(section) {
                                    const sectionCode = section.section_code || '';

                                    // Update section current serving
                                    const currentElement = $(`#current-${sectionCode}`);
                                    if (section.current_serving && section.current_serving !== '---') {
                                        currentElement.text(section.current_serving);
                                        currentElement.css('color', '#f1c40f');
                                    } else {
                                        currentElement.text('---');
                                        currentElement.css('color', 'rgba(255,255,255,0.7)');
                                    }

                                    // Update waiting count
                                    const waitingElement = $(`#waiting-${sectionCode}`);
                                    if (waitingElement.length) {
                                        waitingElement.text(section.waiting_count || '0');
                                    }

                                    // Update today count
                                    const todayElement = $(`#today-${sectionCode}`);
                                    if (todayElement.length) {
                                        todayElement.text(section.total_today || '0');
                                    }
                                });
                            }

                            // Update unit cards
                            if (response.units && response.units.length > 0) {
                                response.units.forEach(function(unit) {
                                    const unitCode = unit.unit_code || '';

                                    // Update unit current serving
                                    const unitCurrentElement = $(`#unit-current-${unitCode}`);
                                    if (unit.current_serving && unit.current_serving !== '---') {
                                        unitCurrentElement.text(unit.current_serving);
                                        unitCurrentElement.css('color', '#f1c40f');
                                    } else {
                                        unitCurrentElement.text('---');
                                        unitCurrentElement.css('color', 'rgba(255,255,255,0.7)');
                                    }

                                    // Update unit waiting count
                                    const unitWaitingElement = $(`#unit-waiting-${unitCode}`);
                                    if (unitWaitingElement.length) {
                                        unitWaitingElement.text(unit.waiting_count || '0');
                                    }
                                });
                            }

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

// Update active section/unit highlight for BOTH priority and regular
function updateActiveHighlight(prioritySectionCode, priorityUnitCode, regularSectionCode, regularUnitCode) {
    // Remove all active classes first
    $('.section-card').removeClass('active priority regular both');
    $('.unit-card').removeClass('active priority regular both');
    
    // Reset all section backgrounds to default
    $('.section-card').css('background', '#505151ff');
    $('.unit-card').css('background', 'rgba(255, 255, 255, 0.1)');
    $('.unit-card').css('border-left', '4px solid #9b59b6');
    
    // Arrays to track which sections/units are active for each type
    const prioritySections = new Set();
    const priorityUnits = new Set();
    const regularSections = new Set();
    const regularUnits = new Set();
    
    // Highlight PRIORITY section/unit (RED)
    if (priorityUnitCode) {
        // Highlight the priority unit
        const priorityUnitElement = $(`#unit-${priorityUnitCode}`);
        if (priorityUnitElement.length) {
            priorityUnitElement.addClass('active priority');
            priorityUnitElement.css('background', 'rgba(231, 76, 60, 0.6)');
            priorityUnitElement.css('border-left', '4px solid #e74c3c');
            priorityUnits.add(priorityUnitCode);
            
            // Track parent section
            const priorityParentSection = priorityUnitElement.data('parent-section');
            if (priorityParentSection) {
                prioritySections.add(priorityParentSection);
            }
        }
    } 
    
    if (prioritySectionCode) {
        // Highlight the priority section
        const prioritySectionElement = $(`#section-${prioritySectionCode}`);
        if (prioritySectionElement.length) {
            prioritySections.add(prioritySectionCode);
        }
    }
    
    // Highlight REGULAR section/unit (BLUE)
    if (regularUnitCode) {
        // Highlight the regular unit
        const regularUnitElement = $(`#unit-${regularUnitCode}`);
        if (regularUnitElement.length) {
            regularUnitElement.addClass('active regular');
            regularUnitElement.css('background', 'rgba(52, 152, 219, 0.6)');
            regularUnitElement.css('border-left', '4px solid #3498db');
            regularUnits.add(regularUnitCode);
            
            // Track parent section
            const regularParentSection = regularUnitElement.data('parent-section');
            if (regularParentSection) {
                regularSections.add(regularParentSection);
            }
        }
    }
    
    if (regularSectionCode) {
        // Highlight the regular section
        const regularSectionElement = $(`#section-${regularSectionCode}`);
        if (regularSectionElement.length) {
            regularSections.add(regularSectionCode);
        }
    }
    
    // Now apply the visual highlights to sections
    prioritySections.forEach(sectionCode => {
        const sectionElement = $(`#section-${sectionCode}`);
        if (sectionElement.length) {
            if (regularSections.has(sectionCode)) {
                // Section has both priority AND regular - use combined style
                sectionElement.addClass('active both');
                sectionElement.css('background', 'linear-gradient(135deg, #c0392b, #e74c3c, #1e3c72, #2a5298)');
            } else {
                // Section has only priority
                sectionElement.addClass('active priority');
                sectionElement.css('background', 'linear-gradient(135deg, #c0392b, #e74c3c)');
            }
        }
    });
    
    regularSections.forEach(sectionCode => {
        const sectionElement = $(`#section-${sectionCode}`);
        if (sectionElement.length && !prioritySections.has(sectionCode)) {
            // Section has only regular (not already handled above)
            sectionElement.addClass('active regular');
            sectionElement.css('background', 'linear-gradient(135deg, #1e3c72, #2a5298)');
        }
    });
    
    // Handle units that might have both priority and regular (should be rare)
    priorityUnits.forEach(unitCode => {
        if (regularUnits.has(unitCode)) {
            // Same unit has both priority and regular
            const unitElement = $(`#unit-${unitCode}`);
            unitElement.removeClass('priority regular').addClass('both');
            unitElement.css('background', 'linear-gradient(135deg, rgba(231, 76, 60, 0.6), rgba(52, 152, 219, 0.6))');
            unitElement.css('border-left', '4px solid #9b59b6');
        }
    });
}

            // Update wait times separately for priority and regular
            function updateWaitTimes(priorityCount, regularCount) {
                const avgWaitTime = 5; // minutes per visitor

                // Priority wait time (usually ready or minimal wait)
                if (priorityCount === 0) {
                    $('#priorityWaitTime').text('Ready');
                } else {
                    $('#priorityWaitTime').text('Next in line');
                }

                // Regular wait time
                const regularWaitTime = regularCount * avgWaitTime;
                if (regularWaitTime === 0) {
                    $('#regularWaitTime').text('Ready');
                } else if (regularWaitTime < 60) {
                    $('#regularWaitTime').text(regularWaitTime + ' min');
                } else {
                    const hours = Math.floor(regularWaitTime / 60);
                    const minutes = regularWaitTime % 60;
                    $('#regularWaitTime').text(hours + 'h ' + minutes + 'm');
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

            // Show error message
            function showErrorMessage(message) {
                $('#priorityCurrentQueue').text('ERR');
                $('#regularCurrentQueue').text('ERR');
                showToast('Error: ' + message);
            }

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
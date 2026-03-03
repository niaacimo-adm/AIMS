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

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Nunito+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* ── NIA Brand Colors ── */
            --nia-green:        #1a8a3c;
            --nia-green-dark:   #0e5225;
            --nia-green-mid:    #2dc95a;   /* primary card/accent green */
            --nia-green-light:  #4dd87a;
            --nia-green-pale:   #e8f8ee;   /* pale green tint for surfaces */
            --nia-green-tint:   #f0faf4;   /* very light card backgrounds */

            /* Priority = Warm Orange */
            --pri-orange:       #f97316;
            --pri-orange-dark:  #c05a0a;
            --pri-orange-light: #fed7aa;
            --pri-orange-tint:  #fff7f0;
            --pri-card-bg:      #fff5ec;

            /* Regular = Green */
            --reg-card-bg:      #f0faf4;

            /* Page */
            --bg:               #f5f5f5;
            --surface:          #ffffff;
            --card-panel:       #ffffff;
            --card-section:     #ffffff;

            /* Text */
            --text-dark:        #1a2e1a;
            --text-mid:         #2d5a3d;
            --text-muted:       #6b9e7a;
            --text-light:       #a8c8b4;

            /* Borders */
            --divider:          #d4eedd;
            --border-card:      #b8dfc8;

            /* Utility */
            --gold:             #d97706;
            --amber:            #f59e0b;
            --shadow:           rgba(26,138,60,0.10);
            --shadow-pri:       rgba(249,115,22,0.15);
            --shadow-reg:       rgba(26,138,60,0.15);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body.queue-display {
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            padding: 20px 24px;
            font-family: 'Nunito Sans', sans-serif;
            font-size: 18px;
        }

        /* ── HEADER ── */
        .qd-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            border-radius: 20px;
            padding: 18px 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 16px var(--shadow);
            border: 1px solid var(--border-card);
            flex-wrap: wrap;
            gap: 12px;
        }

        .qd-brand-group {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .qd-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid var(--nia-green-mid);
            box-shadow: 0 0 16px rgba(45,201,90,0.3);
            object-fit: cover;
            flex-shrink: 0;
        }

        .qd-brand-text { display: flex; flex-direction: column; }

        .qd-brand-title {
            font-family: 'Nunito', sans-serif;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            color: var(--nia-green-dark);
            line-height: 1;
        }

        .qd-brand-sub {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            letter-spacing: 1px;
        }

        .qd-brand-agency {
            font-size: 12px;
            color: var(--nia-green);
            margin-top: 2px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .qd-clock-group { text-align: right; }

        .qd-clock {
            font-family: 'Nunito', sans-serif;
            font-size: 44px;
            font-weight: 900;
            color: var(--gold);
            line-height: 1;
        }

        .qd-date {
            font-size: 15px;
            color: var(--text-mid);
            margin-top: 4px;
            font-weight: 700;
        }

        /* ── SECTION LABEL ── */
        .qd-section-label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .qd-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--divider);
        }

        /* ── 3-COLUMN LAYOUT ── */
        .three-col-layout {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
            align-items: stretch;
        }

        .col-panel {
            background: var(--surface);
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid var(--divider);
            display: flex;
            flex-direction: column;
            min-height: 460px;
            box-shadow: 0 2px 16px var(--shadow);
        }

        .col-panel-header {
            padding: 14px 20px;
            font-family: 'Nunito', sans-serif;
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 3px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .waiting-panel  { border-color: #fde68a; }
        .serving-panel  { border-color: var(--nia-green-mid); box-shadow: 0 4px 32px var(--shadow-reg); }
        .served-panel   { border-color: #86efac; }

        .waiting-header { background: #fffbeb; color: #92400e; }
        .serving-header { background: var(--nia-green-dark); color: #fff; }
        .served-header  { background: #f0fdf4; color: #15803d; }

        .col-badge {
            background: rgba(0,0,0,.15);
            color: inherit;
            font-size: 13px;
            font-weight: 900;
            border-radius: 20px;
            padding: 2px 12px;
            min-width: 32px;
            text-align: center;
        }
        .waiting-header .col-badge { background: #fde68a; color: #92400e; }
        .served-header  .col-badge { background: #bbf7d0; color: #15803d; }

        .col-panel-body {
            flex: 1;
            padding: 14px;
            overflow-y: auto;
        }

        /* ── SERVING PANEL INTERNALS ── */
        .serving-block {
            border-radius: 16px;
            padding: 16px 14px;
            text-align: center;
            position: relative;
        }
        .priority-block {
            background: var(--pri-card-bg);
            border: 2px solid var(--pri-orange);
            margin-bottom: 0;
        }
        .regular-block  {
            background: var(--reg-card-bg);
            border: 2px solid var(--nia-green-mid);
        }

        .serving-block-label {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .priority-label-tag { color: var(--pri-orange-dark); }
        .regular-label-tag  { color: var(--nia-green-dark); }

        .serving-big-number {
            font-family: 'Nunito', sans-serif;
            font-size: 80px;
            font-weight: 900;
            line-height: 1;
            margin: 6px 0;
        }
        .priority-block .serving-big-number { color: var(--pri-orange); }
        .regular-block  .serving-big-number { color: var(--nia-green-dark); }

        .serving-dest {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-mid);
            margin-bottom: 6px;
            min-height: 20px;
        }

        .serving-info-row {
            font-size: 13px;
            color: var(--text-muted);
            margin: 2px 0;
        }

        .serving-divider {
            height: 1px;
            background: var(--divider);
            margin: 10px 0;
        }

        .wait-times-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .wait-chip {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .priority-chip { background: var(--pri-orange-light); color: var(--pri-orange-dark); }
        .regular-chip  { background: var(--nia-green-pale); color: var(--nia-green-dark); }

        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; animation: pulseDot 1.2s ease-in-out infinite; }
        .dot-priority { background: var(--pri-orange); }
        .dot-regular  { background: var(--nia-green-mid); }
        @keyframes pulseDot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.6} }

        /* ── QUEUE PANEL EMPTY STATE ── */
        .empty-state { text-align: center; padding: 32px 10px; color: var(--text-light); }
        .empty-icon  { font-size: 36px; margin-bottom: 10px; opacity: .5; }
        .empty-text  { font-size: 14px; font-weight: 700; }

        /* Queue grid */
        /* Queue grid */
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .queue-grid-item {
            background: var(--nia-green-tint);
            border-radius: 14px;
            padding: 14px 8px;
            text-align: center;
            border: 2px solid var(--divider);
            min-height: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .queue-grid-item.priority { border-color: var(--pri-orange-light); background: var(--pri-orange-tint); }
        .queue-grid-item.regular  { border-color: var(--nia-green-mid); background: var(--nia-green-pale); }
        .queue-grid-item.served   { border-color: #86efac; background: #f0fdf4; }
        .queue-grid-item.waiting  { border-color: #fde68a; background: #fffbeb; }
        .queue-grid-item.called   { border-color: var(--nia-green-mid); background: var(--nia-green-pale); }

        .queue-grid-number {
            font-family: 'Nunito', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--text-dark);
        }

        .queue-grid-badge {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 5px;
        }

        .queue-grid-badge.p-badge { background: var(--pri-orange-light); color: var(--pri-orange-dark); }
        .queue-grid-badge.r-badge { background: var(--nia-green-pale); color: var(--nia-green-dark); }
        .queue-grid-badge.s-badge { background: #bbf7d0; color: #15803d; }
        .queue-grid-badge.e-badge { background: #f3f4f6; color: #9ca3af; }

        .more-count {
            grid-column: 1 / -1;
            background: var(--nia-green-tint);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-size: 17px;
            font-weight: 800;
            color: var(--text-muted);
            border: 1px dashed var(--border-card);
            margin-top: 2px;
        }

        /* ── MARQUEE ── */
        .qd-marquee {
            background: var(--nia-green-dark);
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .marquee-content {
            display: inline-block;
            white-space: nowrap;
            animation: marquee 45s linear infinite;
            font-size: 19px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1px;
        }

        @keyframes marquee {
            0%   { transform: translateX(100vw); }
            100% { transform: translateX(-100%); }
        }

        /* ── SECTION COUNTERS ── */
        .counter-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .section-card {
            background: var(--surface);
            border: 2px solid var(--nia-green-mid);
            border-radius: 18px;
            padding: 20px;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-shadow: 0 2px 12px var(--shadow);
        }

        .section-card.active.priority { border-color: var(--pri-orange); box-shadow: 0 0 20px var(--shadow-pri); }
        .section-card.active.regular  { border-color: var(--nia-green); box-shadow: 0 0 20px var(--shadow-reg); background: var(--nia-green-tint); }
        .section-card.active.both     { border-color: #a855f7; box-shadow: 0 0 20px rgba(168,85,247,0.15); }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .section-name {
            font-size: 19px;
            font-weight: 900;
            color: var(--text-dark);
        }

        .section-code {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            background: var(--nia-green-pale);
            border: 1px solid var(--nia-green-mid);
            color: var(--nia-green-dark);
            padding: 5px 12px;
            border-radius: 8px;
            text-transform: uppercase;
        }

        .section-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .stat-box {
            background: var(--nia-green-tint);
            border-radius: 12px;
            padding: 12px 6px;
            text-align: center;
            border: 1px solid var(--divider);
        }

        .stat-value {
            font-family: 'Nunito', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: var(--nia-green-dark);
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .unit-list { margin-top: 12px; }

        .unit-header {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .unit-card {
            background: var(--nia-green-tint);
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 8px;
            border-left: 4px solid var(--nia-green-mid);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-left-color 0.3s, background 0.3s;
        }

        .unit-card.active.priority { border-left-color: var(--pri-orange); background: var(--pri-orange-tint); }
        .unit-card.active.regular  { border-left-color: var(--nia-green); background: var(--nia-green-pale); }
        .unit-card.active.both     { border-left-color: #a855f7; background: #faf5ff; }

        .unit-info { display: flex; flex-direction: column; gap: 2px; }
        .unit-name { font-size: 15px; font-weight: 800; color: var(--text-dark); }
        .unit-code-tag { font-size: 11px; color: var(--text-muted); letter-spacing: 1px; }

        .unit-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }

        .unit-serving {
            font-family: 'Nunito', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: var(--nia-green-dark);
        }

        .unit-waiting-tag {
            font-size: 12px;
            font-weight: 700;
            background: var(--nia-green-pale);
            border: 1px solid var(--nia-green-mid);
            border-radius: 5px;
            padding: 2px 8px;
            color: var(--text-mid);
            letter-spacing: 1px;
        }

        .no-units {
            text-align: center;
            padding: 14px;
            font-size: 14px;
            color: var(--text-light);
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .three-col-layout { grid-template-columns: 1fr 1.2fr 1fr; }
            .serving-big-number { font-size: 60px; }
        }
        @media (max-width: 800px) {
            .three-col-layout { grid-template-columns: 1fr; }
            .serving-big-number { font-size: 80px; }
            .qd-clock { font-size: 34px; }
            .qd-logo { width: 54px; height: 54px; }
        }
    </style>


</head>

<body class="queue-display">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="qd-header">
            <div class="qd-brand-group">
                <img src="../dist/img/nialogo.png" alt="NIA Logo" class="qd-logo">
                <div class="qd-brand-text">
                    <div class="qd-brand-title">NIA-ACIMO</div>
                    <div class="qd-brand-sub">Queue Management System</div>
                    <div class="qd-brand-agency">National Irrigation Administration</div>
                    <div class="qd-brand-sub">Albay-Catanduanes Irrigation Management Office</div>
                </div>
            </div>
            <div class="qd-clock-group">
                <div class="qd-clock" id="digitalClock">--:--:--</div>
                <div class="qd-date" id="currentDate">-- --- ----</div>
            </div>
        </div>

        <!-- 3-COLUMN QUEUE LAYOUT -->
        <div class="three-col-layout">

            <!-- ══ COL 1: WAITING ══ -->
            <div class="col-panel waiting-panel">
                <div class="col-panel-header waiting-header">
                    <span><i class="fas fa-hourglass-half"></i>&nbsp; Waiting</span>
                    <span class="col-badge" id="waitingCount">0</span>
                </div>
                <div class="col-panel-body" id="waitingQueue">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-users"></i></div>
                        <div class="empty-text">No visitors waiting</div>
                    </div>
                </div>
            </div>

            <!-- ══ COL 2: NOW SERVING (Priority + Regular) ══ -->
            <div class="col-panel serving-panel">
                <div class="col-panel-header serving-header">
                    <span><i class="fas fa-bullhorn"></i>&nbsp; NOW SERVING</span>
                </div>
                <div class="col-panel-body" style="padding: 12px;">

                    <!-- PRIORITY -->
                    <div class="serving-block priority-block" id="priorityServingCard">
                        <div class="serving-block-label priority-label-tag">
                            <span class="dot dot-priority"></span>
                            <i class="fas fa-star"></i>&nbsp; PRIORITY (PWD / Senior)
                        </div>
                        <div class="serving-big-number" id="priorityCurrentQueue">---</div>
                        <div class="serving-dest" id="priorityDest"></div>
                        <div id="priorityVisitorInfo"></div>
                    </div>

                    <div class="serving-divider"></div>

                    <!-- REGULAR -->
                    <div class="serving-block regular-block" id="regularServingCard">
                        <div class="serving-block-label regular-label-tag">
                            <span class="dot dot-regular"></span>
                            <i class="fas fa-users"></i>&nbsp; REGULAR
                        </div>
                        <div class="serving-big-number" id="regularCurrentQueue">---</div>
                        <div class="serving-dest" id="regularDest"></div>
                        <div id="regularVisitorInfo"></div>
                    </div>

                    <!-- Wait time chips -->
                    <div class="wait-times-row">
                        <span class="wait-chip priority-chip">
                            <i class="fas fa-star"></i> Priority: <strong id="priorityWaitTime">Ready</strong>
                        </span>
                        <span class="wait-chip regular-chip">
                            <i class="fas fa-clock"></i> Est. Wait: <strong id="regularWaitTime">0 min</strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ══ COL 3: SERVED ══ -->
            <div class="col-panel served-panel">
                <div class="col-panel-header served-header">
                    <span><i class="fas fa-check-circle"></i>&nbsp; Served</span>
                    <span class="col-badge" id="servedCount">0</span>
                </div>
                <div class="col-panel-body" id="servedQueue">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="empty-text">No visitors served yet</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MARQUEE -->
        <div class="qd-marquee">
            <div class="marquee-content">
                <i class="fas fa-info-circle"></i>&nbsp;&nbsp;Welcome to NIA-ACIMO. Please have your visitor pass ready. Thank you for your patience.
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <i class="fas fa-shield-alt"></i>&nbsp;&nbsp;Please maintain social distancing and wear your mask properly.
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <i class="fas fa-concierge-bell"></i>&nbsp;&nbsp;For inquiries, please approach the Information Desk.
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            </div>
        </div>

        <!-- SECTION & UNIT COUNTERS -->
        <!-- <div class="qd-section-label">Section &amp; Unit Counters</div>
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
                            <div class="unit-header">Units</div>
                            <?php foreach ($units_by_section[$section['section_id']] as $unit): ?>
                                <div class="unit-card" id="unit-<?= $unit['unit_code'] ?>"
                                    data-unit-id="<?= $unit['unit_id'] ?>"
                                    data-parent-section="<?= $section['section_code'] ?>">
                                    <div class="unit-info">
                                        <div class="unit-name"><?= htmlspecialchars($unit['unit_name']) ?></div>
                                        <div class="unit-code-tag"><?= $unit['unit_code'] ?></div>
                                    </div>
                                    <div class="unit-right">
                                        <div class="unit-serving" id="unit-current-<?= $unit['unit_code'] ?>">---</div>
                                        <div class="unit-waiting-tag">
                                            <span id="unit-waiting-<?= $unit['unit_code'] ?>">0</span> waiting
                                        </div>
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
        </div> -->

    </div><!-- /container-fluid -->

    <?php include '../includes/footer.php'; ?>

    <!-- ══════════════════════════════════════════════════
         ANNOUNCEMENT TOAST OVERLAY (shown during TTS)
    ══════════════════════════════════════════════════ -->
    <div id="announcementOverlay" style="
        display:none;
        position:fixed;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        min-width: 420px;
        max-width: 680px;
        background: #0e5225;
        border-radius: 18px;
        padding: 18px 28px;
        box-shadow: 0 8px 40px rgba(14,82,37,0.35);
        border: 3px solid #2dc95a;
        text-align: center;
        animation: slideUp 0.4s ease;
    ">
        <div style="font-family:'Nunito',sans-serif; font-size:13px; letter-spacing:3px; text-transform:uppercase; color:#86efac; margin-bottom:6px;">
            🔊 Now Announcing
        </div>
        <div id="announcementText" style="font-family:'Nunito',sans-serif; font-size:22px; font-weight:900; color:#ffffff; line-height:1.3;">
        </div>
    </div>

    <!-- Volume / TTS control button -->
    <div style="position:fixed; top:5px; right:20px; z-index:9998;">
        <button id="ttsToggleBtn" onclick="toggleTTS()" style="
            background: #0e5225;
            border: 2px solid #2dc95a;
            border-radius: 50px;
            padding: 10px 20px;
            color: #fff;
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(14,82,37,0.25);
        ">
            <i class="fas fa-volume-up" id="ttsIcon"></i>
            <!-- <span id="ttsLabel">Announcements ON</span> -->
        </button>
    </div>

    <style>
        @keyframes slideUp {
            from { opacity:0; transform: translateX(-50%) translateY(20px); }
            to   { opacity:1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes pulseGlow {
            0%,100% { box-shadow: 0 8px 40px rgba(14,82,37,0.35); }
            50%      { box-shadow: 0 8px 60px rgba(45,201,90,0.6); }
        }
        #announcementOverlay.announcing {
            animation: slideUp 0.4s ease, pulseGlow 1.2s ease-in-out infinite;
        }
    </style>

    <script>
        $(document).ready(function() {
            let lastPriorityQueueNumber = '';
            let lastRegularQueueNumber = '';
            let lastPriorityData = null;
            let lastRegularData = null;
            let refreshInterval = 5000;
            let priorityWaitingCount = 0;
            let regularWaitingCount = 0;
            let ttsEnabled = true;
            let announcementQueue = [];  // Queue of pending announcements
            let isSpeaking = false;
            let overlayTimeout = null;

            // ══════════════════════════════════════════
            //  TEXT-TO-SPEECH ANNOUNCEMENT ENGINE
            // ══════════════════════════════════════════

            // Toggle TTS on/off
            window.toggleTTS = function() {
                ttsEnabled = !ttsEnabled;
                if (!ttsEnabled) {
                    window.speechSynthesis.cancel();
                    hideAnnouncement();
                    announcementQueue = [];
                    isSpeaking = false;
                    $('#ttsIcon').removeClass('fa-volume-up').addClass('fa-volume-mute');
                    $('#ttsLabel').text('Announcements OFF');
                    $('#ttsToggleBtn').css({ background: '#666', borderColor: '#888' });
                } else {
                    $('#ttsIcon').removeClass('fa-volume-mute').addClass('fa-volume-up');
                    $('#ttsLabel').text('Announcements ON');
                    $('#ttsToggleBtn').css({ background: '#0e5225', borderColor: '#2dc95a' });
                }
            };

            // Track recently announced queue numbers to suppress duplicates
            // (queue.php already announces on button click; display.php polls every 5s)
            let recentlyAnnounced = {};   // { queueNumber: timestamp }
            const ANNOUNCE_COOLDOWN_MS = 15000; // won't re-announce within 15 seconds

            // Build announcement — clear, friendly, easy to follow
            function buildAnnouncement(queueData, queueType) {
                const qNum = queueData.queue_number || '';
                let dest = (queueData.destination || queueData.unit_name || queueData.section_name || '').trim();

                // Normalise IMO Office label for TTS
                if (dest.toUpperCase() === 'IMO' || dest === 'IMO Office') {
                    dest = 'I M O Office';
                }

                // Spell each character with spaces so TTS reads them individually
                const spokenNum = qNum.split('').join(' ');
                const destTo = dest ? ' to ' + dest : '';

                // Rotating scripts — simple, clear, warm
                const scripts = [
                    // Script 1
                    'Attention please. Queue number ' + spokenNum +
                        (dest ? ', please proceed to ' + dest : '') +
                        '. I repeat, queue number ' + spokenNum + destTo +
                        '. Thank you, and please take care.',

                    // Script 2
                    'Good day! Queue number ' + spokenNum + ' is now being called.' +
                        (dest ? ' Please go to ' + dest + '.' : '') +
                        ' That is queue number ' + spokenNum + destTo +
                        '. Thank you very much.',

                    // Script 3
                    'Your attention please. We are now calling queue number ' + spokenNum +
                        (dest ? '. Please make your way to ' + dest : '') +
                        '. Queue number ' + spokenNum + destTo +
                        '. We are ready for you. Thank you.',

                    // Script 4
                    'Queue number ' + spokenNum + ', it is your turn.' +
                        (dest ? ' Please proceed to ' + dest + '.' : '') +
                        ' Again, queue number ' + spokenNum + destTo +
                        '. Thank you for waiting, and please come forward.',

                ];

                const idx = Math.floor(Date.now() / 1000) % scripts.length;
                const text = scripts[idx];

                const displayDest = dest === 'I M O Office' ? 'IMO Office' : dest;
                return {
                    text,
                    displayText: 'Queue #' + qNum + (displayDest ? '  →  ' + displayDest : '')
                };
            }

            // Add announcement to queue and process
            // Built-in cooldown prevents re-announcing what queue.php already said on button click
            function queueAnnouncement(queueData, queueType) {
                if (!ttsEnabled || !('speechSynthesis' in window)) return;

                const qNum = queueData.queue_number || '';
                const now  = Date.now();

                // Purge expired cooldowns
                Object.keys(recentlyAnnounced).forEach(k => {
                    if (now - recentlyAnnounced[k] > ANNOUNCE_COOLDOWN_MS) delete recentlyAnnounced[k];
                });

                // Skip if announced within cooldown window (prevents double-announce with queue.php)
                if (recentlyAnnounced[qNum]) return;

                recentlyAnnounced[qNum] = now;
                const announcement = buildAnnouncement(queueData, queueType);
                announcementQueue.push(announcement);
                if (!isSpeaking) processAnnouncementQueue();
            }

            // Process announcements one by one
            function processAnnouncementQueue() {
                if (announcementQueue.length === 0) {
                    isSpeaking = false;
                    hideAnnouncement();
                    return;
                }

                isSpeaking = true;
                const announcement = announcementQueue.shift();

                // Show overlay
                showAnnouncement(announcement.displayText);

                // Play chime first, then speak
                playChime(function() {
                    speak(announcement.text, function() {
                        // Brief pause between announcements
                        setTimeout(processAnnouncementQueue, 800);
                    });
                });
            }

            // Show the announcement overlay
            function showAnnouncement(displayText) {
                clearTimeout(overlayTimeout);
                $('#announcementText').text(displayText);
                $('#announcementOverlay').addClass('announcing').stop(true).fadeIn(300);
            }

            // Hide the announcement overlay
            function hideAnnouncement() {
                clearTimeout(overlayTimeout);
                $('#announcementOverlay').removeClass('announcing').fadeOut(400);
            }

            // Speak — social media girl voice ✨
            // Bright, fast, bubbly — like a TikTok creator doing a voiceover
            function speak(text, onEnd) {
                if (!('speechSynthesis' in window)) { onEnd && onEnd(); return; }
                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate   = 0.85;   // Slower — clear and easy to follow for seniors
                utterance.pitch  = 1.1;    // Slightly warm and friendly, not flat
                utterance.volume = 1.0;

                function doSpeak() {
                    const voices = window.speechSynthesis.getVoices();

                    // Best voices for the "social media girl" sound — ordered by preference.
                    // Microsoft Aria & Jenny are neural voices that genuinely sound like this.
                    // Google US English Female is the best fallback on Chrome.
                    const voicePriority = [
                        'microsoft aria',           // 🥇 Best — natural, young, clear US female
                        'microsoft jenny',          // 🥈 Casual and friendly US female
                        'google us english',        // 🥉 Clear American female (Chrome)
                        'aria',                     // Generic Aria match
                        'jenny',
                        'emma',                     // Apple/Google Emma
                        'ava',                      // Apple Ava — warm young voice
                        'samantha',                 // Apple Samantha — bright
                        'sonia',                    // Microsoft Sonia UK
                        'natasha',                  // Australian female
                        'karen',
                        'google uk english female',
                        'female',
                        'zira',
                    ];

                    let chosenVoice = null;
                    for (const kw of voicePriority) {
                        chosenVoice = voices.find(v =>
                            v.name.toLowerCase().includes(kw) && v.lang.startsWith('en'));
                        if (chosenVoice) break;
                    }
                    // Last resort: any English voice
                    if (!chosenVoice) chosenVoice = voices.find(v => v.lang.startsWith('en'));
                    if (chosenVoice) utterance.voice = chosenVoice;

                    utterance.onend = function() {
                        overlayTimeout = setTimeout(hideAnnouncement, 2000);
                        onEnd && onEnd();
                    };
                    utterance.onerror = function() {
                        hideAnnouncement();
                        onEnd && onEnd();
                    };

                    window.speechSynthesis.speak(utterance);
                }

                // Voices may not be loaded yet on first call
                if (window.speechSynthesis.getVoices().length === 0) {
                    window.speechSynthesis.onvoiceschanged = function() {
                        window.speechSynthesis.onvoiceschanged = null;
                        doSpeak();
                    };
                } else {
                    doSpeak();
                }
            }

            // Play a chime using Web Audio API, then call callback
            function playChime(callback) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();

                    // Two-tone chime: high then low
                    function tone(freq, startTime, duration, gainVal) {
                        const osc  = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, startTime);
                        gain.gain.setValueAtTime(gainVal, startTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                        osc.start(startTime);
                        osc.stop(startTime + duration);
                    }

                    const t = ctx.currentTime;
                    tone(880, t,        0.25, 0.6);   // high A5
                    tone(660, t + 0.28, 0.30, 0.5);   // E5
                    tone(550, t + 0.60, 0.35, 0.4);   // C#5

                    setTimeout(callback, 950); // call back after chime
                } catch(e) {
                    // Web Audio not available — just call back
                    callback();
                }
            }

            // Also play existing bell sound file as fallback/supplement
            function playQueueSound() {
                try {
                    const audio = new Audio('../dist/sounds/queue-bell.mp3');
                    audio.volume = 0.4;
                    audio.play().catch(() => {});
                } catch(e) {}
            }

            // ══════════════════════════════════════════
            //  CLOCK
            // ══════════════════════════════════════════
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
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
                $('#digitalClock').text(time);
                $('#currentDate').text(date);
            }
            updateClock();
            setInterval(updateClock, 1000);

            // Initial load + auto-refresh
            updateQueueDisplay();
            setInterval(updateQueueDisplay, refreshInterval);

            // Track last serving values per section/unit for announcements
            let lastSectionServing = {};
            let lastUnitServing = {};

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
                                // destination = most specific name (unit > section > IMO)
                                const priDest = current.destination || current.unit_name || current.section_name || '';
                                $('#priorityCurrentQueue').text(current.is_priority == 1 ? (current.priority_number || current.queue_number) : current.queue_number);
                                $('#priorityDest').text(priDest);
                                $('#priorityVisitorInfo').html(
                                    `<div class="serving-info-row"><i class="fas fa-user"></i> ${current.visitor_name || ''}</div>` +
                                    (current.purpose ? `<div class="serving-info-row"><i class="fas fa-file-alt"></i> ${current.purpose}</div>` : '')
                                );
                                currentPrioritySectionCode = current.section_code;
                                currentPriorityUnitCode = current.unit_code;

                                if (lastPriorityQueueNumber !== current.queue_number) {
                                    lastPriorityQueueNumber = current.queue_number;
                                    playQueueSound();
                                    queueAnnouncement(current, 'priority');
                                    $('#priorityCurrentQueue').animate({fontSize:'90px'}, 150, function(){
                                        $(this).animate({fontSize:'80px'}, 200);
                                    });
                                }
                            } else {
                                $('#priorityCurrentQueue').text('---');
                                $('#priorityDest').text('');
                                $('#priorityVisitorInfo').html(
                                    '<div class="empty-state"><div class="empty-icon"><i class="fas fa-user-clock"></i></div>' +
                                    '<div class="empty-text">No priority visitor</div></div>'
                                );
                            }

                            // Update regular serving
                            if (response.current_regular && response.current_regular.queue_number) {
                                const current = response.current_regular;
                                const regDest = current.destination || current.unit_name || current.section_name || '';
                                $('#regularCurrentQueue').text(current.queue_number);
                                $('#regularDest').text(regDest);
                                $('#regularVisitorInfo').html(
                                    `<div class="serving-info-row"><i class="fas fa-user"></i> ${current.visitor_name || ''}</div>` +
                                    (current.purpose ? `<div class="serving-info-row"><i class="fas fa-file-alt"></i> ${current.purpose}</div>` : '')
                                );
                                currentRegularSectionCode = current.section_code;
                                currentRegularUnitCode = current.unit_code;

                                if (lastRegularQueueNumber !== current.queue_number) {
                                    lastRegularQueueNumber = current.queue_number;
                                    playQueueSound();
                                    queueAnnouncement(current, 'regular');
                                    $('#regularCurrentQueue').animate({fontSize:'90px'}, 150, function(){
                                        $(this).animate({fontSize:'80px'}, 200);
                                    });
                                }
                            } else {
                                $('#regularCurrentQueue').text('---');
                                $('#regularDest').text('');
                                $('#regularVisitorInfo').html(
                                    '<div class="empty-state"><div class="empty-icon"><i class="fas fa-users"></i></div>' +
                                    '<div class="empty-text">No regular visitor</div></div>'
                                );
                            }

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
                                    const isPriority = (visitor.is_priority === true || visitor.is_priority === '1');
                                    const dest = visitor.destination || visitor.unit_name || visitor.section_name || '';
                                    const qNum = isPriority ? (visitor.priority_number || visitor.queue_number) : visitor.queue_number;

                                    if (isPriority) priorityCount++;
                                    else regularCount++;

                                    waitingHtml += '<div class="queue-grid-item ' + statusClass + ' ' + (isPriority ? 'priority' : 'regular') + '">' +
                                        '<div class="queue-grid-number">' + qNum + '</div>' +
                                        (isPriority ? '<span class="queue-grid-badge p-badge"><i class="fas fa-star"></i> PRI</span>' : '<span class="queue-grid-badge r-badge">REG</span>') +
                                        (dest ? '<div style="font-size:10px;color:#6b9e7a;margin-top:3px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">' + dest + '</div>' : '') +
                                    '</div>';
                                });

                                // Fill empty slots if less than 6
                                for (let i = displayQueue.length; i < maxDisplay; i++) {
                                    waitingHtml += `
                                        <div class="queue-grid-item" style="border-style:dashed;">
                                            <div class="queue-grid-number" style="opacity: 0.3;">---</div>
                                            <span class="queue-grid-badge e-badge">EMPTY</span>
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
                                    const isPriority = (visitor.is_priority === true || visitor.is_priority === '1');
                                    const dest = visitor.destination || visitor.unit_name || visitor.section_name || '';
                                    const qNum = isPriority ? (visitor.priority_number || visitor.queue_number) : visitor.queue_number;

                                    servedHtml += '<div class="queue-grid-item served ' + (isPriority ? 'priority' : 'regular') + '">' +
                                        '<div class="queue-grid-number">' + qNum + '</div>' +
                                        (isPriority ? '<span class="queue-grid-badge p-badge"><i class="fas fa-star"></i> PRI</span>' : '<span class="queue-grid-badge s-badge">SERVED</span>') +
                                        (dest ? '<div style="font-size:10px;color:#15803d;margin-top:3px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">' + dest + '</div>' : '') +
                                    '</div>';
                                });

                                // Fill empty slots if less than 6
                                for (let i = displayQueue.length; i < maxDisplay; i++) {
                                    servedHtml += `
                                        <div class="queue-grid-item" style="border-style:dashed;">
                                            <div class="queue-grid-number" style="opacity: 0.3;">---</div>
                                            <span class="queue-grid-badge e-badge">EMPTY</span>
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
                                        currentElement.css('color', '#0e5225');

                                        // ── ANNOUNCE when section serving number changes ──
                                        if (lastSectionServing[sectionCode] !== section.current_serving) {
                                            lastSectionServing[sectionCode] = section.current_serving;

                                            // Only announce at section level if NOT already announced
                                            // via the main priority/regular card (avoid duplicates)
                                            // We announce section-level if it has its own unique serving
                                            // that differs from the main cards
                                            const alreadyAnnounced =
                                                (section.current_serving === lastPriorityQueueNumber) ||
                                                (section.current_serving === lastRegularQueueNumber);

                                            if (!alreadyAnnounced) {
                                                // Build a section-specific announcement
                                                const sectionAnnouncement = {
                                                    queue_number:  section.current_serving,
                                                    visitor_name:  section.visitor_name || 'Visitor',
                                                    section_name:  section.section_name || sectionCode,
                                                    unit_name:     '',
                                                    purpose:       section.purpose || ''
                                                };
                                                queueAnnouncement(sectionAnnouncement,
                                                    section.is_priority ? 'priority' : 'regular');
                                            }
                                        }

                                        // Visual pulse on section card
                                        $(`#section-${sectionCode}`).addClass('serving-pulse');
                                        setTimeout(() => $(`#section-${sectionCode}`).removeClass('serving-pulse'), 2000);

                                    } else {
                                        currentElement.text('---');
                                        currentElement.css('color', '#a8c8b4');
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
                                        unitCurrentElement.css('color', '#0e5225');

                                        // ── ANNOUNCE when unit serving number changes ──
                                        if (lastUnitServing[unitCode] !== unit.current_serving) {
                                            lastUnitServing[unitCode] = unit.current_serving;

                                            const alreadyAnnounced =
                                                (unit.current_serving === lastPriorityQueueNumber) ||
                                                (unit.current_serving === lastRegularQueueNumber);

                                            if (!alreadyAnnounced) {
                                                const unitAnnouncement = {
                                                    queue_number: unit.current_serving,
                                                    visitor_name: unit.visitor_name || 'Visitor',
                                                    section_name: unit.unit_name || unitCode,
                                                    unit_name:    unit.unit_name || '',
                                                    purpose:      unit.purpose || ''
                                                };
                                                queueAnnouncement(unitAnnouncement,
                                                    unit.is_priority ? 'priority' : 'regular');
                                            }
                                        }

                                        // Visual pulse on unit card
                                        $(`#unit-${unitCode}`).addClass('serving-pulse');
                                        setTimeout(() => $(`#unit-${unitCode}`).removeClass('serving-pulse'), 2000);

                                    } else {
                                        unitCurrentElement.text('---');
                                        unitCurrentElement.css('color', '#a8c8b4');
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
    $('.section-card').css('background', '#ffffff');
    $('.unit-card').css('background', '#f0faf4');
    $('.unit-card').css('border-left', '4px solid #2dc95a');
    
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
            priorityUnitElement.css('background', '#fff7f0');
            priorityUnitElement.css('border-left', '4px solid #f97316');
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
            regularUnitElement.css('background', '#e8f8ee');
            regularUnitElement.css('border-left', '4px solid #1a8a3c');
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
                sectionElement.css('background', '#fff7f0');
            }
        }
    });
    
    regularSections.forEach(sectionCode => {
        const sectionElement = $(`#section-${sectionCode}`);
        if (sectionElement.length && !prioritySections.has(sectionCode)) {
            // Section has only regular (not already handled above)
            sectionElement.addClass('active regular');
            sectionElement.css('background', '#f0faf4');
        }
    });
    
    // Handle units that might have both priority and regular (should be rare)
    priorityUnits.forEach(unitCode => {
        if (regularUnits.has(unitCode)) {
            // Same unit has both priority and regular
            const unitElement = $(`#unit-${unitCode}`);
            unitElement.removeClass('priority regular').addClass('both');
            unitElement.css('background', '#faf5ff');
            unitElement.css('border-left', '4px solid #a855f7');
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
            // showToast('Queue display loaded. Announcements active. Auto-refreshing every 5 seconds...');

            // Preload voices (Chrome needs this on load)
            if ('speechSynthesis' in window) {
                window.speechSynthesis.getVoices();
                window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
            }
        });
    </script>

    <style>
        /* Pulse animation on section/unit card when a new number is called */
        @keyframes servingPulse {
            0%   { box-shadow: 0 0 0 0 rgba(45,201,90,0.5); }
            50%  { box-shadow: 0 0 0 12px rgba(45,201,90,0); }
            100% { box-shadow: 0 0 0 0 rgba(45,201,90,0); }
        }
        .serving-pulse {
            animation: servingPulse 0.8s ease-out 2;
        }
        /* Highlight the "Now Serving" stat value when active */
        .stat-box .stat-value.active-serving {
            color: #0e5225 !important;
            font-size: 34px;
            transition: font-size 0.3s ease;
        }
    </style>
</body>

</html>
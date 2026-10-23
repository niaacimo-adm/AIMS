<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

require_once '../includes/module_guard.php';
checkModuleMaintenance($db);

$stmt = $db->prepare("SELECT u.id, u.user, r.name as role_name, r.id as role_id FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { session_destroy(); header("Location: login.php"); exit(); }
$user = $result->fetch_assoc();

/* ── Helper: run a query and return all rows ─────────────────── */
function rows($db, $sql) {
    $s = $db->prepare($sql);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ── KPIs ────────────────────────────────────────────────────── */
$kpi = rows($db, "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN employment_status_id = 1 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN employment_status_id = 2 THEN 1 ELSE 0 END) AS on_leave,
        SUM(CASE WHEN employment_status_id NOT IN (1,2) OR employment_status_id IS NULL THEN 1 ELSE 0 END) AS other,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female
    FROM employee")[0];
$total    = (int)$kpi['total'];
$active   = (int)$kpi['active'];
$on_leave = (int)$kpi['on_leave'];
$other    = (int)$kpi['other'];
$male     = (int)$kpi['male'];
$female   = (int)$kpi['female'];
$pct = fn($n, $d) => $d > 0 ? round(($n / $d) * 100) : 0;

/* ── Breakdown data ──────────────────────────────────────────── */
$sections = rows($db, "SELECT s.section_id, s.section_name,
        COUNT(e.emp_id) AS total,
        SUM(CASE WHEN e.gender='Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN e.gender='Female' THEN 1 ELSE 0 END) AS female
    FROM section s LEFT JOIN employee e ON e.section_id = s.section_id
    GROUP BY s.section_id, s.section_name ORDER BY total DESC, s.section_name");

$appointments = rows($db, "SELECT a.appointment_id, a.status_name, a.color, COUNT(e.emp_id) AS count
    FROM appointment_status a LEFT JOIN employee e ON e.appointment_status_id = a.appointment_id
    GROUP BY a.appointment_id, a.status_name, a.color ORDER BY count DESC");

$offices = rows($db, "SELECT o.office_id, o.office_name, COUNT(e.emp_id) AS count
    FROM office o LEFT JOIN employee e ON e.office_id = o.office_id
    GROUP BY o.office_id, o.office_name ORDER BY count DESC");

$employment_statuses = rows($db, "SELECT status_id, status_name FROM employment_status ORDER BY status_id");

$employees = rows($db, "SELECT e.emp_id, e.first_name, e.last_name, e.id_number, e.picture,
        e.employment_status_id, e.appointment_status_id,
        p.position_name, s.section_name, es.status_name,
        a.status_name AS appointment_name, a.color AS appointment_color
    FROM employee e
    LEFT JOIN position p ON e.position_id = p.position_id
    LEFT JOIN section s ON e.section_id = s.section_id
    LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
    LEFT JOIN appointment_status a ON e.appointment_status_id = a.appointment_id
    ORDER BY e.last_name, e.first_name LIMIT 300");

/* Compact payload for the client-side directory */
$dir = array_map(function ($e) {
    $col = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$e['appointment_color']) ? $e['appointment_color'] : '#2a9863';
    return [
        'n'  => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')),
        'i'  => mb_strtoupper(mb_substr($e['first_name'] ?? '', 0, 1) . mb_substr($e['last_name'] ?? '', 0, 1)),
        'id' => (string)($e['id_number'] ?? ''),
        'pic'=> (string)($e['picture'] ?? ''),
        'st' => (int)$e['employment_status_id'],
        'stn'=> (string)($e['status_name'] ?? ''),
        'pos'=> (string)($e['position_name'] ?? ''),
        'sec'=> (string)($e['section_name'] ?? ''),
        'ap' => (string)($e['appointment_name'] ?? ''),
        'apc'=> $col,
    ];
}, $employees);

$uploads_url = '../dist/img/employees/';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$jf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HR Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/module-theme.css">
    <style>
        /* Scoped under .hr so nothing leaks into the rest of AIMS. Uses app CSS variables when present. */
        .hr{
            --b:#2a9863; --bd:#1f7a4e; --bs:#e7f5ee;
            --sf:var(--surface,#fff); --bg:var(--bg,#f4f7f5); --bo:var(--border,#e3ebe6);
            --tx:var(--text,#12261b); --t2:var(--text2,#46604f); --mu:#7b8f83;
            --blue:#2563eb; --pink:#db2777; --amber:#d97706; --slate:#64748b;
            --rad:16px; --sh:0 1px 2px rgba(18,38,27,.04); --shm:0 12px 28px rgba(18,38,27,.10);
            font-family:Inter,'Segoe UI',system-ui,sans-serif; color:var(--tx); padding:20px 0 48px;
        }
        body.dark-mode .hr{--bs:rgba(42,152,99,.18);--mu:#93a79b;--sh:none}
        .hr *{box-sizing:border-box}
        .hr button{font-family:inherit}

        /* ── Hero ── */
        .hr-hero{position:relative;overflow:hidden;border-radius:22px;padding:28px 30px;color:#fff;margin-bottom:20px;
            background:linear-gradient(130deg,#12402b 0%,#1f7a4e 50%,#35b27c 100%);
            display:flex;flex-wrap:wrap;gap:18px;align-items:flex-end;justify-content:space-between}
        .hr-hero::before,.hr-hero::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.08)}
        .hr-hero::before{width:280px;height:280px;right:-70px;top:-110px}
        .hr-hero::after{width:200px;height:200px;right:170px;bottom:-120px}
        .hr-hero>*{position:relative;z-index:1}
        .hr-eyebrow{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
        .hr-pill{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:99px;font-size:.76rem;font-weight:600;
            background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.24);backdrop-filter:blur(4px)}
        .hr-hero h1{margin:0 0 4px;font-size:1.65rem;font-weight:700;letter-spacing:-.02em}
        .hr-hero p{margin:0;opacity:.88;font-size:.93rem}
        .hr-actions{display:flex;gap:8px;flex-wrap:wrap}
        .hr-btn{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 16px;border-radius:11px;border:1px solid rgba(255,255,255,.3);
            background:rgba(255,255,255,.14);color:#fff;font-size:.84rem;font-weight:600;cursor:pointer;transition:background .15s,transform .15s}
        .hr-btn:hover{background:rgba(255,255,255,.26);transform:translateY(-1px)}
        .hr-btn.solid{background:#fff;color:var(--bd);border-color:#fff}
        .hr-btn.solid:hover{background:#f0fff7}
        .hr-hero{align-items:center;width:fit-content;max-width:100%;margin-left:auto;padding:18px 26px;border-radius:18px}
        .hr-clock{font-size:2.6rem;font-weight:700;letter-spacing:-.02em;line-height:1;font-variant-numeric:tabular-nums}
        .hr-date{margin-top:8px;font-size:1rem;opacity:.9;display:flex;align-items:center;gap:8px}
        @media (max-width:600px){.hr-clock{font-size:2rem}}

        /* ── KPI cards ── */
        .hr-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
        .hr-kpi{background:var(--sf);border:1px solid var(--bo);border-radius:var(--rad);padding:18px 20px;box-shadow:var(--sh);transition:transform .18s,box-shadow .18s}
        .hr-kpi:hover{transform:translateY(-2px);box-shadow:var(--shm)}
        .hr-kpi-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .hr-ico{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1rem;flex:none}
        .ic-g{background:var(--bs);color:var(--b)} .ic-b{background:#e8f0ff;color:var(--blue)} .ic-a{background:#fef3e0;color:var(--amber)} .ic-s{background:#eef1f5;color:var(--slate)}
        body.dark-mode .ic-b{background:rgba(37,99,235,.2)} body.dark-mode .ic-a{background:rgba(217,119,6,.2)} body.dark-mode .ic-s{background:rgba(100,116,139,.22)}
        .hr-trend{font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px;background:var(--bs);color:var(--bd)}
        body.dark-mode .hr-trend{color:#6fe0a7}
        .hr-kl{font-size:.74rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--mu)}
        .hr-kv{font-size:2rem;font-weight:700;letter-spacing:-.02em;line-height:1.1;margin:3px 0 10px;font-variant-numeric:tabular-nums}
        .hr-meter{height:6px;border-radius:99px;background:var(--bo);overflow:hidden}
        .hr-meter i{display:block;height:100%;border-radius:99px;width:0;transition:width .9s cubic-bezier(.2,.8,.2,1)}

        /* ── Cards ── */
        .hr-grid{display:grid;gap:16px;margin-bottom:20px}
        .g32{grid-template-columns:3fr 2fr} .g2{grid-template-columns:1fr 1fr}
        .hr-card{background:var(--sf);border:1px solid var(--bo);border-radius:var(--rad);box-shadow:var(--sh);overflow:hidden;min-width:0}
        .hr-ch{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:15px 20px;border-bottom:1px solid var(--bo)}
        .hr-ch h5{margin:0;display:flex;align-items:center;gap:10px;font-size:.98rem;font-weight:700}
        .hr-ch .hr-ico{width:32px;height:32px;border-radius:9px;font-size:.85rem}
        .hr-badge{padding:4px 11px;border-radius:99px;background:var(--bs);color:var(--bd);font-size:.74rem;font-weight:700;white-space:nowrap}
        body.dark-mode .hr-badge{color:#6fe0a7}
        .hr-chart{position:relative;height:290px;padding:16px 20px 20px}

        /* Section bars (click to filter directory) */
        .hr-list{padding:4px 20px 14px;max-height:372px;overflow-y:auto}
        .hr-row{display:block;width:100%;text-align:left;background:none;border:0;border-bottom:1px dashed var(--bo);padding:11px 8px;margin:0;cursor:pointer;color:inherit;border-radius:8px;transition:background .12s}
        .hr-row:last-child{border-bottom:0} .hr-row:hover{background:var(--bs)}
        .hr-row.on{background:var(--bs);box-shadow:inset 3px 0 0 var(--b)}
        .hr-rt{display:flex;justify-content:space-between;gap:10px;font-size:.86rem;font-weight:600;margin-bottom:7px}
        .hr-rt span:last-child{color:var(--t2);font-weight:700;white-space:nowrap}
        .hr-bar{display:flex;height:8px;border-radius:99px;overflow:hidden;background:var(--bo)}
        .hr-bar i{display:block;height:100%} .bm{background:var(--blue)} .bf{background:var(--pink)} .bg{background:var(--b)}
        .hr-legend{display:flex;gap:14px;padding:10px 20px 14px;border-top:1px solid var(--bo);font-size:.75rem;color:var(--t2)}
        .hr-legend b{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}

        /* ── Directory ── */
        .hr-tools{display:flex;flex-wrap:wrap;gap:10px;padding:14px 20px;border-bottom:1px solid var(--bo);align-items:center}
        .hr-search{position:relative;flex:1 1 240px}
        .hr-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--mu);font-size:.82rem}
        .hr-search input,.hr-sel{height:40px;border:1px solid var(--bo);border-radius:11px;background:var(--bg);color:var(--tx);font-size:.86rem;outline:none;transition:border .15s,box-shadow .15s}
        .hr-search input{width:100%;padding:0 12px 0 36px} .hr-sel{padding:0 10px;cursor:pointer;max-width:200px}
        .hr-search input:focus,.hr-sel:focus{border-color:var(--b);box-shadow:0 0 0 3px rgba(42,152,99,.16)}
        body.dark-mode .hr-search input,body.dark-mode .hr-sel{background:var(--sf)!important;color:var(--tx)!important}
        .hr-seg{display:inline-flex;padding:3px;border-radius:11px;background:var(--bg);border:1px solid var(--bo)}
        .hr-seg button{border:0;background:none;height:32px;padding:0 12px;border-radius:8px;font-size:.8rem;font-weight:600;color:var(--t2);cursor:pointer;display:inline-flex;align-items:center;gap:6px}
        .hr-seg button.on{background:var(--sf);color:var(--bd);box-shadow:0 1px 3px rgba(18,38,27,.12)}
        body.dark-mode .hr-seg button.on{color:#6fe0a7}
        .hr-seg small{font-size:.7rem;padding:1px 7px;border-radius:99px;background:var(--bs);color:var(--bd);font-weight:700}
        .hr-filterbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:0 20px;overflow:hidden;max-height:0;transition:max-height .2s,padding .2s}
        .hr-filterbar.show{max-height:60px;padding:10px 20px 0}
        .hr-tag{display:inline-flex;align-items:center;gap:7px;padding:4px 6px 4px 11px;border-radius:99px;background:var(--bs);color:var(--bd);font-size:.76rem;font-weight:600}
        .hr-tag button{border:0;background:rgba(0,0,0,.08);width:18px;height:18px;border-radius:50%;line-height:1;cursor:pointer;color:inherit;font-size:.7rem}

        .hr-tw{overflow:auto;max-height:560px}
        .hr-table{width:100%;border-collapse:collapse;font-size:.86rem}
        .hr-table th{position:sticky;top:0;z-index:1;background:var(--sf);text-align:left;padding:11px 20px;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--mu);border-bottom:1px solid var(--bo)}
        .hr-table td{padding:11px 20px;border-bottom:1px solid var(--bo);vertical-align:middle}
        .hr-table tbody tr{transition:background .12s} .hr-table tbody tr:hover{background:var(--bs)}
        .hr-person{display:flex;align-items:center;gap:12px;min-width:220px}
        .hr-av{position:relative;width:38px;height:38px;border-radius:50%;flex:none;overflow:hidden;display:grid;place-items:center;font-size:.8rem;font-weight:700;background:var(--bs);color:var(--bd)}
        .hr-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
        .hr-pn{font-weight:600} .hr-pi{font-size:.74rem;color:var(--mu)}
        .hr-chip{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:99px;font-size:.73rem;font-weight:600;white-space:nowrap;background:#eef1f5;color:var(--slate)}
        .hr-chip::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
        .hr-chip.act{background:var(--bs);color:var(--bd)} .hr-chip.lv{background:#fef3e0;color:#b45309} .hr-chip.pl::before{display:none}
        body.dark-mode .hr-chip.act{color:#6fe0a7} body.dark-mode .hr-chip.lv{background:rgba(217,119,6,.2);color:#fbbf24} body.dark-mode .hr-chip{background:rgba(255,255,255,.07)}

        .hr-cards{display:none;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;padding:16px 20px;max-height:600px;overflow:auto}
        .hr-ec{border:1px solid var(--bo);border-radius:14px;padding:16px;text-align:center;background:var(--sf);transition:transform .16s,box-shadow .16s,border-color .16s}
        .hr-ec:hover{transform:translateY(-2px);box-shadow:var(--shm);border-color:var(--b)}
        .hr-ec .hr-av{width:60px;height:60px;font-size:1.05rem;margin:0 auto 10px}
        .hr-ec .hr-pn{font-size:.92rem} .hr-ec .hr-pos{font-size:.78rem;color:var(--t2);margin:2px 0 10px;min-height:2.2em}
        .hr-ec .tags{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-top:8px}

        .hr-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:12px 20px;border-top:1px solid var(--bo);font-size:.8rem;color:var(--t2)}
        .hr-pager{display:flex;align-items:center;gap:6px}
        .hr-pager button{height:32px;min-width:32px;padding:0 10px;border-radius:9px;border:1px solid var(--bo);background:var(--sf);color:var(--tx);font-weight:600;font-size:.8rem;cursor:pointer}
        .hr-pager button:hover:not(:disabled){border-color:var(--b);color:var(--bd)} .hr-pager button:disabled{opacity:.4;cursor:default}
        .hr-empty{display:none;text-align:center;padding:48px 20px;color:var(--mu)} .hr-empty i{display:block;font-size:2.2rem;margin-bottom:10px;opacity:.5}
        .hr-note{font-size:.74rem;color:var(--mu)}

        @media (max-width:1100px){.hr-kpis{grid-template-columns:repeat(2,1fr)} .g32,.g2{grid-template-columns:1fr}}
        @media (max-width:600px){.hr-kpis{grid-template-columns:1fr} .hr-hero{padding:22px} .hr-sel{max-width:none;flex:1} .hr-table th:nth-child(2),.hr-table td:nth-child(2){display:none}}
        @media (prefers-reduced-motion:reduce){.hr *{transition:none!important}}
        @media print{.hr-actions,.hr-tools,.hr-foot,.hr-seg{display:none!important} .hr-tw,.hr-cards{max-height:none}}

        /* Page hero (pg-hero styles come from module-theme.css) — content overlaps the hero like Attachments Monitoring */
        .hr-pull{margin-top:-38px;position:relative;z-index:3;padding:0 20px}
        .hr-pull .hr{padding-top:0}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-hex"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-tachometer-alt"></i>HR Dashboard</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub"><?= $h($greeting) ?> workforce overview, appointment status and employee directory</p>
                </div>
            </div>
        </div>

        <div class="content hr-pull">
            <div class="container-fluid hr">
                <!-- KPIs -->
                <div class="hr-kpis">
                    <div class="hr-kpi">
                        <div class="hr-kpi-top"><div class="hr-ico ic-g"><i class="fas fa-users"></i></div><span class="hr-trend"><?= count($offices) ?> offices</span></div>
                        <div class="hr-kl">Total employees</div>
                        <div class="hr-kv" data-count="<?= $total ?>">0</div>
                        <div class="hr-meter"><i style="background:var(--b)" data-w="100"></i></div>
                    </div>
                    <div class="hr-kpi">
                        <div class="hr-kpi-top"><div class="hr-ico ic-b"><i class="fas fa-user-check"></i></div><span class="hr-trend"><?= $pct($active, $total) ?>%</span></div>
                        <div class="hr-kl">Active</div>
                        <div class="hr-kv" data-count="<?= $active ?>">0</div>
                        <div class="hr-meter"><i style="background:var(--blue)" data-w="<?= $pct($active, $total) ?>"></i></div>
                    </div>
                    <div class="hr-kpi">
                        <div class="hr-kpi-top"><div class="hr-ico ic-a"><i class="fas fa-umbrella-beach"></i></div><span class="hr-trend"><?= $pct($on_leave, $total) ?>%</span></div>
                        <div class="hr-kl">On leave</div>
                        <div class="hr-kv" data-count="<?= $on_leave ?>">0</div>
                        <div class="hr-meter"><i style="background:var(--amber)" data-w="<?= $pct($on_leave, $total) ?>"></i></div>
                    </div>
                    <div class="hr-kpi">
                        <div class="hr-kpi-top"><div class="hr-ico ic-s"><i class="fas fa-user-clock"></i></div><span class="hr-trend"><?= $pct($other, $total) ?>%</span></div>
                        <div class="hr-kl">Other status</div>
                        <div class="hr-kv" data-count="<?= $other ?>">0</div>
                        <div class="hr-meter"><i style="background:var(--slate)" data-w="<?= $pct($other, $total) ?>"></i></div>
                    </div>
                </div>

                <!-- SECTIONS + GENDER -->
                <div class="hr-grid g32">
                    <div class="hr-card">
                        <div class="hr-ch"><h5><div class="hr-ico ic-g"><i class="fas fa-sitemap"></i></div>Headcount by section</h5>
                            <span class="hr-badge">Click a section to filter</span></div>
                        <div class="hr-list">
                            <?php if (!$sections): ?>
                                <div class="hr-empty" style="display:block"><i class="fas fa-sitemap"></i>No sections yet.</div>
                            <?php else:
                                $max = max(1, (int)$sections[0]['total']);
                                foreach ($sections as $s):
                                    $t = (int)$s['total']; $m = (int)$s['male']; $f = (int)$s['female']; $w = round($t / $max * 100); ?>
                            <button type="button" class="hr-row" data-section="<?= $h($s['section_name']) ?>">
                                <div class="hr-rt"><span><?= $h($s['section_name']) ?></span><span><?= $t ?> staff</span></div>
                                <div class="hr-bar" style="width:<?= $w ?>%;min-width:<?= $t ? '6%' : '0' ?>" title="<?= $m ?> male, <?= $f ?> female">
                                    <i class="bm" style="width:<?= $t ? $m / $t * 100 : 0 ?>%"></i><i class="bf" style="width:<?= $t ? $f / $t * 100 : 0 ?>%"></i><i class="bg" style="width:<?= $t ? max(0, 100 - ($m + $f) / $t * 100) : 0 ?>%"></i>
                                </div>
                            </button>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="hr-legend"><span><b style="background:var(--blue)"></b>Male</span><span><b style="background:var(--pink)"></b>Female</span><span><b style="background:var(--b)"></b>Unspecified</span></div>
                    </div>

                    <div class="hr-card">
                        <div class="hr-ch"><h5><div class="hr-ico ic-b"><i class="fas fa-venus-mars"></i></div>Gender balance</h5>
                            <span class="hr-badge"><?= $pct($female, $total) ?>% female</span></div>
                        <div class="hr-chart"><canvas id="genderChart"></canvas></div>
                    </div>
                </div>

                <!-- APPOINTMENT + OFFICE -->
                <div class="hr-grid g2">
                    <div class="hr-card">
                        <div class="hr-ch"><h5><div class="hr-ico ic-a"><i class="fas fa-chart-pie"></i></div>Appointment status</h5></div>
                        <div class="hr-chart"><canvas id="appointmentChart"></canvas></div>
                    </div>
                    <div class="hr-card">
                        <div class="hr-ch"><h5><div class="hr-ico ic-g"><i class="fas fa-building"></i></div>Employees by office</h5></div>
                        <div class="hr-chart"><canvas id="officeChart"></canvas></div>
                    </div>
                </div>

                <!-- DIRECTORY -->
                <div class="hr-card" id="directory">
                    <div class="hr-ch"><h5><div class="hr-ico ic-g"><i class="fas fa-address-book"></i></div>Employee directory</h5>
                        <div class="hr-seg" role="group" aria-label="View">
                            <button type="button" id="vTable" class="on"><i class="fas fa-list"></i>Table</button>
                            <button type="button" id="vCards"><i class="fas fa-th-large"></i>Cards</button>
                        </div></div>

                    <div class="hr-tools">
                        <div class="hr-search"><i class="fas fa-search"></i>
                            <input type="text" id="q" placeholder="Search name, ID, position, section…" aria-label="Search employees"></div>
                        <div class="hr-seg" id="statusSeg" role="group" aria-label="Employment status">
                            <button type="button" class="on" data-st="">All <small id="cAll">0</small></button>
                            <button type="button" data-st="1">Active <small id="cAct">0</small></button>
                            <button type="button" data-st="2">On leave <small id="cLv">0</small></button>
                            <button type="button" data-st="x">Other <small id="cOt">0</small></button>
                        </div>
                        <select class="hr-sel" id="fAppt" aria-label="Appointment status"><option value="">All appointments</option></select>
                        <select class="hr-sel" id="fSort" aria-label="Sort">
                            <option value="name">Sort: Name A–Z</option>
                            <option value="name-d">Sort: Name Z–A</option>
                            <option value="sec">Sort: Section</option>
                            <option value="pos">Sort: Position</option>
                        </select>
                    </div>
                    <div class="hr-filterbar" id="tagBar"></div>

                    <div class="hr-tw" id="tableWrap">
                        <table class="hr-table">
                            <thead><tr><th>Employee</th><th>Position</th><th>Section</th><th>Status</th><th>Appointment</th></tr></thead>
                            <tbody id="tbody"></tbody>
                        </table>
                    </div>
                    <div class="hr-cards" id="cardWrap"></div>
                    <div class="hr-empty" id="noRows"><i class="fas fa-users"></i>No employees match your filters</div>

                    <div class="hr-foot">
                        <span id="range">—</span>
                        <div class="hr-pager"><button type="button" id="prev" aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>
                            <span id="pageInfo">1 / 1</span>
                            <button type="button" id="next" aria-label="Next page"><i class="fas fa-chevron-right"></i></button></div>
                    </div>
                    <?php if (count($employees) >= 300): ?><div class="hr-foot" style="border-top:0;padding-top:0"><span class="hr-note">Showing the first 300 employees alphabetically.</span></div><?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>if (typeof Chart === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>');</script>

<script>
/* ── KPI count-up + meters ───────────────────────────────── */
(function () {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-count]').forEach(el => {
        const end = parseInt(el.dataset.count, 10) || 0;
        if (reduce || !end) { el.textContent = end.toLocaleString(); return; }
        const t0 = performance.now(), dur = 700;
        const tick = now => {
            const p = Math.min((now - t0) / dur, 1);
            el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString();
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
    requestAnimationFrame(() => document.querySelectorAll('.hr-meter i').forEach(i => i.style.width = i.dataset.w + '%'));
})();

/* ── Live clock ──────────────────────────────────────────── */
(function () {
    const c = document.getElementById('hrClock'), d = document.getElementById('hrDate');
    if (!c || !d) return;
    const tick = () => {
        const n = new Date();
        c.textContent = n.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        d.textContent = n.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    };
    tick(); setInterval(tick, 1000);
})();

/* ── Directory ───────────────────────────────────────────── */
(function () {
    const DATA = <?= json_encode($dir, $jf) ?>;
    const IMG = <?= json_encode($uploads_url, $jf) ?>;
    const PAGE = 20;
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const state = { q: '', st: '', sec: '', ap: '', sort: 'name', view: 'table', page: 1 };

    /* counts + appointment options */
    const cnt = { all: DATA.length, a: 0, l: 0, o: 0 };
    DATA.forEach(e => { if (e.st === 1) cnt.a++; else if (e.st === 2) cnt.l++; else cnt.o++; });
    $('cAll').textContent = cnt.all; $('cAct').textContent = cnt.a; $('cLv').textContent = cnt.l; $('cOt').textContent = cnt.o;
    [...new Set(DATA.map(e => e.ap).filter(Boolean))].sort().forEach(a => $('fAppt').insertAdjacentHTML('beforeend', `<option value="${esc(a)}">${esc(a)}</option>`));

    const statusOf = e => e.st === 1 ? 'act' : (e.st === 2 ? 'lv' : '');
    const avatar = e => `<span class="hr-av">${esc(e.i || '?')}${e.pic ? `<img src="${esc(IMG + e.pic)}" alt="" loading="lazy" onerror="this.remove()">` : ''}</span>`;
    const apChip = e => e.ap ? `<span class="hr-chip pl" style="background:${esc(e.apc)}22;color:${esc(e.apc)}">${esc(e.ap)}</span>` : '—';

    function filtered() {
        const t = state.q.toLowerCase();
        let r = DATA.filter(e =>
            (!t || (e.n + ' ' + e.id + ' ' + e.pos + ' ' + e.sec).toLowerCase().includes(t)) &&
            (!state.st || (state.st === 'x' ? (e.st !== 1 && e.st !== 2) : String(e.st) === state.st)) &&
            (!state.sec || e.sec === state.sec) &&
            (!state.ap || e.ap === state.ap));
        const cmp = { 'name': (a, b) => 0, 'name-d': (a, b) => 0, 'sec': (a, b) => a.sec.localeCompare(b.sec) || a.n.localeCompare(b.n), 'pos': (a, b) => a.pos.localeCompare(b.pos) || a.n.localeCompare(b.n) }[state.sort];
        if (state.sort === 'name-d') r = r.slice().reverse(); else if (state.sort !== 'name') r = r.slice().sort(cmp);
        return r;
    }

    function render() {
        const rows = filtered(), n = rows.length, pages = Math.max(1, Math.ceil(n / PAGE));
        state.page = Math.min(state.page, pages);
        const start = (state.page - 1) * PAGE, slice = rows.slice(start, start + PAGE);

        $('tbody').innerHTML = slice.map(e => `<tr>
            <td><div class="hr-person">${avatar(e)}<div><div class="hr-pn">${esc(e.n)}</div><div class="hr-pi">ID ${esc(e.id || '—')}</div></div></div></td>
            <td>${esc(e.pos || '—')}</td><td>${esc(e.sec || '—')}</td>
            <td><span class="hr-chip ${statusOf(e)}">${esc(e.stn || 'Unknown')}</span></td><td>${apChip(e)}</td></tr>`).join('');
        $('cardWrap').innerHTML = slice.map(e => `<div class="hr-ec">${avatar(e)}
            <div class="hr-pn">${esc(e.n)}</div><div class="hr-pi">ID ${esc(e.id || '—')}</div>
            <div class="hr-pos">${esc(e.pos || '—')}</div>
            <div class="tags"><span class="hr-chip ${statusOf(e)}">${esc(e.stn || 'Unknown')}</span>${apChip(e)}</div>
            <div class="hr-note" style="margin-top:8px">${esc(e.sec || '')}</div></div>`).join('');

        $('tableWrap').style.display = state.view === 'table' && n ? '' : 'none';
        $('cardWrap').style.display  = state.view === 'cards' && n ? 'grid' : 'none';
        $('noRows').style.display = n ? 'none' : 'block';
        $('range').textContent = n ? `Showing ${start + 1}–${start + slice.length} of ${n}` : '0 results';
        $('pageInfo').textContent = `${state.page} / ${pages}`;
        $('prev').disabled = state.page <= 1; $('next').disabled = state.page >= pages;

        document.querySelectorAll('.hr-row').forEach(b => b.classList.toggle('on', b.dataset.section === state.sec));
        const tags = [];
        if (state.sec) tags.push(['sec', 'Section: ' + state.sec]);
        if (state.ap) tags.push(['ap', 'Appointment: ' + state.ap]);
        $('tagBar').innerHTML = tags.map(t => `<span class="hr-tag">${esc(t[1])}<button type="button" data-clear="${t[0]}" aria-label="Remove filter">&times;</button></span>`).join('');
        $('tagBar').classList.toggle('show', tags.length > 0);
    }

    let timer;
    $('q').addEventListener('input', e => { clearTimeout(timer); timer = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; render(); }, 120); });
    $('statusSeg').addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        state.st = b.dataset.st; state.page = 1;
        $('statusSeg').querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b)); render();
    });
    $('fAppt').addEventListener('change', e => { state.ap = e.target.value; state.page = 1; render(); });
    $('fSort').addEventListener('change', e => { state.sort = e.target.value; state.page = 1; render(); });
    $('prev').addEventListener('click', () => { state.page--; render(); });
    $('next').addEventListener('click', () => { state.page++; render(); });
    $('vTable').addEventListener('click', () => { state.view = 'table'; $('vTable').classList.add('on'); $('vCards').classList.remove('on'); render(); });
    $('vCards').addEventListener('click', () => { state.view = 'cards'; $('vCards').classList.add('on'); $('vTable').classList.remove('on'); render(); });
    $('tagBar').addEventListener('click', e => {
        const b = e.target.closest('[data-clear]'); if (!b) return;
        if (b.dataset.clear === 'sec') state.sec = ''; else { state.ap = ''; $('fAppt').value = ''; }
        state.page = 1; render();
    });
    document.querySelectorAll('.hr-row').forEach(b => b.addEventListener('click', () => {
        state.sec = state.sec === b.dataset.section ? '' : b.dataset.section; state.page = 1; render();
        if (state.sec) $('directory').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));

    /* CSV export of the current filtered list (spreadsheet-formula safe) */
    $('btnExport')?.addEventListener('click', () => {
        const safe = v => { v = String(v ?? ''); if (/^[=+\-@\t\r]/.test(v)) v = "'" + v; return '"' + v.replace(/"/g, '""') + '"'; };
        const lines = [['Name', 'ID Number', 'Position', 'Section', 'Employment Status', 'Appointment'].map(safe).join(',')]
            .concat(filtered().map(e => [e.n, e.id, e.pos, e.sec, e.stn, e.ap].map(safe).join(',')));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'employees-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });
    render();
})();

/* ── Charts (Chart.js v2 from AdminLTE and v3+) ──────────── */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { document.querySelectorAll('.hr-chart').forEach(c => c.style.display = 'none'); return; }
    const v2 = !!(Chart.defaults && Chart.defaults.global);
    const css = n => getComputedStyle(document.body).getPropertyValue(n).trim();
    const font = "Inter, 'Segoe UI', system-ui, sans-serif";
    const textColor = css('--text2') || '#46604f';
    if (v2) { Chart.defaults.global.defaultFontFamily = font; Chart.defaults.global.defaultFontColor = textColor; }
    else    { Chart.defaults.font.family = font; Chart.defaults.color = textColor; }
    const surface = css('--surface') || '#fff', grid = css('--border') || '#e3ebe6';
    const make = (id, cfg) => { const el = document.getElementById(id); if (el) try { new Chart(el.getContext('2d'), cfg); } catch (e) { console.error(id, e); } };
    const legendBottom = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } };
    const doughnut = (labels, data, colors) => ({
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 3, borderColor: surface }] },
        options: v2 ? { responsive: true, maintainAspectRatio: false, cutoutPercentage: 70, legend: legendBottom }
                    : { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: legendBottom } }
    });

    make('genderChart', doughnut(['Male', 'Female'], [<?= $male ?>, <?= $female ?>], ['#2563eb', '#db2777']));

    const appt = <?= json_encode($appointments, $jf) ?>;
    if (appt.length) make('appointmentChart', doughnut(appt.map(d => d.status_name), appt.map(d => parseInt(d.count)), appt.map(d => d.color || '#2a9863')));

    const off = <?= json_encode($offices, $jf) ?>;
    if (off.length) make('officeChart', {
        type: 'bar',
        data: { labels: off.map(d => d.office_name), datasets: [{ label: 'Employees', data: off.map(d => parseInt(d.count)), backgroundColor: '#2a9863', borderRadius: 6, maxBarThickness: 32 }] },
        options: v2
            ? { responsive: true, maintainAspectRatio: false, legend: { display: false },
                scales: { xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10 } }], yAxes: [{ gridLines: { color: grid }, ticks: { beginAtZero: true, precision: 0, fontSize: 10 } }] } }
            : { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, font: { size: 10 } } } } }
    });
});
</script>
</body>
</html>
<?php
/**
 * views/security.php — Security Monitor (Admin)
 *
 * Shows sign-in activity from the `login_attempts` table, lists accounts / IP
 * addresses that are currently locked out, and lets an administrator unlock them.
 *
 * Unlocking does NOT delete history: it adds an "unlocked" event (result = 3).
 * login.php only counts failures that happened AFTER the latest unlock event.
 *
 * Permission: manage_users  (same as users.php)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();

checkPermission('manage_users');

/* ── Lockout settings: KEEP IN SYNC with login.php ───────────────────── */
const SEC_MAX_FAILS_USER = 5;
const SEC_MAX_FAILS_IP   = 20;
const SEC_WINDOW_MIN     = 15;

/* ── Helpers (prefixed so they never clash with includes/auth.php) ───── */
function sec_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function sec_rows(mysqli $db, $sql, $types = '', array $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) { throw new RuntimeException($db->error); }
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function sec_flash($type, $title, $text) {
    $_SESSION['swal'] = ['type' => $type, 'title' => $title, 'text' => $text];
}

function sec_browser($ua) {
    $ua = (string)$ua;
    if ($ua === '') return '—';
    if (stripos($ua, 'Unlocked by') === 0) return $ua;
    $os = 'Unknown OS';
    if (stripos($ua, 'Windows') !== false)                              $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false)                          $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua))                     $os = 'iOS';
    elseif (stripos($ua, 'Mac OS X') !== false)                         $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false)                            $os = 'Linux';
    $br = 'Browser';
    if (stripos($ua, 'Edg/') !== false)          $br = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false)      $br = 'Opera';
    elseif (stripos($ua, 'Chrome/') !== false)   $br = 'Chrome';
    elseif (stripos($ua, 'Firefox/') !== false)  $br = 'Firefox';
    elseif (stripos($ua, 'Safari/') !== false)   $br = 'Safari';
    return $br . ' · ' . $os;
}

function sec_result_badge($r) {
    switch ((int)$r) {
        case 1:  return ['Success',  'badge-success'];
        case 0:  return ['Failed',   'badge-danger'];
        case 2:  return ['Blocked',  'badge-warning'];
        case 3:  return ['Unlocked', 'badge-info'];
        default: return ['Unknown',  'badge-secondary'];
    }
}

/* ── CSRF token (login.php creates it at sign-in; create if missing) ── */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

/* ── Handle unlock requests ──────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $posted)) {
        sec_flash('error', 'Security check failed', 'Your session token did not match. Please reload the page and try again.');
    } else {
        try {
            $note = substr('Unlocked by ' . ($_SESSION['username'] ?? 'administrator'), 0, 255);
            $res  = 3; // 3 = unlocked by administrator

            if (!empty($_POST['unlock_user'])) {
                $target = mb_substr(trim((string)$_POST['unlock_user']), 0, 100);
                $dash   = '-';
                $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, user_agent, result) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('sssi', $target, $dash, $note, $res);
                $stmt->execute();
                sec_flash('success', 'Account unlocked', '"' . $target . '" can sign in again.');

            } elseif (!empty($_POST['unlock_ip'])) {
                $ip = trim((string)$_POST['unlock_ip']);
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    sec_flash('error', 'Invalid address', 'That is not a valid IP address.');
                } else {
                    $dash = '-';
                    $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, user_agent, result) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $dash, $ip, $note, $res);
                    $stmt->execute();
                    sec_flash('success', 'IP address unlocked', $ip . ' can sign in again.');
                }
            }
        } catch (Throwable $ex) {
            error_log('[AIMS security] unlock failed: ' . $ex->getMessage());
            sec_flash('error', 'Could not unlock', 'The database request failed. Check that the login_attempts table exists.');
        }
    }
    header('Location: security.php');
    exit();
}

/* ── Load data ───────────────────────────────────────────────────────── */
$tableMissing = false;
$kpi = ['ok' => 0, 'failed' => 0, 'blocked' => 0];
$lockedUsers = []; $lockedIps = []; $log = []; $knownUsers = [];
$nowTs = time();

try {
    $nowTs = (int)sec_rows($db, "SELECT UNIX_TIMESTAMP(NOW()) AS n")[0]['n'];

    $k = sec_rows($db, "SELECT COALESCE(SUM(result = 1),0) AS ok, COALESCE(SUM(result = 0),0) AS failed,
                               COALESCE(SUM(result = 2),0) AS blocked
                        FROM login_attempts WHERE attempted_at > (NOW() - INTERVAL 24 HOUR)")[0];
    $kpi = ['ok' => (int)$k['ok'], 'failed' => (int)$k['failed'], 'blocked' => (int)$k['blocked']];

    // Failures + unlock events inside the lockout window, oldest first.
    // An unlock event clears the failures collected so far for that user / IP.
    $w = SEC_WINDOW_MIN;
    $events = sec_rows($db, "SELECT result, username, ip_address, UNIX_TIMESTAMP(attempted_at) AS ts
                             FROM login_attempts
                             WHERE result IN (0,3) AND attempted_at > (NOW() - INTERVAL ? MINUTE)
                             ORDER BY attempted_at ASC, id ASC", 'i', [$w]);
    $uF = []; $iF = []; $uName = [];
    foreach ($events as $ev) {
        $uk = strtolower($ev['username']); $ip = $ev['ip_address'];
        if ((int)$ev['result'] === 3) {
            if ($ev['username']   !== '-') unset($uF[$uk]);
            if ($ev['ip_address'] !== '-') unset($iF[$ip]);
        } else {
            $uF[$uk][] = (int)$ev['ts']; $uName[$uk] = $uName[$uk] ?? $ev['username'];
            $iF[$ip][] = (int)$ev['ts'];
        }
    }
    foreach ($uF as $uk => $ts) {
        if (count($ts) >= SEC_MAX_FAILS_USER) {
            $lockedUsers[] = ['name' => $uName[$uk], 'count' => count($ts), 'last' => end($ts),
                              'unlock' => $ts[count($ts) - SEC_MAX_FAILS_USER] + $w * 60];
        }
    }
    foreach ($iF as $ip => $ts) {
        if (count($ts) >= SEC_MAX_FAILS_IP) {
            $lockedIps[] = ['ip' => $ip, 'count' => count($ts), 'last' => end($ts),
                            'unlock' => $ts[count($ts) - SEC_MAX_FAILS_IP] + $w * 60];
        }
    }

    $log = sec_rows($db, "SELECT id, username, ip_address, user_agent, result, UNIX_TIMESTAMP(attempted_at) AS ts
                          FROM login_attempts ORDER BY id DESC LIMIT 500");
    foreach (sec_rows($db, "SELECT LOWER(user) AS u FROM users") as $r) { $knownUsers[$r['u']] = true; }

} catch (Throwable $ex) {
    error_log('[AIMS security] load failed: ' . $ex->getMessage());
    $tableMissing = true;
}

$lockedCount = count($lockedUsers) + count($lockedIps);
$csrf = $_SESSION['csrf'];

function sec_mins_left($unlockTs, $nowTs) { return max(1, (int)ceil(($unlockTs - $nowTs) / 60)); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-ACIMO | Security Monitor</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
  <style>
    .sec-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px; }
    @media (max-width:991px){ .sec-kpis { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:575px){ .sec-kpis { grid-template-columns:1fr; } }
    .sec-kpi { display:flex; align-items:center; gap:14px; padding:16px 18px; border-radius:14px;
               background:var(--h-surface3, #f4f7f5); border:1px solid var(--h-border, #e3ebe6); }
    .sec-ico { width:44px; height:44px; border-radius:12px; display:grid; place-items:center; font-size:1.05rem; flex:none; }
    .sec-ico.g { background:rgba(42,152,99,.16);  color:#2a9863; }
    .sec-ico.r { background:rgba(220,53,69,.15);  color:#dc3545; }
    .sec-ico.a { background:rgba(217,119,6,.16);  color:#d97706; }
    .sec-ico.b { background:rgba(37,99,235,.15);  color:#2563eb; }
    .sec-kl { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--h-muted, #7b8f83); }
    .sec-kv { font-size:1.9rem; font-weight:700; line-height:1.1; font-variant-numeric:tabular-nums; }
    .sec-empty { text-align:center; padding:26px 10px; color:var(--h-muted, #7b8f83); }
    .sec-empty i { display:block; font-size:2rem; margin-bottom:8px; color:#2a9863; opacity:.8; }
    .sec-tag { font-size:.68rem; padding:2px 7px; border-radius:99px; background:rgba(220,53,69,.15); color:#dc3545; font-weight:700; margin-left:6px; white-space:nowrap; }
    .mod-chips .mod-chip[data-filter] { cursor:pointer; }
    .sec-mono { font-family:ui-monospace, Consolas, monospace; font-size:.86rem; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  <div class="content-wrapper">

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
                <div class="pg-hero-title"><i class="fas fa-shield-alt"></i>Security Monitor</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Review sign-in activity and unlock locked accounts.</p>
            </div>
        </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if ($tableMissing): ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          The <code>login_attempts</code> table could not be read. Run <code>security_schema.sql</code> once in phpMyAdmin,
          then reload this page.
        </div>
        <?php else: ?>

        <!-- KPIs (last 24 hours) -->
        <div class="sec-kpis">
          <div class="sec-kpi"><div class="sec-ico g"><i class="fas fa-sign-in-alt"></i></div>
            <div><div class="sec-kl">Successful sign-ins · 24h</div><div class="sec-kv"><?= $kpi['ok'] ?></div></div></div>
          <div class="sec-kpi"><div class="sec-ico r"><i class="fas fa-times-circle"></i></div>
            <div><div class="sec-kl">Failed attempts · 24h</div><div class="sec-kv"><?= $kpi['failed'] ?></div></div></div>
          <div class="sec-kpi"><div class="sec-ico a"><i class="fas fa-ban"></i></div>
            <div><div class="sec-kl">Blocked attempts · 24h</div><div class="sec-kv"><?= $kpi['blocked'] ?></div></div></div>
          <div class="sec-kpi"><div class="sec-ico b"><i class="fas fa-lock"></i></div>
            <div><div class="sec-kl">Locked right now</div><div class="sec-kv"><?= $lockedCount ?></div></div></div>
        </div>

        <!-- Locked accounts / IPs -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Locked Accounts &amp; Addresses</h3>
            <div class="card-tools">
              <small class="text-muted">Locks clear automatically after <?= SEC_WINDOW_MIN ?> minutes. Verify the person’s identity before unlocking.</small>
            </div>
          </div>
          <div class="card-body">
            <?php if ($lockedCount === 0): ?>
              <div class="sec-empty"><i class="fas fa-check-circle"></i>No accounts or addresses are locked right now.</div>
            <?php else: ?>
            <table class="table table-striped mod-table">
              <thead>
                <tr><th>Type</th><th>Target</th><th>Failed attempts</th><th>Last failure</th><th>Auto-unlock</th><th style="width:120px">Action</th></tr>
              </thead>
              <tbody>
              <?php foreach ($lockedUsers as $l): ?>
                <tr>
                  <td><span class="badge badge-primary">Account</span></td>
                  <td><span class="mod-title"><?= sec_h($l['name']) ?></span>
                      <?php if (!isset($knownUsers[strtolower($l['name'])])): ?><span class="sec-tag">not a user</span><?php endif; ?></td>
                  <td><?= (int)$l['count'] ?></td>
                  <td><?= sec_h(date('M j, Y g:i A', $l['last'])) ?></td>
                  <td>in ~<?= sec_mins_left($l['unlock'], $nowTs) ?> min</td>
                  <td>
                    <form method="POST" class="d-inline unlock-form">
                      <input type="hidden" name="csrf_token" value="<?= sec_h($csrf) ?>">
                      <input type="hidden" name="unlock_user" value="<?= sec_h($l['name']) ?>">
                      <button type="button" class="btn btn-sm btn-success unlock-btn" data-label="<?= sec_h($l['name']) ?>" data-kind="account">
                        <i class="fas fa-unlock"></i> Unlock
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($lockedIps as $l): ?>
                <tr>
                  <td><span class="badge badge-warning">IP address</span></td>
                  <td><span class="mod-title sec-mono"><?= sec_h($l['ip']) ?></span></td>
                  <td><?= (int)$l['count'] ?></td>
                  <td><?= sec_h(date('M j, Y g:i A', $l['last'])) ?></td>
                  <td>in ~<?= sec_mins_left($l['unlock'], $nowTs) ?> min</td>
                  <td>
                    <form method="POST" class="d-inline unlock-form">
                      <input type="hidden" name="csrf_token" value="<?= sec_h($csrf) ?>">
                      <input type="hidden" name="unlock_ip" value="<?= sec_h($l['ip']) ?>">
                      <button type="button" class="btn btn-sm btn-success unlock-btn" data-label="<?= sec_h($l['ip']) ?>" data-kind="address">
                        <i class="fas fa-unlock"></i> Unlock
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Activity log -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Sign-in Activity <small class="text-muted">(latest 500)</small></h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <span class="mod-chip active" data-filter="">All<span class="n"><?= count($log) ?></span></span>
                <span class="mod-chip" data-filter="Failed">Failed</span>
                <span class="mod-chip" data-filter="Blocked">Blocked</span>
                <span class="mod-chip" data-filter="Success">Success</span>
                <span class="mod-chip" data-filter="Unlocked">Unlocked</span>
              </div>
            </div>
          </div>
          <div class="card-body">
            <table id="activityTable" class="table table-striped mod-table">
              <thead>
                <tr><th>Time</th><th>Username</th><th>Result</th><th>IP Address</th><th>Browser / Note</th></tr>
              </thead>
              <tbody>
              <?php foreach ($log as $r):
                  list($label, $cls) = sec_result_badge($r['result']);
                  $isUnlock = ((int)$r['result'] === 3);
                  $uname = ($r['username'] === '-') ? '—' : $r['username'];
                  $ipTxt = ($r['ip_address'] === '-') ? '—' : $r['ip_address'];
              ?>
                <tr>
                  <td data-order="<?= (int)$r['ts'] ?>"><?= sec_h(date('M j, Y g:i:s A', (int)$r['ts'])) ?></td>
                  <td>
                    <?= sec_h($uname) ?>
                    <?php if ((int)$r['result'] === 0 && !isset($knownUsers[strtolower($r['username'])])): ?>
                      <span class="sec-tag" title="This username does not exist in AIMS (possible guessing)">unknown user</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
                  <td class="sec-mono"><?= sec_h($ipTxt) ?></td>
                  <td title="<?= sec_h($r['user_agent']) ?>"><?= sec_h(sec_browser($r['user_agent'])) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php endif; ?>
      </div>
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>

<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: <?= json_encode($_SESSION['swal']['type']) ?>,
        title: <?= json_encode($_SESSION['swal']['title']) ?>,
        text: <?= json_encode($_SESSION['swal']['text']) ?>,
        showConfirmButton: true,
        confirmButtonColor: '#2a9863',
        timer: 3500
    });
});
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script>
$(document).on('click', '.unlock-btn', function (e) {
    e.preventDefault();
    var form = $(this).closest('form'), label = $(this).data('label'), kind = $(this).data('kind');
    Swal.fire({
        title: 'Unlock ' + kind + '?',
        text: 'Allow "' + label + '" to try signing in again? Only do this after confirming the person\'s identity.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2a9863',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Unlock'
    }).then(function (result) { if (result.isConfirmed) { form.trigger('submit'); } });
});

$(function () {
    var table = $("#activityTable").DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: { lengthMenu: "Show _MENU_ events per page", paginate: { previous: "&laquo;", next: "&raquo;" } }
    });
    // filter chips -> Result column
    $('.mod-chip[data-filter]').on('click', function () {
        $('.mod-chip[data-filter]').removeClass('active');
        $(this).addClass('active');
        var f = $(this).data('filter');
        table.column(2).search(f ? '^' + f + '$' : '', true, false).draw();
    });
});
</script>
</body>
</html>
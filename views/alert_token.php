<?php
// views/alert_token.php
// Shows the logged-in employee their personal token for the AIMS Queue Alert helper.
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit;
}

$db  = (new Database())->getConnection();
$emp = (int)$_SESSION['emp_id'];

$token = null;
$stmt = $db->prepare("SELECT token FROM queue_alert_tokens WHERE emp_id = ?");
$stmt->bind_param("i", $emp);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $token = $row['token'];
}

if ($token === null || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regen']))) {
    $token = bin2hex(random_bytes(20));
    $stmt = $db->prepare("REPLACE INTO queue_alert_tokens (emp_id, token) VALUES (?, ?)");
    $stmt->bind_param("is", $emp, $token);
    $stmt->execute();
}

$server = defined('SCAN_BASE_URL') ? SCAN_BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Queue Alert Token | AIMS</title>
<style>
  body{font-family:Arial,sans-serif;background:#f0faf4;margin:0;padding:40px;color:#1a2e1a}
  .box{max-width:560px;margin:auto;background:#fff;border:2px solid #2dc95a;border-radius:14px;padding:28px}
  h2{margin-top:0;color:#0e5225}
  .val{font-family:Consolas,monospace;font-size:16px;background:#f3f4f6;border-radius:8px;padding:12px;word-break:break-all;margin:6px 0 16px}
  button{background:#1a8a3c;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:bold;cursor:pointer}
  button.warn{background:#6b7280}
  small{color:#555}
</style>
</head>
<body>
<div class="box">
  <h2>AIMS Queue Alert - Setup Values</h2>
  <p>Enter these two values the first time the <b>AIMS Queue Alert</b> helper asks for them on your PC.</p>

  <b>Server address</b>
  <div class="val" id="srv"><?= htmlspecialchars($server) ?></div>

  <b>Your personal token</b>
  <div class="val" id="tok"><?= htmlspecialchars($token) ?></div>

  <button onclick="navigator.clipboard.writeText(document.getElementById('tok').innerText);this.innerText='Copied!'">Copy token</button>

  <form method="post" style="display:inline" onsubmit="return confirm('Generate a new token? The helper on your PC will need the new one.');">
    <button class="warn" name="regen" value="1">Generate new token</button>
  </form>

  <p><small>Keep this token private. It only lets the helper read and acknowledge <i>your</i> client alerts.</small></p>
</div>
</body>
</html>
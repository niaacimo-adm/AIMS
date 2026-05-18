<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT u.id, u.user, u.password, u.role_id, u.employee_id, ur.name as role_name 
            FROM users u
            JOIN user_roles ur ON u.role_id = ur.id
            WHERE u.user = ? AND u.employee_id IS NOT NULL
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['emp_id']    = $user['employee_id'];
                $_SESSION['username']  = $user['user'];
                $_SESSION['role_id']   = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];

                $stmt = $db->prepare("
                    SELECT p.name FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->bind_param("i", $user['role_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                $permissions = [];
                while ($row = $result->fetch_assoc()) { $permissions[] = $row['name']; }
                $_SESSION['permissions'] = $permissions;
                $success = 'Login successful!';
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <title>NIA ACIMO - Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS — dark (default)
        ══════════════════════════════════════════ */
        :root {
            --green:       #24e78f;
            --green-dark:  #2a9863;
            --gold:        #d4af37;
            --gold-light:  #f4e4a6;

            /* backgrounds */
            --body-bg:         #0b1f17;
            --panel-left-bg:   linear-gradient(158deg,#1c4d38 0%,#102f22 52%,#091d14 100%);
            --panel-right-bg:  rgba(10,24,17,.88);
            --card-shadow:     0 48px 110px rgba(0,0,0,.6);
            --loader-bg:       rgba(5,14,10,.97);

            /* mesh gradient overlay */
            --mesh-a: rgba(36,231,143,.18);
            --mesh-b: rgba(42,152,99,.14);
            --mesh-c: rgba(212,175,55,.07);
            --mesh-base: linear-gradient(160deg,#0b1f17 0%,#071510 60%,#0d2318 100%);

            /* orbs */
            --orb-1: rgba(36,231,143,.12);
            --orb-2: rgba(42,152,99,.10);
            --orb-3: rgba(212,175,55,.07);
            --orb-4: rgba(36,231,143,.06);

            /* dot grid */
            --dot-color: rgba(36,231,143,.06);

            /* card border */
            --card-border: rgba(255,255,255,.08);
            --card-inset:  rgba(255,255,255,.12);

            /* right panel accents */
            --accent-top:    linear-gradient(90deg,transparent,var(--green),transparent);
            --accent-side:   linear-gradient(180deg,transparent 15%,rgba(36,231,143,.15) 50%,transparent 85%);
            --corner-border: rgba(36,231,143,.1);

            /* ring / badge */
            --ring-color: rgba(36,231,143,.1);
            --badge-bg:   rgba(36,231,143,.07);
            --badge-border:rgba(36,231,143,.18);
            --badge-text: rgba(255,255,255,.65);
            --arc-glow:   rgba(36,231,143,.2);

            /* logo ring */
            --logo-ring-bg:    rgba(255,255,255,.05);
            --logo-ring-border:rgba(36,231,143,.28);
            --logo-ring-glow1: rgba(36,231,143,.06);
            --logo-ring-glow2: rgba(36,231,143,.10);

            /* typography */
            --h2-color:      #ffffff;
            --h3-color:      var(--green);
            --tagline-color: rgba(255,255,255,.55);
            --title-color:   #ffffff;
            --subtitle-color:rgba(255,255,255,.55);
            --footer-color:  rgba(255,255,255,.2);

            /* inputs */
            --input-bg:         rgba(255,255,255,.04);
            --input-border:     rgba(255,255,255,.09);
            --input-color:      #ffffff;
            --input-focus-bg:   rgba(36,231,143,.04);
            --label-color:      rgba(255,255,255,.38);
            --label-bg:         transparent;
            --icon-color:       rgba(255,255,255,.28);
        }

        /* ══════════════════════════════════════════
           DESIGN TOKENS — light
        ══════════════════════════════════════════ */
        [data-theme="light"] {
            --body-bg:         #1a5c38 ;
            --panel-left-bg:   linear-gradient(158deg,#1e5c3f 0%,#1a4d34 52%,#133b27 100%);
            --panel-right-bg:  rgba(255,255,255,.93);
            --card-shadow:     0 40px 90px rgba(0,80,40,.22);
            --loader-bg:       rgba(184,223,201,.97);

            --mesh-a: rgba(20,140,80,.28);
            --mesh-b: rgba(42,152,99,.22);
            --mesh-c: rgba(212,175,55,.12);
            --mesh-base: linear-gradient(160deg,#24e78f  0%,#1a5c38 40%,#0e3d24 70%,#0e3d24 100%);

            --orb-1: rgba(20,140,80,.30);
            --orb-2: rgba(42,152,99,.24);
            --orb-3: rgba(212,175,55,.16);
            --orb-4: rgba(36,180,110,.18);

            --dot-color: rgba(15,90,50,.14);

            --card-border: rgba(36,152,99,.15);
            --card-inset:  rgba(255,255,255,.8);

            --accent-top:  linear-gradient(90deg,transparent,var(--green-dark),transparent);
            --accent-side: linear-gradient(180deg,transparent 15%,rgba(42,152,99,.15) 50%,transparent 85%);
            --corner-border:rgba(42,152,99,.15);

            --ring-color: rgba(36,152,99,.12);
            --badge-bg:   rgba(36,152,99,.08);
            --badge-border:rgba(36,152,99,.22);
            --badge-text: rgba(255,255,255,.85);
            --arc-glow:   rgba(36,180,110,.25);

            --logo-ring-bg:    rgba(255,255,255,.15);
            --logo-ring-border:rgba(255,255,255,.5);
            --logo-ring-glow1: rgba(255,255,255,.12);
            --logo-ring-glow2: rgba(255,255,255,.2);

            --h2-color:      #ffffff;
            --h3-color:      #b8f0d4;
            --tagline-color: rgba(255,255,255,.75);
            --title-color:   #0f2d1e;
            --subtitle-color:rgba(15,45,30,.55);
            --footer-color:  rgba(15,45,30,.3);

            --input-bg:       rgba(15,45,30,.04);
            --input-border:   rgba(15,45,30,.14);
            --input-color:    #0f2d1e;
            --input-focus-bg: rgba(36,180,110,.06);
            --label-color:    rgba(15,45,30,.45);
            --label-bg:       rgba(255,255,255,.92);
            --icon-color:     rgba(15,45,30,.35);
        }

        /* ══════════════════════════════════════════
           RESET & BASE
        ══════════════════════════════════════════ */
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:'Source Sans Pro','Segoe UI',sans-serif;
            min-height:100vh;
            background:var(--body-bg);
            display:flex; align-items:center; justify-content:center;
            overflow:hidden;
            transition:background .4s ease;
        }

        /* Animated mesh */
        body::before {
            content:'';
            position:fixed; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, var(--mesh-a) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, var(--mesh-b) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, var(--mesh-c) 0%, transparent 50%),
                var(--mesh-base);
            animation:meshDrift 22s ease-in-out infinite alternate;
            z-index:0;
            transition:background .4s ease;
        }
        @keyframes meshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }

        /* Orbs */
        .orbs { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .orb  { position:absolute; border-radius:50%; filter:blur(70px); animation:orbFloat 18s ease-in-out infinite; }
        .orb-1 { width:340px; height:340px; background:var(--orb-1); top:-90px;    left:-70px;  animation-delay:0s;   animation-duration:21s; }
        .orb-2 { width:280px; height:280px; background:var(--orb-2); bottom:-70px; right:-50px; animation-delay:-7s;  animation-duration:17s; }
        .orb-3 { width:200px; height:200px; background:var(--orb-3); top:42%;      right:22%;   animation-delay:-13s; animation-duration:24s; }
        .orb-4 { width:160px; height:160px; background:var(--orb-4); bottom:18%;   left:12%;    animation-delay:-4s;  animation-duration:15s; }
        @keyframes orbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(22px,-32px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-16px,20px) scale(.95);  }
        }

        /* Dot grid */
        .dot-grid {
            position:fixed; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle,var(--dot-color) 1px,transparent 1px);
            background-size:36px 36px;
            transition:background-image .4s ease;
        }

        /* ══════════════════════════════════════════
           THEME TOGGLE BUTTON
        ══════════════════════════════════════════ */
        .theme-toggle {
            position:fixed; top:20px; right:20px; z-index:1000;
            width:52px; height:28px;
            background:none; border:none; cursor:pointer; padding:0;
        }

        .toggle-track {
            width:52px; height:28px; border-radius:14px;
            background:rgba(36,231,143,.2);
            border:1.5px solid rgba(36,231,143,.4);
            position:relative;
            transition:background .35s ease, border-color .35s ease, box-shadow .35s ease;
            display:flex; align-items:center;
            box-shadow:0 2px 12px rgba(36,231,143,.15);
        }
        [data-theme="light"] .toggle-track {
            background:rgba(42,152,99,.15);
            border-color:rgba(42,152,99,.3);
            box-shadow:0 2px 12px rgba(42,152,99,.12);
        }

        .toggle-thumb {
            width:22px; height:22px; border-radius:50%;
            background:linear-gradient(135deg,var(--green),var(--green-dark));
            position:absolute; left:2px;
            display:flex; align-items:center; justify-content:center;
            transition:transform .35s cubic-bezier(.22,1,.36,1), background .35s ease;
            box-shadow:0 2px 8px rgba(0,0,0,.25);
            font-size:.62rem; color:#fff;
        }
        [data-theme="light"] .toggle-thumb {
            transform:translateX(24px);
            background:linear-gradient(135deg,#1e5c3f,#2a9863);
        }

        .icon-moon { display:block; }
        .icon-sun  { display:none;  }
        [data-theme="light"] .icon-moon { display:none;  }
        [data-theme="light"] .icon-sun  { display:block; }

        /* ══════════════════════════════════════════
           CARD
        ══════════════════════════════════════════ */
        .login-card {
            position:relative; z-index:10;
            width:min(980px,94vw);
            display:grid; grid-template-columns:1fr 1fr;
            border-radius:28px; overflow:hidden;
            box-shadow:var(--card-shadow), 0 0 0 1px var(--card-border), inset 0 1px 0 var(--card-inset);
            animation:cardUp .8s cubic-bezier(.22,1,.36,1) both;
            transition:box-shadow .4s ease;
        }
        @keyframes cardUp {
            from { opacity:0; transform:translateY(44px) scale(.96); }
            to   { opacity:1; transform:translateY(0)    scale(1);   }
        }

        /* ══════════════════════════════════════════
           LEFT PANEL (stays green in both modes)
        ══════════════════════════════════════════ */
        .panel-left {
            background:var(--panel-left-bg);
            padding:60px 44px;
            display:flex; flex-direction:column;
            justify-content:center; align-items:center; text-align:center;
            position:relative; overflow:hidden;
            transition:background .4s ease;
        }

        .panel-left .hex-bg {
            position:absolute; inset:0; pointer-events:none; opacity:.045;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }

        .ring-wrap {
            position:absolute; top:50%; left:50%;
            transform:translate(-50%,-50%);
            width:400px; height:400px; pointer-events:none;
        }
        .ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid var(--ring-color);
            animation:ringPulse 4s ease-in-out infinite;
        }
        .ring:nth-child(2) { inset:40px; border-color:var(--ring-color); animation-delay:.8s; opacity:.7; }
        .ring:nth-child(3) { inset:80px; border-color:var(--ring-color); animation-delay:1.6s; opacity:.5; }
        @keyframes ringPulse {
            0%,100% { opacity:.5; transform:scale(1);    }
            50%      { opacity:.9; transform:scale(1.04); }
        }

        .arc-glow {
            position:absolute; top:-70px; right:-70px;
            width:240px; height:240px; border-radius:50%;
            background:radial-gradient(circle,var(--arc-glow) 0%,transparent 70%);
            pointer-events:none;
        }

        /* Logo ring */
        .logo-wrap { position:relative; z-index:2; margin-bottom:30px; }
        .logo-ring {
            width:136px; height:136px; border-radius:50%;
            background:var(--logo-ring-bg);
            border:2px solid var(--logo-ring-border);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto;
            box-shadow:0 0 0 10px var(--logo-ring-glow1), 0 24px 56px rgba(0,0,0,.45);
            animation:logoBreathe 3.5s ease-in-out infinite;
            transition:all .4s ease;
        }
        @keyframes logoBreathe {
            0%,100% { box-shadow:0 0 0 10px var(--logo-ring-glow1), 0 24px 56px rgba(0,0,0,.45); }
            50%      { box-shadow:0 0 0 20px var(--logo-ring-glow2), 0 24px 56px rgba(0,0,0,.45); }
        }
        .logo-ring img { width:100px; filter:drop-shadow(0 4px 14px rgba(0,0,0,.45)); }

        .panel-left h2 {
            position:relative; z-index:2; color:var(--h2-color);
            font-size:1.48rem; font-weight:800; line-height:1.25; margin-bottom:6px;
            text-shadow:0 2px 14px rgba(0,0,0,.45);
        }
        .panel-left h3 {
            position:relative; z-index:2; color:var(--h3-color);
            font-size:1rem; font-weight:600; letter-spacing:.04em; margin-bottom:24px;
        }

        .divider {
            position:relative; z-index:2; width:56px; height:2px;
            margin:0 auto 22px;
            background:linear-gradient(90deg,transparent,var(--green),transparent);
            border-radius:2px;
        }

        .tagline {
            position:relative; z-index:2; color:var(--tagline-color);
            font-size:.85rem; line-height:1.65; max-width:230px;
        }

        .panel-badge {
            position:relative; z-index:2; margin-top:36px;
            background:var(--badge-bg); border:1px solid var(--badge-border);
            border-radius:40px; padding:8px 20px;
            display:inline-flex; align-items:center; gap:8px;
            color:var(--badge-text); font-size:.78rem; letter-spacing:.05em;
            transition:all .4s ease;
        }
        .panel-badge i { color:var(--green); }

        /* ══════════════════════════════════════════
           RIGHT PANEL
        ══════════════════════════════════════════ */
        .panel-right {
            background:var(--panel-right-bg);
            backdrop-filter:blur(28px);
            -webkit-backdrop-filter:blur(28px);
            padding:56px 52px;
            display:flex; flex-direction:column; justify-content:center;
            position:relative; overflow:hidden;
            transition:background .4s ease;
        }

        .panel-right::before {
            content:''; position:absolute; top:0; left:0; right:0; height:3px;
            background:var(--accent-top);
        }
        .panel-right::after {
            content:''; position:absolute; left:0; top:0; width:2px; height:100%;
            background:var(--accent-side);
        }

        .corner-accent {
            position:absolute; bottom:22px; right:22px;
            width:72px; height:72px;
            border-right:2px solid var(--corner-border);
            border-bottom:2px solid var(--corner-border);
            border-radius:0 0 12px 0;
            pointer-events:none; transition:border-color .4s ease;
        }

        /* ── Form header ── */
        .form-header { margin-bottom:34px; }
        .form-eyebrow {
            font-size:.71rem; font-weight:700; letter-spacing:.18em;
            text-transform:uppercase; color:var(--green); margin-bottom:6px;
        }
        .form-title {
            font-size:2rem; font-weight:800; color:var(--title-color);
            line-height:1.1; transition:color .4s ease;
        }
        .form-subtitle {
            margin-top:6px; color:var(--subtitle-color); font-size:.88rem;
            transition:color .4s ease;
        }

        /* ── Floating-label inputs ── */
        .field-wrap {
            position:relative; margin-bottom:20px;
            animation:fieldUp .55s cubic-bezier(.22,1,.36,1) both;
        }
        .field-wrap:nth-child(1) { animation-delay:.12s; }
        .field-wrap:nth-child(2) { animation-delay:.22s; }
        @keyframes fieldUp {
            from { opacity:0; transform:translateY(18px); }
            to   { opacity:1; transform:translateY(0);    }
        }

        .field-wrap input {
            width:100%; height:58px;
            background:var(--input-bg);
            border:1px solid var(--input-border);
            border-radius:14px;
            padding:18px 52px 0 18px;
            color:var(--input-color); font-size:.95rem;
            outline:none; font-family:inherit;
            transition:border-color .3s, background .3s, box-shadow .3s, color .4s;
        }
        .field-wrap input::placeholder { color:transparent; }
        .field-wrap input:focus {
            border-color:rgba(36,152,99,.5);
            background:var(--input-focus-bg);
            box-shadow:0 0 0 3px rgba(36,152,99,.1), 0 8px 24px rgba(0,0,0,.08);
        }

        /* Floating label */
        .field-wrap label {
            position:absolute; left:18px; top:50%;
            transform:translateY(-50%);
            color:var(--label-color); font-size:.9rem; font-weight:500;
            pointer-events:none; padding:0 3px; z-index:2;
            transition:all .22s cubic-bezier(.22,1,.36,1), color .4s ease;
            background:transparent;
        }
        .field-wrap input:focus + label,
        .field-wrap input:not(:placeholder-shown) + label {
            top:10px; transform:translateY(0);
            font-size:.68rem; font-weight:700;
            color:var(--green-dark); letter-spacing:.04em;
            background:var(--label-bg);
        }

        /* Icon */
        .field-icon {
            position:absolute; right:16px; top:50%;
            transform:translateY(-50%);
            color:var(--icon-color); font-size:.95rem;
            transition:color .3s, transform .3s;
            cursor:pointer; z-index:3;
            background:none; border:none; padding:4px; line-height:1;
        }
        .field-wrap:focus-within .field-icon {
            color:var(--green-dark);
            transform:translateY(-50%) scale(1.1);
        }

        /* Bottom sweep */
        .field-wrap::after {
            content:''; position:absolute;
            bottom:0; left:50%; width:0; height:2px;
            background:linear-gradient(90deg,var(--green),var(--green-dark));
            border-radius:0 0 14px 14px;
            transition:width .38s cubic-bezier(.22,1,.36,1), left .38s cubic-bezier(.22,1,.36,1);
        }
        .field-wrap:focus-within::after { width:100%; left:0; }

        /* Error shake */
        @keyframes shake {
            0%,100% { transform:translateX(0); }
            15%  { transform:translateX(-9px); }
            30%  { transform:translateX( 9px); }
            45%  { transform:translateX(-6px); }
            60%  { transform:translateX( 6px); }
            75%  { transform:translateX(-3px); }
            90%  { transform:translateX( 3px); }
        }
        .field-error input {
            border-color:rgba(220,53,69,.5) !important;
            box-shadow:0 0 0 3px rgba(220,53,69,.12) !important;
            animation:shake .45s ease !important;
        }

        /* ── Buttons ── */
        .btn-login, .btn-reset {
            width:100%; height:56px; border:none; border-radius:14px;
            color:#fff; font-size:.98rem; font-weight:700; letter-spacing:.04em;
            cursor:pointer; position:relative; overflow:hidden;
            transition:transform .25s, box-shadow .25s; font-family:inherit;
            display:flex; align-items:center; justify-content:center; gap:10px;
            animation:fieldUp .55s cubic-bezier(.22,1,.36,1) .32s both;
        }
        .btn-login {
            background:linear-gradient(135deg,var(--green) 0%,var(--green-dark) 100%);
            box-shadow:0 12px 32px rgba(36,152,99,.28); margin-top:6px;
        }
        .btn-reset {
            background:linear-gradient(135deg,var(--gold) 0%,#b8941f 100%);
            box-shadow:0 12px 32px rgba(212,175,55,.28); margin-top:6px;
        }
        .btn-login:hover  { transform:translateY(-2px); box-shadow:0 18px 42px rgba(36,152,99,.38); }
        .btn-reset:hover  { transform:translateY(-2px); box-shadow:0 18px 42px rgba(212,175,55,.38); }
        .btn-login:active, .btn-reset:active { transform:translateY(0); }

        .btn-login::before, .btn-reset::before {
            content:''; position:absolute;
            top:0; left:-100%; width:100%; height:100%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,.16),transparent);
            transition:left .5s ease;
        }
        .btn-login:hover::before, .btn-reset:hover::before { left:100%; }

        /* ── Link row ── */
        .link-row {
            text-align:center; margin-top:18px;
            animation:fieldUp .55s cubic-bezier(.22,1,.36,1) .42s both;
        }
        .text-link {
            color:var(--gold); font-size:.85rem;
            cursor:pointer; background:none; border:none;
            display:inline-flex; align-items:center; gap:6px;
            transition:color .2s, gap .2s; font-family:inherit; padding:0;
        }
        [data-theme="light"] .text-link { color:#9a7a00; }
        .text-link:hover { color:var(--gold-light); gap:10px; }
        [data-theme="light"] .text-link:hover { color:var(--gold); }

        /* ── Footer ── */
        .form-footer {
            text-align:center; margin-top:26px;
            color:var(--footer-color); font-size:.73rem; letter-spacing:.03em;
            animation:fieldUp .55s cubic-bezier(.22,1,.36,1) .5s both;
            transition:color .4s ease;
        }

        /* ── Forgot panel ── */
        .forgot-eyebrow {
            font-size:.71rem; font-weight:700; letter-spacing:.18em;
            text-transform:uppercase; color:var(--gold); margin-bottom:6px;
        }
        [data-theme="light"] .forgot-eyebrow { color:#9a7a00; }
        .forgot-title-text {
            font-size:2rem; font-weight:800; color:var(--title-color);
            line-height:1.1; margin-bottom:6px; transition:color .4s;
        }
        .forgot-desc {
            color:var(--subtitle-color); font-size:.86rem; line-height:1.65;
            margin-bottom:26px; transition:color .4s;
        }

        /* ── Loader ── */
        .loader-overlay {
            position:fixed; inset:0;
            background:var(--loader-bg);
            display:flex; align-items:center; justify-content:center;
            z-index:99998; opacity:0; visibility:hidden;
            transition:opacity .3s, visibility .3s, background .4s;
        }
        .loader-overlay.active { opacity:1; visibility:visible; }

        .nia-loader {
            width:176px; height:176px; position:relative;
            display:flex; align-items:center; justify-content:center;
        }
        .nia-loader-ring {
            position:absolute; width:100%; height:100%;
            border:3px solid transparent;
            border-top:3px solid var(--gold); border-radius:50%;
            animation:spin 1.5s linear infinite;
        }
        .nia-loader-ring:nth-child(2) { width:122%; height:122%; border-top-color:var(--green-dark); animation-duration:2s;   }
        .nia-loader-ring:nth-child(3) { width:144%; height:144%; border-top-color:rgba(36,231,143,.4); animation-duration:2.5s; }
        .nia-loader-logo {
            width:96px; height:96px; border-radius:50%;
            background:#fff; z-index:2;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 10px 30px rgba(0,0,0,.3);
        }
        .nia-loader-logo img { width:76px; }
        .loader-content { display:flex; flex-direction:column; align-items:center; gap:16px; }
        .loader-text {
            color:var(--title-color); font-size:.95rem; font-weight:600;
            letter-spacing:.06em; transition:color .4s;
        }
        [data-theme="dark"] .loader-text { color:#fff; }
        .progress-bar { width:176px; height:3px; background:rgba(128,128,128,.2); border-radius:2px; overflow:hidden; }
        .progress-fill {
            height:100%; width:0%; background:var(--gold); border-radius:2px;
            transition:width 3s linear; box-shadow:0 0 8px var(--gold);
        }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* ── SweetAlert overrides ── */
        .swal2-container { z-index:99999 !important; }
        .swal2-popup {
            border-radius:20px !important;
            font-family:'Source Sans Pro',sans-serif !important;
            box-shadow:0 30px 80px rgba(0,0,0,.25) !important;
        }
        .swal2-confirm {
            background:linear-gradient(135deg,var(--green) 0%,var(--green-dark) 100%) !important;
            border-radius:10px !important; font-weight:700 !important;
            padding:10px 32px !important;
            box-shadow:0 6px 20px rgba(36,152,99,.3) !important;
        }

        /* ── Responsive ── */
        @media (max-width:768px) {
            .login-card { grid-template-columns:1fr; }
            .panel-left  { padding:44px 32px; }
            .panel-right { padding:44px 30px; }
            .form-title, .forgot-title-text { font-size:1.65rem; }
            .theme-toggle { top:14px; right:14px; }
        }
        @media (max-width:480px) {
            body { padding:14px; }
            .login-card { border-radius:20px; }
            .panel-right { padding:34px 20px; }
        }
    </style>
</head>
<body>

<!-- Backgrounds -->
<div class="orbs">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="orb orb-4"></div>
</div>
<div class="dot-grid"></div>

<!-- Theme toggle -->
<button class="theme-toggle" id="themeToggle" aria-label="Toggle light/dark mode">
    <div class="toggle-track">
        <div class="toggle-thumb">
            <i class="fas fa-moon  icon-moon"></i>
            <i class="fas fa-sun   icon-sun"></i>
        </div>
    </div>
</button>

<!-- PHP bridges -->
<?php if (!empty($error)): ?>
<span id="phpError"   data-msg="<?= htmlspecialchars($error) ?>"    style="display:none"></span>
<?php endif; ?>
<?php if (!empty($success)): ?>
<span id="phpSuccess" data-redirect="views/dashboard.php"            style="display:none"></span>
<?php endif; ?>

<!-- Loader -->
<div class="loader-overlay" id="loaderOverlay">
    <div class="loader-content">
        <div class="nia-loader">
            <div class="nia-loader-ring"></div>
            <div class="nia-loader-ring"></div>
            <div class="nia-loader-ring"></div>
            <div class="nia-loader-logo">
                <img src="dist/img/nialogo.png" alt="NIA">
            </div>
        </div>
        <div class="loader-text">Authenticating&hellip;</div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="login-card">

    <!-- LEFT -->
    <div class="panel-left">
        <div class="hex-bg"></div>
        <div class="ring-wrap">
            <div class="ring"></div>
            <div class="ring"></div>
            <div class="ring"></div>
        </div>
        <div class="arc-glow"></div>

        <div class="logo-wrap">
            <div class="logo-ring">
                <img src="dist/img/nialogo.png" alt="NIA Logo">
            </div>
        </div>

        <h2> National Irrigation Administration</h2>
        <h3>Albay-Catanduanes IMO</h3>
        <div class="divider"></div>

        <div class="panel-badge">
            <i class="fas fa-shield-alt"></i>
            ACIMO Integrated Management Solution (AIMS)
        </div>
    </div>

    <!-- RIGHT -->
    <div class="panel-right">
        <div class="corner-accent"></div>

        <!-- LOGIN FORM -->
        <div id="loginFormContainer">
            <div class="form-header">
                <div class="form-eyebrow">AIMS Portal</div>
                <div class="form-title">Welcome back</div>
                <div class="form-subtitle">Sign in to your account to continue</div>
            </div>

            <form action="login.php" method="post" id="loginForm" autocomplete="off">
                <div class="field-wrap" id="wrap-username">
                    <input type="text" id="username" name="username"
                           placeholder="Username"
                           value="<?= htmlspecialchars($username) ?>" required>
                    <label for="username">Username</label>
                    <span class="field-icon"><i class="fas fa-user"></i></span>
                </div>

                <div class="field-wrap" id="wrap-password">
                    <input type="password" id="password" name="password"
                           placeholder="Password" required>
                    <label for="password">Password</label>
                    <button type="button" class="field-icon" id="passwordToggle" aria-label="Toggle password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="link-row">
                <button type="button" class="text-link" id="forgotPasswordLink">
                    <i class="fas fa-key"></i> Forgot Password?
                </button>
            </div>
        </div>

        <!-- FORGOT FORM -->
        <div id="forgotFormContainer" style="display:none">
            <div class="form-header">
                <div class="forgot-eyebrow">Account Recovery</div>
                <div class="forgot-title-text">Reset Password</div>
                <p class="forgot-desc">Enter your employee ID number. An administrator will be notified and will assist you with the reset process.</p>
            </div>

            <form id="forgotPasswordForm" autocomplete="off">
                <div class="field-wrap">
                    <input type="text" id="id_number" name="id_number"
                           placeholder="Employee ID Number" required>
                    <label for="id_number">Employee ID Number</label>
                    <span class="field-icon"><i class="fas fa-id-card"></i></span>
                </div>

                <button type="submit" class="btn-reset">
                    <i class="fas fa-paper-plane"></i> Request Password Reset
                </button>
            </form>

            <div class="link-row">
                <button type="button" class="text-link" id="backToLoginLink">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </button>
            </div>
        </div>
    </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {

    /* ── Theme toggle ─────────────────────────────── */
    var STORAGE_KEY = 'nia-theme';
    var $html = $('html');

    // Restore saved preference
    var saved = localStorage.getItem(STORAGE_KEY) || 'dark';
    $html.attr('data-theme', saved);

    $('#themeToggle').on('click', function () {
        var current = $html.attr('data-theme');
        var next    = current === 'dark' ? 'light' : 'dark';
        $html.attr('data-theme', next);
        localStorage.setItem(STORAGE_KEY, next);
    });

    /* ── SweetAlert helpers ───────────────────────── */
    function isDark() { return $html.attr('data-theme') === 'dark'; }

    function swalOpts(extra) {
        return $.extend({
            position: 'center',
            confirmButtonColor: '#2a9863',
            background: isDark() ? '#0f2d1e' : '#ffffff',
            color:      isDark() ? '#e0f7ec' : '#0f2d1e'
        }, extra);
    }

    function swalError(msg) {
        Swal.fire(swalOpts({
            icon: 'error',
            title: 'Login Failed',
            text:  msg,
            confirmButtonText: 'Try Again'
        }));
    }
    function swalWarning(msg) {
        Swal.fire(swalOpts({
            icon: 'warning',
            title: 'Hold on!',
            text:  msg,
            confirmButtonText: 'OK'
        }));
    }

    function shakeField(id) {
        var $el = $(id);
        $el.addClass('field-error');
        $el.find('input').one('input', function () { $el.removeClass('field-error'); });
        setTimeout(function () { $el.removeClass('field-error'); }, 700);
    }

    /* ── PHP error ────────────────────────────────── */
    var $err = $('#phpError');
    if ($err.length) {
        shakeField('#wrap-username');
        shakeField('#wrap-password');
        swalError($err.data('msg'));
    }

    /* ── PHP success ──────────────────────────────── */
    var $ok = $('#phpSuccess');
    if ($ok.length) {
        Swal.fire(swalOpts({
            icon: 'success',
            title: 'Login Successful!',
            showConfirmButton: false,
            timer: 1500
        })).then(function () {
            window.location.href = $ok.data('redirect');
        });
    }

    /* ── Password toggle ──────────────────────────── */
    $('#passwordToggle').on('click', function () {
        var inp  = $('#password');
        var icon = $(this).find('i');
        if (inp.attr('type') === 'password') {
            inp.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            inp.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    /* ── Login form ───────────────────────────────── */
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        var user = $('#username').val().trim();
        var pass = $('#password').val();

        if (!user) { shakeField('#wrap-username'); swalWarning('Please enter your username.'); return; }
        if (!pass) { shakeField('#wrap-password'); swalWarning('Please enter your password.'); return; }

        $('#loaderOverlay').addClass('active');
        $('#progressFill').css('width', '100%');

        var form = this;
        setTimeout(function () { form.submit(); }, 3000);
    });

    /* ── Form toggle ──────────────────────────────── */
    $('#forgotPasswordLink').on('click', function () {
        $('#loginFormContainer').fadeOut(280, function () { $('#forgotFormContainer').fadeIn(280); });
    });
    $('#backToLoginLink').on('click', function () {
        $('#forgotFormContainer').fadeOut(280, function () { $('#loginFormContainer').fadeIn(280); });
    });

    /* ── Forgot password ──────────────────────────── */
    $('#forgotPasswordForm').on('submit', function (e) {
        e.preventDefault();
        var id = $('#id_number').val().trim();
        if (!id) { swalWarning('Please enter your ID number.'); return; }

        $('#loaderOverlay').addClass('active');
        $('#progressFill').css('width', '100%');

        $.ajax({
            url: 'ajax_forgot_password.php',
            type: 'POST',
            data: { id_number: id },
            success: function (res) {
                $('#loaderOverlay').removeClass('active');
                if (res.success) {
                    Swal.fire(swalOpts({
                        position: 'center',
                        icon: 'success',
                        title: res.message || 'Request sent!',
                        showConfirmButton: false,
                        timer: 1500
                    })).then(function () {
                        $('#forgotPasswordForm')[0].reset();
                        $('#forgotFormContainer').fadeOut(280, function () {
                            $('#loginFormContainer').fadeIn(280);
                        });
                    });
                } else {
                    Swal.fire(swalOpts({
                        icon: 'error',
                        title: 'Not Found',
                        text:  res.message,
                        confirmButtonText: 'OK'
                    }));
                }
            },
            error: function () {
                $('#loaderOverlay').removeClass('active');
                swalError('Network error. Please try again.');
            }
        });
    });

});
</script>
</body>
</html>
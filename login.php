<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$username = '';

$database = new Database();
$db = $database->getConnection();

$forms_stmt = $db->prepare("SELECT * FROM company_forms WHERE is_active = TRUE ORDER BY created_at DESC");
$forms_stmt->execute();
$company_forms = $forms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
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
    <title>NIA ACIMO — Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="login.css">
    
</head>
<body>

<!-- BG layers -->
<div class="orbs">
    <div class="orb orb-1"></div><div class="orb orb-2"></div>
    <div class="orb orb-3"></div><div class="orb orb-4"></div>
</div>
<div class="dot-grid"></div>

<!-- Theme toggle -->
<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <div class="toggle-track">
        <div class="toggle-thumb">
            <i class="fas fa-moon icon-moon"></i>
            <i class="fas fa-sun  icon-sun"></i>
        </div>
    </div>
</button>

<!-- PHP bridges -->
<?php if (!empty($error)): ?>
<span id="phpError" data-msg="<?= htmlspecialchars($error) ?>" style="display:none"></span>
<?php endif; ?>
<?php if (!empty($success)): ?>
<span id="phpSuccess" data-redirect="views/dashboard.php" style="display:none"></span>
<?php endif; ?>

<!-- Loader -->
<div class="loader-overlay" id="loaderOverlay">
    <div class="nia-loader-wrap">
        <div class="nia-loader">
            <img class="nia-logo" src="dist/img/nialogo.png" alt="Loading"
                 style="width:74px;height:74px;max-width:74px;max-height:74px;border-radius:50%;object-fit:contain;display:block;">
        </div>
        <div class="nia-loader-label" id="loaderLabel">Authenticating</div>
    </div>
</div>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<div class="page-hero" id="home">
    <div class="login-card">

        <!-- ① BRAND -->
        <div class="panel-left">
            <div class="hex-bg"></div>
            <div class="ring-wrap">
                <div class="ring"></div><div class="ring"></div><div class="ring"></div>
            </div>
            <div class="arc-glow"></div>

            <div class="logo-wrap">
                <div class="logo-ring">
                    <img src="dist/img/nialogo.png" alt="NIA Logo">
                </div>
            </div>
            <h2>NATIONAL IRRIGATION ADMINISTRATION</h2>
            <h3>Albay-Catanduanes IMO</h3>
            <div class="divider"></div>
            <div class="panel-badge">
                <i class="fas fa-shield-alt"></i>
                ACIMO Integrated Management Solution (AIMS)
            </div>
            <div class="quick-links forms-fallback">
                <button type="button" class="panel-badge js-open-forms">
                    <i class="fas fa-file-alt"></i> Forms
                </button>
            </div>
        </div>

        <!-- ② LOGIN FORM -->
        <div class="panel-right">
            <div class="corner-accent"></div>

            <div id="loginFormContainer">
                <div class="form-eyebrow">AIMS Portal</div>
                <div class="form-title">Welcome back</div>
                <div class="form-subtitle">Sign in to your account to continue</div>

                <form action="login.php" method="post" id="loginForm" autocomplete="off">
                    <div class="field-wrap" id="wrap-username">
                        <input type="text" id="username" name="username"
                               placeholder=" "
                               value="<?= htmlspecialchars($username) ?>" required>
                        <label for="username">Username</label>
                        <span class="field-icon"><i class="fas fa-user"></i></span>
                    </div>
                    <div class="field-wrap" id="wrap-password">
                        <input type="password" id="password" name="password"
                               placeholder=" " required>
                        <label for="password">Password</label>
                        <button type="button" class="field-icon" id="passwordToggle" aria-label="Toggle password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="remember-row">
                        <label class="remember-label" for="rememberMe">
                            <input type="checkbox" id="rememberMe">
                            <span class="remember-box">
                                <i class="fas fa-check remember-check"></i>
                            </span>
                            <span class="remember-text">Remember me</span>
                        </label>
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

            <div id="forgotFormContainer" style="display:none">
                <div class="forgot-eyebrow">Account Recovery</div>
                <div class="forgot-title-text">Reset Password</div>
                <p class="forgot-desc">Enter your employee ID number. An administrator will be notified to assist you.</p>
                <form id="forgotPasswordForm" autocomplete="off">
                    <div class="field-wrap">
                        <input type="text" id="id_number" name="id_number"
                               placeholder=" " required>
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

        <!-- ③ INFO PANEL: announcements + help -->
        <div class="panel-info">

            <section class="info-notices" aria-labelledby="noticesTitle">
                <div class="info-header">
                    <h4 class="info-title" id="noticesTitle">
                        <i class="fas fa-bullhorn"></i> Announcements <span class="notice-count" id="noticeCount"></span>
                    </h4>
                    <div class="info-date" id="todayLabel"></div>
                </div>
                <div class="notice-list" id="noticeList" aria-live="polite">
                    <div class="notice-empty">
                        <i class="fas fa-spinner fa-spin"></i> Loading&hellip;
                    </div>
                </div>
            </section>

            <section class="info-help" aria-labelledby="helpTitle">
                <div class="info-header">
                    <h4 class="info-title" id="helpTitle">
                        <i class="fas fa-life-ring"></i> Need help?
                    </h4>
                </div>
                <div class="help-grid">
                    <button type="button" class="help-tile js-open-forms">
                        <i class="fas fa-file-alt"></i>
                        <span class="help-tile-text">
                            <strong>Forms &amp; Documents</strong>
                            <small>Browse and download</small>
                        </span>
                    </button>
                    <button type="button" class="help-tile js-open-forgot">
                        <i class="fas fa-key"></i>
                        <span class="help-tile-text">
                            <strong>Can&rsquo;t sign in?</strong>
                            <small>Request a password reset</small>
                        </span>
                    </button>
                </div>
            </section>

        </div>

    </div>
</div>

<!-- ══════════════════════════════════════
     FORMS MODAL
══════════════════════════════════════ -->
<div class="forms-modal-overlay" id="formsModalOverlay">
    <div class="forms-modal-dialog">
        <div class="forms-modal-header">
            <div class="forms-modal-title">
                <i class="fas fa-file-alt"></i> Forms &amp; Documents
            </div>
            <button type="button" class="forms-modal-close" id="closeFormsModal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <!-- Search bar — login-style -->
        <div class="forms-modal-search">
            <div class="forms-search-wrap">
                <input type="text" id="formsSearchInput" placeholder=" " autocomplete="off">
                <label for="formsSearchInput">Search forms&hellip;</label>
                <i class="fas fa-search forms-search-icon"></i>
            </div>
            <div class="forms-count-badge" id="formsCountBadge">&mdash;</div>
        </div>
        <div class="forms-modal-body" id="formsModalContent">
            <!-- AJAX -->
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {

    /* ── Theme ─────────────────────────── */
    var $html = $('html');
    var SKEY = 'nia-theme';
    $html.attr('data-theme', localStorage.getItem(SKEY) || 'dark');
    $('#themeToggle').on('click', function () {
        var next = $html.attr('data-theme') === 'dark' ? 'light' : 'dark';
        $html.attr('data-theme', next);
        localStorage.setItem(SKEY, next);
    });

    /* ── Today label ───────────────────── */
    var MO = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var N  = new Date();
    $('#todayLabel').text(MO[N.getMonth()] + ' ' + N.getDate() + ', ' + N.getFullYear());

    /* ── SweetAlert helpers ────────────── */
    function isDark() { return $html.attr('data-theme') === 'dark'; }
    function sOpts(x) {
        return $.extend({
            position:'center', confirmButtonColor:'#0f7a4e',
            background: isDark() ? '#0f2d1e' : '#fff',
            color:      isDark() ? '#e0f7ec' : '#082616'
        }, x);
    }
    function sErr(m)  { Swal.fire(sOpts({ icon:'error',   title:'Login Failed', text:m, confirmButtonText:'Try Again' })); }
    function sWarn(m) { Swal.fire(sOpts({ icon:'warning', title:'Hold on!',      text:m, confirmButtonText:'OK' })); }

    function shake(id) {
        var $e = $(id);
        $e.addClass('field-error');
        $e.find('input').one('input', function () { $e.removeClass('field-error'); });
        setTimeout(function () { $e.removeClass('field-error'); }, 700);
    }

    /* ── PHP bridges ───────────────────── */
    if ($('#phpError').length)   { shake('#wrap-username'); shake('#wrap-password'); sErr($('#phpError').data('msg')); }
    if ($('#phpSuccess').length) {
        Swal.fire(sOpts({ icon:'success', title:'Login Successful!', showConfirmButton:false, timer:1500 }))
            .then(function () { window.location.href = $('#phpSuccess').data('redirect'); });
    }

    /* ── Password toggle ───────────────── */
    $('#passwordToggle').on('click', function () {
        var inp = $('#password'), ic = $(this).find('i');
        inp.attr('type') === 'password'
            ? inp.attr('type','text').end()     && ic.removeClass('fa-eye').addClass('fa-eye-slash')
            : inp.attr('type','password').end() && ic.removeClass('fa-eye-slash').addClass('fa-eye');
    });

    /* ── Remember Me — restore on load ────────────────── */
    var RKEY = 'nia-remember-user';
    var savedUser = localStorage.getItem(RKEY);
    if (savedUser) {
        $('#username').val(savedUser);
        $('#rememberMe').prop('checked', true);
        /* trigger float-label so it stays raised */
        $('#username').trigger('input');
    }

    /* ── Login submit ──────────────────── */
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        var u = $('#username').val().trim(), p = $('#password').val();
        if (!u) { shake('#wrap-username'); sWarn('Please enter your username.'); return; }
        if (!p) { shake('#wrap-password'); sWarn('Please enter your password.'); return; }

        /* Save or clear remembered username */
        if ($('#rememberMe').is(':checked')) {
            localStorage.setItem(RKEY, u);
        } else {
            localStorage.removeItem(RKEY);
        }

        $('#loaderLabel').text('Authenticating');
        $('#loaderOverlay').addClass('active');
        var f = this;
        setTimeout(function () { f.submit(); }, 3000);
    });

    /* ── Forgot / back ─────────────────── */
    $('#forgotPasswordLink').on('click', function () {
        $('#loginFormContainer').fadeOut(260, function () { $('#forgotFormContainer').fadeIn(260); });
    });
    $('#backToLoginLink').on('click', function () {
        $('#forgotFormContainer').fadeOut(260, function () { $('#loginFormContainer').fadeIn(260); });
    });

    /* ── Forgot AJAX ───────────────────── */
    $('#forgotPasswordForm').on('submit', function (e) {
        e.preventDefault();
        var id = $('#id_number').val().trim();
        if (!id) { sWarn('Please enter your ID number.'); return; }
        $('#loaderLabel').text('Sending request');
        $('#loaderOverlay').addClass('active');
        $.ajax({
            url:'ajax_forgot_password.php', type:'POST', data:{ id_number:id },
            success: function (r) {
                $('#loaderOverlay').removeClass('active');
                if (r.success) {
                    Swal.fire(sOpts({ icon:'success', title:r.message||'Request sent!', showConfirmButton:false, timer:1500 }))
                        .then(function () {
                            $('#forgotPasswordForm')[0].reset();
                            $('#forgotFormContainer').fadeOut(260, function () { $('#loginFormContainer').fadeIn(260); });
                        });
                } else { Swal.fire(sOpts({ icon:'error', title:'Not Found', text:r.message, confirmButtonText:'OK' })); }
            },
            error: function () { $('#loaderOverlay').removeClass('active'); sErr('Network error. Please try again.'); }
        });
    });

    /* ══════════════════════════════════════
       FORMS MODAL
    ══════════════════════════════════════ */
    function showForms() {
        $('#formsModalContent').html(
            '<div style="text-align:center;padding:32px 0;">'
            + '<i class="fas fa-spinner fa-spin fa-2x" style="color:var(--green);"></i>'
            + '<p style="margin-top:12px;font-size:.85rem;color:var(--fm-body-text);">Loading forms&hellip;</p>'
            + '</div>'
        );
        $('#formsCountBadge').text('—');
        $('#formsSearchInput').val('');
        $('#formsModalOverlay').addClass('show');
        $('body').css('overflow','hidden');
        $.ajax({
            url:'views/get_forms.php', type:'GET', dataType:'html',
            success: function (html) {
                $('#formsModalContent').html(html);
                /* Count .form-card-compact items and update badge */
                var total = $('#formsModalContent').find('.form-card-compact').length;
                $('#formsCountBadge').text(total ? total + ' form' + (total !== 1 ? 's' : '') : '0 forms');
            },
            error: function () {
                $('#formsModalContent').html(
                    '<div style="text-align:center;padding:32px 0;">'
                    + '<i class="fas fa-exclamation-circle fa-2x" style="color:#e74c3c;"></i>'
                    + '<p style="margin-top:10px;font-size:.85rem;color:var(--fm-body-text);">Failed to load. <button onclick="showForms()" style="background:none;border:none;color:var(--green);cursor:pointer;font-weight:600;">Retry</button></p>'
                    + '</div>'
                );
                $('#formsCountBadge').text('—');
            }
        });
    }

    /* Live search filter — targets get_forms.php cards */
    $('#formsSearchInput').on('input', function() {
        var q = $(this).val().toLowerCase().trim();
        var $items = $('#formsModalContent').find('.form-card-compact');
        if (!$items.length) return;
        var visible = 0;
        if (!q) {
            $items.show();
            visible = $items.length;
        } else {
            $items.each(function() {
                var name = ($(this).data('form-name') || '').toLowerCase();
                var desc = ($(this).data('form-desc') || '').toLowerCase();
                var show = name.indexOf(q) !== -1 || desc.indexOf(q) !== -1;
                $(this).toggle(show);
                if (show) visible++;
            });
        }
        $('#formsCountBadge').text(visible + ' form' + (visible !== 1 ? 's' : ''));
        $('#modal-no-results').toggle(visible === 0 && q !== '');
    });
    function hideForms() {
        $('#formsModalOverlay').removeClass('show');
        $('body').css('overflow','');
    }
    window.showForms = showForms;   /* needed by the inline Retry button */
    $('.js-open-forms').on('click', showForms);
    $('.js-open-forgot').on('click', function () { $('#forgotPasswordLink').trigger('click'); });
    $('#closeFormsModal').on('click', hideForms);
    $('#formsModalOverlay').on('click', function (e) { if ($(e.target).is(this)) hideForms(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') hideForms(); });

    /* ══════════════════════════════════════
       ANNOUNCEMENTS
       Public-safe only. All text is inserted with
       .text() so titles/bodies can never run script.
    ══════════════════════════════════════ */
    var CAT = {
        advisory:    { label:'Advisory',    color:'#f59e0b' },
        maintenance: { label:'Maintenance', color:'#06b6d4' },
        holiday:     { label:'Holiday',     color:'#ec4899' },
        policy:      { label:'Policy',      color:'#8b5cf6' },
        general:     { label:'General',     color:'#24e78f' }
    };
    function fmtDate(str) {
        var d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return '';
        return MO[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }
    function noticeEmpty(icon, msg) {
        return $('<div class="notice-empty"></div>')
            .append($('<i class="fas ' + icon + '"></i>')).append(document.createTextNode(' ' + msg));
    }

    /* show the bottom fade only while there is more to scroll */
    function updateFade() {
        var el = document.getElementById('noticeList');
        if (!el) return;
        el.classList.toggle('has-more', el.scrollHeight - el.scrollTop - el.clientHeight > 8);
    }
    $('#noticeList').on('scroll', updateFade);
    $(window).on('resize', updateFade);

    function renderNotices(items) {
        var $list = $('#noticeList').empty();
        $('#noticeCount').text(items.length ? '(' + items.length + ')' : '');

        if (!items.length) {
            $list.append(noticeEmpty('fa-check-circle', 'No announcements right now.'));
            updateFade();
            return;
        }

        items.forEach(function (n) {
            var cat = CAT[n.category] || CAT.general;
            var $item = $('<button type="button" class="notice-item"></button>')
                .css('--notice-color', cat.color);

            var $meta = $('<div class="notice-meta"></div>');
            if (+n.is_pinned) $meta.append('<i class="fas fa-thumbtack notice-pin" title="Pinned"></i>');
            $meta.append($('<span class="notice-cat"></span>').text(cat.label));
            $meta.append($('<span class="notice-when"></span>').text(fmtDate(n.posted_at)));

            $item.append($meta)
                 .append($('<div class="notice-title"></div>').text(n.title))
                 .append($('<div class="notice-excerpt"></div>').text(n.body));

            $item.on('click', function () {
                Swal.fire(sOpts({
                    title: n.title,
                    html:  $('<div style="text-align:left;white-space:pre-line;font-size:.92rem;line-height:1.6;"></div>').text(n.body)[0].outerHTML,
                    footer: $('<span></span>').text(cat.label + ' · ' + fmtDate(n.posted_at))[0].outerHTML,
                    confirmButtonText: 'Close'
                }));
            });
            $list.append($item);
        });

        updateFade();
        setTimeout(updateFade, 300);   /* re-check after web fonts settle */
    }

    function loadNotices() {
        /* panel is display:none below 1100px — skip the request there */
        if (!document.querySelector('.panel-info').offsetParent) return;
        $.ajax({ url:'includes/ajax_announcements.php', type:'GET', dataType:'json' })
            .done(function (res) { renderNotices(res && res.success && Array.isArray(res.data) ? res.data : []); })
            .fail(function (xhr) {
                /* open DevTools > Console to see why */
                console.error('[announcements] HTTP ' + xhr.status, (xhr.responseText || '').slice(0, 300));
                var why = xhr.status === 200 ? 'bad response' : (xhr.status ? 'error ' + xhr.status : 'no connection');
                $('#noticeList').empty().append(noticeEmpty('fa-exclamation-circle', 'Announcements are unavailable (' + why + ').'));
            });
    }
    loadNotices();

});
</script>
</body>
</html>
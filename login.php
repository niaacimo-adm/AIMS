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
    <link rel="stylesheet" href="plugins/fullcalendar/main.css">
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
            <h2>National Irrigation Administration</h2>
            <h3>Albay-Catanduanes IMO</h3>
            <div class="divider"></div>
            <div class="panel-badge">
                <i class="fas fa-shield-alt"></i>
                ACIMO Integrated Management Solution (AIMS)
            </div>
            <div class="quick-links">
                <button type="button" class="panel-badge" id="formsModalTrigger">
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

        <!-- ③ CALENDAR PANEL -->
        <div class="panel-calendar">
            <div class="cal-header">
                <div class="cal-header-title">
                    <i class="fas fa-calendar-alt"></i> Events Calendar
                </div>
                <div class="cal-header-date" id="todayLabel"></div>
            </div>

            <div id="inCardCalendar"></div>

            <div class="upcoming-strip">
                <div class="upcoming-strip-title">Upcoming Events</div>
                <div id="upcomingList">
                    <div style="font-size:.74rem;color:var(--upcoming-muted);padding:5px 0;">
                        <i class="fas fa-spinner fa-spin" style="font-size:.7rem;margin-right:5px;"></i>Loading…
                    </div>
                </div>
            </div>
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
<script src="plugins/fullcalendar/main.js"></script>
<script src="plugins/moment/moment.min.js"></script>

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
        /* destroy & rebuild calendar so FullCalendar picks up new CSS vars */
        if (inCardCal) {
            inCardCal.destroy();
            inCardCal = null;
        }
        /* small delay lets CSS variable transition settle before render */
        setTimeout(function() { initCalendar(); }, 50);
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
    $('#formsModalTrigger').on('click', showForms);
    $('#closeFormsModal').on('click', hideForms);
    $('#formsModalOverlay').on('click', function (e) { if ($(e.target).is(this)) hideForms(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') hideForms(); });

    /* ══════════════════════════════════════
       EVENT COLOUR HELPER
    ══════════════════════════════════════ */
    function evtColor(type) {
        var m = { holiday:'#f59e0b', meeting:'#06b6d4', birthday:'#ec4899' };
        return m[type] || '#6366f1';
    }

    /* ══════════════════════════════════════
       NORMALISE EVENT RESPONSE
       Handles: array, {data:[]}, {success,data:[]},
                {events:[]}, {success,events:[]}
    ══════════════════════════════════════ */
    function extractEvents(res) {
        if (!res) return [];
        if (Array.isArray(res)) return res;
        if (Array.isArray(res.data))   return res.data;
        if (Array.isArray(res.events)) return res.events;
        return [];
    }

    /* ══════════════════════════════════════
       IN-CARD CALENDAR
    ══════════════════════════════════════ */
    var inCardCal = null;

    function initCalendar() {
        var el = document.getElementById('inCardCalendar');
        if (!el) return;

        inCardCal = new FullCalendar.Calendar(el, {
            initialView:   'dayGridMonth',
            headerToolbar: { left:'prev,next', center:'title', right:'' },
            height:        'auto',
            fixedWeekCount:false,
            dayMaxEvents:  2,
            eventDisplay:  'block',

            /* ── Fetch events ── */
            events: function (info, ok, fail) {
                $.ajax({
                    url:      'views/get_events.php',
                    type:     'GET',
                    dataType: 'json',
                    success: function (res) {
                        var raw = extractEvents(res);
                        if (raw.length) {
                            ok(raw.map(function (ev) {
                                return {
                                    id:              ev.id,
                                    title:           ev.title,
                                    start:           ev.start,
                                    end:             ev.end   || null,
                                    description:     ev.description || '',
                                    type:            ev.type  || 'event',
                                    backgroundColor: evtColor(ev.type),
                                    borderColor:     evtColor(ev.type),
                                    textColor:       '#ffffff'
                                };
                            }));
                            buildUpcoming(raw);
                        } else {
                            ok([]);
                            buildUpcoming([]);
                        }
                    },
                    error: function (xhr, st, err) {
                        console.warn('[Calendar] fetch error:', err, xhr.responseText);
                        fail(err);
                        buildUpcoming([]);
                    }
                });
            },

            eventClick: function (info) {
                Swal.fire(sOpts({
                    title:           info.event.title,
                    icon:            'info',
                    confirmButtonText:'Close',
                    html:
                        '<p style="margin:4px 0"><strong>Type:</strong> ' + (info.event.extendedProps.type || '—') + '</p>'
                      + '<p style="margin:4px 0"><strong>Description:</strong> ' + (info.event.extendedProps.description || 'No description') + '</p>'
                      + '<p style="margin:4px 0"><strong>Start:</strong> ' + moment(info.event.start).format('MMMM D, YYYY h:mm A') + '</p>'
                      + (info.event.end ? '<p style="margin:4px 0"><strong>End:</strong> ' + moment(info.event.end).format('MMMM D, YYYY h:mm A') + '</p>' : '')
                }));
            }
        });

        inCardCal.render();
    }

    /* ── Upcoming events list ── */
    function buildUpcoming(events) {
        var today = new Date(); today.setHours(0,0,0,0);
        var list  = events
            .filter(function (e) { return new Date(e.start) >= today; })
            .sort(function (a,b) { return new Date(a.start) - new Date(b.start); })
            .slice(0, 4);

        if (!list.length) {
            $('#upcomingList').html('<div style="font-size:.74rem;color:var(--upcoming-muted);padding:5px 0;">No upcoming events.</div>');
            return;
        }
        var h = '';
        list.forEach(function (ev) {
            h += '<div class="upcoming-item">'
               + '<div class="upcoming-dot" style="background:' + evtColor(ev.type) + '"></div>'
               + '<div class="upcoming-text">' + ev.title + '</div>'
               + '<div class="upcoming-date">' + moment(ev.start).format('MMM D') + '</div>'
               + '</div>';
        });
        $('#upcomingList').html(h);
    }

    /* init */
    initCalendar();

});
</script>
</body>
</html>
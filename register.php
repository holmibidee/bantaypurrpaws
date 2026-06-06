<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/notifications.php';
startSession();

// Handle "Start over" action: clear pending registration state
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'start_over') {
    unset($_SESSION['reg_pending'], $_SESSION['reg_step']);
    $step = 'form';
}

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$step  = $_SESSION['reg_step'] ?? 'form';
$error = '';
$info  = '';

// ── STEP 1: Send OTP to email ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email']     ?? '');

    if (empty($fullName) || empty($email)) {
        echo json_encode(['ok' => false, 'message' => 'Please fill out all fields.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (emailExists($email)) {
        echo json_encode(['ok' => false, 'message' => 'An account with that email already exists.']);
        exit;
    }

    $_SESSION['reg_pending_phase1'] = ['full_name' => $fullName, 'email' => $email];

    $result = issueAndSendOtp($email, $fullName, 'registration');
    if ($result === true) {
        echo json_encode(['ok' => true, 'message' => "A 6-digit code was sent to {$email}."]);
    } else {
        echo json_encode(['ok' => false, 'message' => is_string($result) ? $result : 'Could not send OTP. Please try again.']);
    }
    exit;
}

// ── STEP 1b: Verify OTP for email (AJAX) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_email_otp') {
    $code    = trim($_POST['otp_code'] ?? '');
    $pending = $_SESSION['reg_pending_phase1'] ?? null;

    if (!$pending) {
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    $status = verifyOtp($pending['email'], $code, 'registration');
    if ($status === 'valid') {
        $_SESSION['reg_email_verified'] = true;
        echo json_encode(['ok' => true]);
    } elseif ($status === 'expired') {
        echo json_encode(['ok' => false, 'message' => 'OTP has expired. Please request a new one.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Invalid code. Please check and try again.']);
    }
    exit;
}

// ── STEP 2: Resend OTP ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_otp') {
    $pending = $_SESSION['reg_pending_phase1'] ?? null;
    if ($pending) {
        $result = issueAndSendOtp($pending['email'], $pending['full_name'], 'registration');
        echo json_encode(['ok' => $result === true, 'message' => $result === true ? 'New OTP sent.' : (is_string($result) ? $result : 'Could not resend.')]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Session expired.']);
    }
    exit;
}

// ── STEP 3: Complete registration (Phase 2) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_register') {
    $pending  = $_SESSION['reg_pending_phase1'] ?? null;
    $verified = $_SESSION['reg_email_verified'] ?? false;

    if (!$pending || !$verified) {
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    $password   = $_POST['password']         ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';
    $agreed     = !empty($_POST['agree_terms']);

    if (!$agreed) {
        echo json_encode(['ok' => false, 'field' => 'agree', 'message' => 'You must agree to the Terms & Conditions.']);
        exit;
    }
    if ($password !== $confirmPwd) {
        echo json_encode(['ok' => false, 'field' => 'confirm_password', 'message' => 'Passwords do not match.']);
        exit;
    }

    $pwErrors = [];
    if (strlen($password) < 12)                                            $pwErrors[] = 'at least 12 characters';
    if (!preg_match('/[A-Z]/', $password))                                 $pwErrors[] = 'an uppercase letter (A-Z)';
    if (!preg_match('/[a-z]/', $password))                                 $pwErrors[] = 'a lowercase letter (a-z)';
    if (!preg_match('/[0-9]/', $password))                                 $pwErrors[] = 'a number (0-9)';
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/', $password))  $pwErrors[] = 'a special character';

    if (!empty($pwErrors)) {
        echo json_encode(['ok' => false, 'field' => 'password', 'message' => 'Password must contain: ' . implode(', ', $pwErrors) . '.']);
        exit;
    }

    $userId = createUser($pending['full_name'], $pending['email'], password_hash($password, PASSWORD_DEFAULT));

    if ($userId) {
        unset($_SESSION['reg_pending_phase1'], $_SESSION['reg_email_verified']);

        require_once __DIR__ . '/includes/users.php';
        $newUser = getUserById($userId);
        if ($newUser) {
            refreshUserSession($newUser);
        }

        createSystemNotification('system', 'Welcome to BantayPurrPaws! Your email was verified.', null, null, $userId);

        flash('success', 'Welcome! Your account has been created.');
        echo json_encode(['ok' => true, 'redirect' => url('dashboard.php')]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Could not create account. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <style>
        /* ── Two-phase slider ── */
        .register-slider-wrap {
            overflow: hidden;
            width: 100%;
        }
        .register-slider {
            display: flex;
            width: 200%;
            transition: transform 0.45s cubic-bezier(0.65, 0, 0.35, 1);
        }
        .register-slider.phase-2 {
            transform: translateX(-50%);
        }
        .register-phase {
            width: 50%;
            flex-shrink: 0;
        }

        /* ── OTP input boxes ── */
        .otp-inputs { display:flex; gap:10px; justify-content:center; margin:16px 0; }
        .otp-inputs input {
            width:46px; height:54px; text-align:center; font-size:22px; font-weight:700;
            border:2px solid var(--border); border-radius:8px;
            background:var(--surface-1); color:var(--text-primary);
            transition: border-color .2s, box-shadow .2s;
        }
        .otp-inputs input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(124,111,91,.15); }
        .otp-inputs input.otp-verified { border-color:#22c55e; background:rgba(34,197,94,.06); }

        /* ── Status / feedback ── */
        .field-error { color:#ef4444; font-size:.78rem; margin-top:5px; display:none; }
        .field-error.visible { display:block; }
        .otp-status { font-size:.82rem; text-align:center; margin-top:6px; min-height:18px; }
        .otp-status.ok  { color:#22c55e; }
        .otp-status.err { color:#ef4444; }

        /* ── Email verified badge ── */
        .email-verified-badge {
            display:none; align-items:center; gap:6px;
            font-size:.78rem; font-weight:600; color:#16a34a;
            background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3);
            border-radius:6px; padding:5px 10px; margin-top:6px;
        }
        .email-verified-badge.visible { display:flex; }

        /* ── Next button disabled state ── */
        #btnNext:disabled { opacity:.45; cursor:not-allowed; }

        /* ── Send OTP button spinner ── */
        .btn-sending { opacity:.7; pointer-events:none; }

        /* ── Phase 2 back link ── */
        .back-link { font-size:.82rem; color:var(--text-muted); cursor:pointer; display:inline-flex; align-items:center; gap:4px; margin-bottom:12px; background:none; border:none; padding:0; }
        .back-link:hover { color:var(--text-primary); }

        /* ── Agree checkbox vertical center ── */
        .agree-row { display:flex; align-items:center; gap:10px; margin-top:4px; }
        .agree-row input[type=checkbox] { margin:0; accent-color:var(--accent); width:16px; height:16px; flex-shrink:0; }
        .agree-row label { font-size:.875rem; color:var(--text-secondary); line-height:1.5; cursor:pointer; }

        /* ── Resend row ── */
        .resend-row { text-align:center; margin-top:10px; font-size:.8rem; color:var(--text-muted); }
        .resend-row button { background:none; border:none; color:var(--primary); font-size:.8rem; cursor:pointer; text-decoration:underline; padding:0; }
        .resend-row button:disabled { opacity:.5; cursor:not-allowed; }

        /* Step indicator */
        .step-indicator { display:flex; align-items:center; gap:8px; justify-content:center; margin-bottom:20px; }
        .step-dot { width:8px; height:8px; border-radius:50%; background:var(--border); transition:background .3s; }
        .step-dot.active { background:var(--accent); }
        .step-dot.done { background:#22c55e; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <a href="<?= url('index.php') ?>" style="display:inline-block;margin-bottom:8px;">
                <img src="<?= url('assets/logo.png') ?>" alt="Bantay PurrPaws" class="auth-logo-img">
            </a>
            <h1 id="regTitle">Create Account</h1>
            <p id="regSubtitle">Join BantayPurrPaws and help save animals</p>
        </div>

        <!-- Step indicator -->
        <div class="step-indicator">
            <div class="step-dot active" id="dot1"></div>
            <div style="width:32px;height:2px;background:var(--border);border-radius:2px;"></div>
            <div class="step-dot" id="dot2"></div>
        </div>

        <div class="register-slider-wrap">
            <div class="register-slider" id="regSlider">

                <!-- ══ PHASE 1: Name, Email, OTP ══ -->
                <div class="register-phase" id="phase1">

                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
                        <input type="text" id="full_name" class="form-control"
                               placeholder="Juan dela Cruz" autocomplete="name">
                        <div class="field-error" id="err_full_name"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                        <div style="display:flex;gap:8px;align-items:flex-start;">
                            <input type="email" id="email" class="form-control"
                                   placeholder="you@example.com" autocomplete="email"
                                   style="flex:1;">
                            <button type="button" id="btnSendOtp" class="btn btn-accent"
                                    style="white-space:nowrap;flex-shrink:0;padding:9px 14px;font-size:.8rem;">
                                Verify Email
                            </button>
                        </div>
                        <div class="field-error" id="err_email"></div>
                        <div class="email-verified-badge" id="emailVerifiedBadge">
                            ✓ Email verified
                        </div>
                    </div>

                    <!-- OTP field (hidden until email sent) -->
                    <div class="form-group" id="otpSection" style="display:none;">
                        <label class="form-label">Verification Code <span class="req">*</span></label>
                        <div class="otp-inputs" id="otpBoxes">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" autocomplete="one-time-code">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit">
                            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit">
                        </div>
                        <div class="otp-status" id="otpStatus"></div>
                        <div class="resend-row">
                            Didn't receive the code?
                            <button type="button" id="btnResend" disabled>Resend OTP</button>
                            <span id="resendTimer"></span>
                        </div>
                    </div>

                    <button type="button" id="btnNext" class="btn btn-primary w-full" style="justify-content:center;padding:11px;margin-top:8px;" disabled>
                        Next →
                    </button>

                    <div class="auth-footer">
                        Already have an account? <a href="<?= url('login.php') ?>">Sign in</a>
                    </div>
                </div>

                <!-- ══ PHASE 2: Password ══ -->
                <div class="register-phase" id="phase2">

                    <button type="button" class="back-link" id="btnBack">← Back</button>

                    <div class="form-group" id="pw-field-group">
                        <label class="form-label" for="password">Password <span class="req">*</span></label>
                        <input type="password" id="password" class="form-control"
                               placeholder="Min. 12 characters" autocomplete="new-password">
                        <div class="field-error" id="err_password"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
                        <input type="password" id="confirm_password" class="form-control"
                               placeholder="Repeat password" autocomplete="new-password">
                        <div class="field-error" id="err_confirm_password"></div>
                    </div>

                    <div class="agree-row" style="margin-top:12px;">
                        <input type="checkbox" id="agree_terms">
                        <label for="agree_terms">
                            I have read and agree to the
                            <a href="<?= url('terms.php') ?>" target="_blank" style="color:var(--accent);text-decoration:underline;">
                                Terms &amp; Conditions
                            </a>
                            of BantayPurrPaws.
                        </label>
                    </div>
                    <div class="field-error" id="err_agree" style="margin-top:4px;"></div>

                    <button type="button" id="btnFinish" class="btn btn-primary w-full" style="justify-content:center;padding:11px;margin-top:16px;">
                        Finish Signing Up
                    </button>

                    <div class="field-error" id="err_finish" style="margin-top:8px;text-align:center;font-size:.83rem;"></div>

                </div>
                <!-- end phase2 -->

            </div><!-- end slider -->
        </div><!-- end wrap -->
    </div>
</div>

<script src="<?= url('js/pw-toggle.js') ?>"></script>
<script src="<?= url('js/password-strength.js') ?>"></script>
<script>
(function () {
    'use strict';

    // ── Element refs ──────────────────────────────────────
    const slider      = document.getElementById('regSlider');
    const btnSendOtp  = document.getElementById('btnSendOtp');
    const btnResend   = document.getElementById('btnResend');
    const btnNext     = document.getElementById('btnNext');
    const btnBack     = document.getElementById('btnBack');
    const btnFinish   = document.getElementById('btnFinish');
    const otpSection  = document.getElementById('otpSection');
    const otpBoxes    = document.getElementById('otpBoxes');
    const otpStatus   = document.getElementById('otpStatus');
    const emailVerifiedBadge = document.getElementById('emailVerifiedBadge');
    const dot1 = document.getElementById('dot1');
    const dot2 = document.getElementById('dot2');

    let emailVerified = false;
    let resendInterval = null;

    // ── Helpers ───────────────────────────────────────────
    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.classList.toggle('visible', !!msg);
    }

    function getOtpValue() {
        return Array.from(document.querySelectorAll('.otp-digit')).map(d => d.value).join('');
    }

    // ── OTP digit navigation ──────────────────────────────
    document.querySelectorAll('.otp-digit').forEach((el, i, all) => {
        el.addEventListener('input', () => {
            el.value = el.value.replace(/\D/, '');
            if (el.value && i < all.length - 1) all[i + 1].focus();
            if (getOtpValue().length === 6) verifyEmailOtp();
        });
        el.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !el.value && i > 0) all[i - 1].focus();
        });
        el.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((ch, j) => { if (all[j]) all[j].value = ch; });
            if (pasted.length === 6) verifyEmailOtp();
        });
    });

    // ── Send OTP ──────────────────────────────────────────
    btnSendOtp.addEventListener('click', async () => {
        const name  = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        showErr('err_full_name', '');
        showErr('err_email', '');

        if (!name) { showErr('err_full_name', 'Please enter your full name.'); return; }
        if (!email) { showErr('err_email', 'Please enter your email address.'); return; }

        btnSendOtp.classList.add('btn-sending');
        btnSendOtp.textContent = 'Sending…';

        try {
            const fd = new FormData();
            fd.append('action', 'send_otp');
            fd.append('full_name', name);
            fd.append('email', email);
            const res  = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                otpSection.style.display = 'block';
                document.querySelector('.otp-digit').focus();
                otpStatus.textContent = data.message;
                otpStatus.className   = 'otp-status ok';
                btnSendOtp.textContent = 'Resend Code';
                document.getElementById('email').readOnly = true;
                document.getElementById('full_name').readOnly = true;
                startResendTimer();
            } else {
                showErr('err_email', data.message);
            }
        } catch(e) {
            showErr('err_email', 'Network error. Please try again.');
        }

        btnSendOtp.classList.remove('btn-sending');
        if (!btnSendOtp.textContent.includes('Resend')) btnSendOtp.textContent = 'Verify Email';
    });

    // ── Verify OTP automatically ──────────────────────────
    async function verifyEmailOtp() {
        const code = getOtpValue();
        if (code.length < 6) return;

        otpStatus.textContent = 'Verifying…';
        otpStatus.className   = 'otp-status';

        const fd = new FormData();
        fd.append('action', 'verify_email_otp');
        fd.append('otp_code', code);

        try {
            const res  = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                emailVerified = true;
                otpStatus.textContent = '✓ Email verified!';
                otpStatus.className   = 'otp-status ok';
                document.querySelectorAll('.otp-digit').forEach(d => {
                    d.classList.add('otp-verified');
                    d.readOnly = true;
                });
                emailVerifiedBadge.classList.add('visible');
                btnNext.disabled = false;
            } else {
                otpStatus.textContent = data.message || 'Invalid code.';
                otpStatus.className   = 'otp-status err';
                // Shake effect on otp boxes
                otpBoxes.style.animation = 'none';
                otpBoxes.offsetHeight;
                otpBoxes.style.animation = 'shake .35s ease';
                // Clear digits
                document.querySelectorAll('.otp-digit').forEach(d => { d.value = ''; });
                document.querySelector('.otp-digit').focus();
            }
        } catch(e) {
            otpStatus.textContent = 'Network error.';
            otpStatus.className   = 'otp-status err';
        }
    }

    // ── Resend timer ─────────────────────────────────────
    function startResendTimer() {
        let secs = 60;
        btnResend.disabled = true;
        const timerEl = document.getElementById('resendTimer');
        clearInterval(resendInterval);
        resendInterval = setInterval(() => {
            secs--;
            timerEl.textContent = '(' + secs + 's)';
            if (secs <= 0) {
                clearInterval(resendInterval);
                btnResend.disabled = false;
                timerEl.textContent = '';
            }
        }, 1000);
    }

    btnResend.addEventListener('click', async () => {
        btnResend.disabled = true;
        const fd = new FormData();
        fd.append('action', 'resend_otp');
        try {
            const res  = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            otpStatus.textContent = data.message;
            otpStatus.className   = 'otp-status ' + (data.ok ? 'ok' : 'err');
            if (data.ok) {
                // clear digits
                document.querySelectorAll('.otp-digit').forEach(d => { d.value = ''; d.readOnly = false; d.classList.remove('otp-verified'); });
                emailVerified = false;
                btnNext.disabled = true;
                emailVerifiedBadge.classList.remove('visible');
                startResendTimer();
            }
        } catch(e) {
            otpStatus.textContent = 'Network error.';
            otpStatus.className   = 'otp-status err';
            btnResend.disabled = false;
        }
    });

    // ── Next (slide to phase 2) ───────────────────────────
    btnNext.addEventListener('click', () => {
        if (!emailVerified) return;
        slider.classList.add('phase-2');
        document.getElementById('regTitle').textContent     = 'Set Your Password';
        document.getElementById('regSubtitle').textContent  = 'Almost there! Choose a secure password.';
        dot1.classList.remove('active'); dot1.classList.add('done');
        dot2.classList.add('active');
        // init password strength widget
        initPasswordStrength('password', 'confirm_password', 'pw-field-group');
    });

    // ── Back ──────────────────────────────────────────────
    btnBack.addEventListener('click', () => {
        slider.classList.remove('phase-2');
        document.getElementById('regTitle').textContent    = 'Create Account';
        document.getElementById('regSubtitle').textContent = 'Join BantayPurrPaws and help save animals';
        dot1.classList.add('active'); dot1.classList.remove('done');
        dot2.classList.remove('active');
    });

    // ── Finish Signing Up ────────────────────────────────
    btnFinish.addEventListener('click', async () => {
        const password  = document.getElementById('password').value;
        const confirm   = document.getElementById('confirm_password').value;
        const agreed    = document.getElementById('agree_terms').checked;

        // Clear previous errors
        ['err_password', 'err_confirm_password', 'err_agree', 'err_finish'].forEach(id => showErr(id, ''));

        let hasError = false;

        if (!password) { showErr('err_password', 'Please enter a password.'); hasError = true; }
        if (!confirm)  { showErr('err_confirm_password', 'Please confirm your password.'); hasError = true; }
        if (password && confirm && password !== confirm) {
            showErr('err_confirm_password', 'Passwords do not match.');
            document.getElementById('confirm_password').style.borderColor = '#ef4444';
            hasError = true;
        }
        if (!agreed) { showErr('err_agree', 'You must agree to the Terms & Conditions.'); hasError = true; }

        if (hasError) return;

        btnFinish.disabled = true;
        btnFinish.textContent = 'Creating account…';

        const fd = new FormData();
        fd.append('action', 'complete_register');
        fd.append('password', password);
        fd.append('confirm_password', confirm);
        fd.append('agree_terms', '1');

        try {
            const res  = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                window.location.href = data.redirect;
            } else {
                if (data.field === 'confirm_password') {
                    showErr('err_confirm_password', data.message);
                    document.getElementById('confirm_password').style.borderColor = '#ef4444';
                } else if (data.field === 'password') {
                    showErr('err_password', data.message);
                } else if (data.field === 'agree') {
                    showErr('err_agree', data.message);
                } else {
                    showErr('err_finish', data.message);
                }
                btnFinish.disabled = false;
                btnFinish.textContent = 'Finish Signing Up';
            }
        } catch(e) {
            showErr('err_finish', 'Network error. Please try again.');
            btnFinish.disabled = false;
            btnFinish.textContent = 'Finish Signing Up';
        }
    });

    // ── Shake animation ───────────────────────────────────
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%,100% { transform:translateX(0); }
            20%,60%  { transform:translateX(-6px); }
            40%,80%  { transform:translateX(6px); }
        }
    `;
    document.head.appendChild(style);

})();
</script>
</body>
</html>

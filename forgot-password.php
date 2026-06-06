<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/sensitive-data.php';
require_once __DIR__ . '/includes/mailer.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

// ── AJAX: Check email exists ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'check_email') {
    header('Content-Type: application/json');
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => "Looks like your email doesn't belong to any account."]);
        exit;
    }
    $user = findUserByEmail($email);
    if (!$user || ($user['auth_provider'] ?? 'local') !== 'local') {
        echo json_encode(['ok' => false, 'message' => "Looks like your email doesn't belong to any account."]);
        exit;
    }
    // Send OTP
    $result = issueAndSendOtp($email, $user['full_name'] ?? '', 'password_reset');
    if ($result === true) {
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_name']  = $user['full_name'] ?? '';
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'message' => is_string($result) ? $result : 'Could not send reset code. Please try again.']);
    }
    exit;
}

// ── AJAX: Verify OTP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'verify_otp') {
    header('Content-Type: application/json');
    $email = $_SESSION['reset_email'] ?? '';
    $code  = trim($_POST['otp_code'] ?? '');
    if (!$email) {
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    $status = verifyOtp($email, $code, 'password_reset');
    if ($status === 'valid') {
        $_SESSION['reset_otp_verified'] = true;
        echo json_encode(['ok' => true]);
    } elseif ($status === 'expired') {
        echo json_encode(['ok' => false, 'expired' => true, 'message' => 'OTP has expired. Please request a new one.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Invalid code. Please check and try again.']);
    }
    exit;
}

// ── AJAX: Resend OTP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'resend_otp') {
    header('Content-Type: application/json');
    $email = $_SESSION['reset_email'] ?? '';
    $name  = $_SESSION['reset_name']  ?? '';
    if (!$email) {
        echo json_encode(['ok' => false, 'message' => 'Session expired.']);
        exit;
    }
    $result = issueAndSendOtp($email, $name, 'password_reset');
    echo json_encode(['ok' => $result === true, 'message' => $result === true ? 'New code sent.' : (is_string($result) ? $result : 'Could not resend.')]);
    exit;
}

// ── AJAX: Save new password ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_password') {
    header('Content-Type: application/json');
    $email    = $_SESSION['reset_email']        ?? '';
    $verified = $_SESSION['reset_otp_verified'] ?? false;
    $pwd      = $_POST['password']              ?? '';
    $confirm  = $_POST['confirm_password']      ?? '';

    if (!$email || !$verified) {
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    if ($pwd !== $confirm) {
        echo json_encode(['ok' => false, 'field' => 'match', 'message' => 'Passwords do not match.']);
        exit;
    }

    $pwErrors = [];
    if (strlen($pwd) < 12)                                          $pwErrors[] = 'at least 12 characters';
    if (!preg_match('/[A-Z]/', $pwd))                               $pwErrors[] = 'an uppercase letter (A-Z)';
    if (!preg_match('/[a-z]/', $pwd))                               $pwErrors[] = 'a lowercase letter (a-z)';
    if (!preg_match('/[0-9]/', $pwd))                               $pwErrors[] = 'a number (0-9)';
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/', $pwd))    $pwErrors[] = 'a special character';

    if (!empty($pwErrors)) {
        echo json_encode(['ok' => false, 'field' => 'policy', 'message' => 'Password must contain: ' . implode(', ', $pwErrors) . '.']);
        exit;
    }

    $db   = getDB();
    $user = findUserByEmail($email);
    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'Account not found.']);
        exit;
    }
    $db->prepare('UPDATE users SET password = ? WHERE id = ?')
       ->execute([password_hash($pwd, PASSWORD_DEFAULT), $user['id']]);

    // Send notification email
    sendPasswordChangedEmail($email, $user['full_name'] ?? '');

    // Clear session
    unset($_SESSION['reset_email'], $_SESSION['reset_name'], $_SESSION['reset_otp_verified']);

    echo json_encode(['ok' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <style>
        /* ── Two-phase slider ── */
        /*
         * The slider must be exactly as wide as the auth-panel (including padding).
         * We break out of the 40px panel padding using negative margins, then give
         * each phase its own 40px padding back — so content lines up perfectly.
         * On mobile the panel uses 22px horizontal padding (see responsive.css).
         */
        :root { --auth-pad: 40px; }
        @media (max-width: 480px) { :root { --auth-pad: 22px; } }
        @media (max-width: 480px) {
            .otp-row {
                flex-direction: column;
                align-items: stretch;
            }

            #btnVerifyOtp {
                width: 100%;
            }
        }

        .fp-slider-wrap {
            overflow: hidden;
            width: 100%;
        }
        .fp-slider {
            display: flex;
            width: 100%;
        }
        .fp-phase {
            flex: 0 0 100%;
            width: 100%;
        }
        .fp-slider.phase-2 { transform: translateX(-100%); }

        /* ── Email error state ── */
        .email-err-note {
            font-size: .78rem; color: #ef4444; margin-top: 5px; display: none;
        }
        .email-err-note.v { display: block; }
        input.input-err { border-color: #ef4444 !important; }

        /* ── OTP row ── */
        .otp-row {
            display: flex;
            gap: 8px;
            align-items: center;
            width: 100%;
        }

        .otp-row input {
            flex: 1;
            min-width: 0;
        }
        .otp-row input { flex: 1; letter-spacing: .2em; font-size: 1.1rem; text-align: center; }

        /* ── Password requirements ── */
        .pw-reqs {
            background: var(--surface-2, #f8f5f1); border-radius: 8px;
            padding: 10px 14px; margin-top: 6px; font-size: .78rem;
            color: var(--text-secondary); line-height: 1.8;
        }
        .pw-reqs ul { margin: 0; padding-left: 16px; }
        .pw-reqs li.ok { color: #16a34a; }
        .pw-reqs li.ok::marker { content: "✓  "; }

        /* ── Password mismatch badge ── */
        .pw-mismatch-badge {
            display: none; background: rgba(239,68,68,.1); color: #ef4444;
            border: 1px solid rgba(239,68,68,.2); border-radius: 6px;
            padding: 5px 12px; font-size: .78rem; font-weight: 600;
            text-align: center; margin-top: 8px;
        }
        .pw-mismatch-badge.v { display: block; }

        /* ── OTP status ── */
        .otp-status { font-size: .8rem; margin-top: 6px; min-height: 16px; }
        .otp-status.ok  { color: #16a34a; }
        .otp-status.err { color: #ef4444; }

        /* ── Disabled fields ── */
        input:disabled, button:disabled { opacity: .45; cursor: not-allowed; }

        /* ── Success modal ── */
        #successModal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 9999;
            align-items: center; justify-content: center;
        }
        #successModal.v { display: flex; }
        #successModal .modal-box {
            background: var(--surface-1, #fff); border-radius: 18px;
            padding: 36px 32px; max-width: 360px; width: 92%;
            box-shadow: 0 24px 72px rgba(0,0,0,.22); text-align: center;
        }
        #successModal .modal-icon { font-size: 3rem; margin-bottom: 12px; }
        #successModal h3 { margin: 0 0 8px; font-size: 1.1rem; }
        #successModal p  { margin: 0 0 20px; font-size: .85rem; color: var(--text-secondary); }

        /* ── Resend row ── */
        .resend-row { font-size: .8rem; color: var(--text-muted); margin-top: 8px; }
        .resend-row button {
            background: none; border: none; color: var(--primary, #8B3A3A);
            font-size: .8rem; cursor: pointer; text-decoration: underline; padding: 0;
        }
        .resend-row button:disabled { opacity: .5; cursor: not-allowed; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60%  { transform: translateX(-5px); }
            40%,80%  { transform: translateX(5px); }
        }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <a href="<?= url('index.php') ?>" style="display:inline-block;margin-bottom:8px;">
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="auth-logo-img">
            </a>
            <h1 id="fpTitle">Forgot Password</h1>
            <p id="fpSubtitle">Enter your email to get a reset code.</p>
        </div>

        <div class="fp-slider-wrap">
            <div class="fp-slider" id="fpSlider">

                <!-- ══ PHASE 1: Email ══ -->
                <div class="fp-phase" id="phase1">
                    <div class="form-group">
                        <label class="form-label" for="fp_email">Email Address <span class="req">*</span></label>
                        <input type="email" id="fp_email" class="form-control"
                               placeholder="you@example.com" autocomplete="email">
                        <div class="email-err-note" id="emailErrNote"></div>
                    </div>
                    <button type="button" class="btn btn-primary w-full" id="btnVerifyEmail"
                            style="justify-content:center;padding:11px;">
                        Verify Email
                    </button>
                    <div class="auth-footer" style="margin-top:18px;">
                        <a href="<?= url('login.php') ?>">← Back to Login</a>
                    </div>
                </div>

                <!-- ══ PHASE 2: OTP + New Password ══ -->
                <div class="fp-phase" id="phase2">
                    <p style="font-size:.82rem;color:var(--text-secondary);margin:0 0 14px;">
                        A 6-digit code was sent to <strong id="emailSentTo"></strong>.
                    </p>

                    <!-- OTP -->
                    <div class="form-group">
                        <label class="form-label">Verification Code <span class="req">*</span></label>
                        <div class="otp-row">
                            <input type="text" id="fpOtpInput" class="form-control"
                                   maxlength="6" inputmode="numeric" placeholder="000000"
                                   autocomplete="one-time-code" style="letter-spacing:.25em;font-size:1.25rem;text-align:center;">
                            <button type="button" class="btn btn-accent" id="btnVerifyOtp"
                                    style="white-space:nowrap;flex-shrink:0;padding:9px 14px;font-size:.82rem;">
                                Verify OTP
                            </button>
                        </div>
                        <div class="otp-status" id="otpStatus"></div>
                        <div class="resend-row">
                            Didn't get it? <button type="button" id="btnResend" disabled>Resend Code</button>
                            <span id="resendTimer"></span>
                        </div>
                    </div>

                    <!-- New Password (disabled until OTP verified) -->
                    <div class="form-group" style="margin-top:6px;">
                        <label class="form-label" for="fp_password">New Password <span class="req">*</span></label>
                        <input type="password" id="fp_password" class="form-control"
                               placeholder="Min. 12 characters" autocomplete="new-password" disabled>
                        <div class="pw-reqs" id="pwReqs">
                            <ul>
                                <li id="req_len">At least 12 characters</li>
                                <li id="req_upper">Uppercase letter (A-Z)</li>
                                <li id="req_lower">Lowercase letter (a-z)</li>
                                <li id="req_num">Number (0-9)</li>
                                <li id="req_special">Special character (e.g. !@#$%)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fp_confirm">Confirm Password <span class="req">*</span></label>
                        <input type="password" id="fp_confirm" class="form-control"
                               placeholder="Repeat new password" autocomplete="new-password" disabled>
                    </div>

                    <button type="button" class="btn btn-primary w-full" id="btnSavePassword"
                            style="justify-content:center;padding:11px;" disabled>
                        Save Changes
                    </button>
                    <div class="pw-mismatch-badge" id="pwMismatchBadge">Passwords do not match</div>

                    <div class="auth-footer" style="margin-top:14px;">
                        <a href="<?= url('login.php') ?>">← Back to Login</a>
                    </div>
                </div>

            </div><!-- end fp-slider -->
        </div><!-- end fp-slider-wrap -->
    </div><!-- end auth-panel -->
</div><!-- end auth-page -->

<!-- ══ Success Modal ══ -->
<div id="successModal">
    <div class="modal-box">
        <div class="modal-icon">🔒</div>
        <h3>Password Changed!</h3>
        <p>Your password has been updated. Please sign in with your new password.</p>
        <a href="<?= url('login.php') ?>" class="btn btn-primary w-full" style="justify-content:center;padding:11px;text-decoration:none;">
            Login Now
        </a>
    </div>
</div>

<script src="<?= url('js/pw-toggle.js') ?>"></script>
<script>
(function () {
    'use strict';

    const slider      = document.getElementById('fpSlider');
    const fpEmail     = document.getElementById('fp_email');
    const emailErrNote= document.getElementById('emailErrNote');
    const emailSentTo = document.getElementById('emailSentTo');
    const btnVerifyEmail = document.getElementById('btnVerifyEmail');
    const fpOtpInput  = document.getElementById('fpOtpInput');
    const btnVerifyOtp= document.getElementById('btnVerifyOtp');
    const otpStatus   = document.getElementById('otpStatus');
    const btnResend   = document.getElementById('btnResend');
    const fpPassword  = document.getElementById('fp_password');
    const fpConfirm   = document.getElementById('fp_confirm');
    const btnSave     = document.getElementById('btnSavePassword');
    const mismatchBadge = document.getElementById('pwMismatchBadge');
    const successModal= document.getElementById('successModal');

    // Password requirement elements
    const reqs = {
        len:     document.getElementById('req_len'),
        upper:   document.getElementById('req_upper'),
        lower:   document.getElementById('req_lower'),
        num:     document.getElementById('req_num'),
        special: document.getElementById('req_special'),
    };

    let resendInterval = null;

    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch('', { method: 'POST', body: fd });
        return res.json();
    }

    // ── Phase 1: Verify email ────────────────────────────
    btnVerifyEmail.addEventListener('click', async () => {
        const email = fpEmail.value.trim();
        emailErrNote.classList.remove('v');
        fpEmail.classList.remove('input-err');

        if (!email) {
            fpEmail.classList.add('input-err');
            emailErrNote.textContent = "Please enter your email address.";
            emailErrNote.classList.add('v');
            return;
        }

        btnVerifyEmail.disabled = true;
        btnVerifyEmail.textContent = 'Verifying…';

        try {
            const data = await post({ ajax_action: 'check_email', email });
            if (data.ok) {
                emailSentTo.textContent = email;
                document.getElementById('fpTitle').textContent     = 'Reset Password';
                document.getElementById('fpSubtitle').textContent  = 'Enter the code we sent and choose a new password.';
                slider.classList.add('phase-2');
                fpOtpInput.focus();
                startResendTimer();
            } else {
                fpEmail.classList.add('input-err');
                fpEmail.style.animation = 'none';
                fpEmail.offsetHeight;
                fpEmail.style.animation = 'shake .35s ease';
                emailErrNote.textContent = data.message;
                emailErrNote.classList.add('v');
            }
        } catch(e) {
            emailErrNote.textContent = 'Network error. Please try again.';
            emailErrNote.classList.add('v');
        }

        btnVerifyEmail.disabled = false;
        btnVerifyEmail.textContent = 'Verify Email';
    });

    fpEmail.addEventListener('keydown', e => { if (e.key === 'Enter') btnVerifyEmail.click(); });

    // ── Resend timer ────────────────────────────────────
    function startResendTimer() {
        let secs = 60;
        btnResend.disabled = true;
        const timerEl = document.getElementById('resendTimer');
        clearInterval(resendInterval);
        resendInterval = setInterval(() => {
            secs--;
            timerEl.textContent = ' (' + secs + 's)';
            if (secs <= 0) {
                clearInterval(resendInterval);
                btnResend.disabled = false;
                timerEl.textContent = '';
            }
        }, 1000);
    }

    btnResend.addEventListener('click', async () => {
        btnResend.disabled = true;
        try {
            const data = await post({ ajax_action: 'resend_otp' });
            otpStatus.textContent = data.message;
            otpStatus.className   = 'otp-status ' + (data.ok ? 'ok' : 'err');
            if (data.ok) { fpOtpInput.value = ''; fpOtpInput.focus(); startResendTimer(); }
        } catch(e) {
            otpStatus.textContent = 'Network error.';
            otpStatus.className   = 'otp-status err';
            btnResend.disabled    = false;
        }
    });

    // ── Phase 2: Verify OTP ──────────────────────────────
    btnVerifyOtp.addEventListener('click', async () => {
        const code = fpOtpInput.value.trim();
        if (code.length < 6) {
            otpStatus.textContent = 'Please enter the full 6-digit code.';
            otpStatus.className   = 'otp-status err';
            return;
        }

        btnVerifyOtp.disabled = true;
        btnVerifyOtp.textContent = 'Verifying…';
        otpStatus.textContent = '';

        try {
            const data = await post({ ajax_action: 'verify_otp', otp_code: code });
            if (data.ok) {
                otpStatus.textContent = '✓ OTP verified!';
                otpStatus.className   = 'otp-status ok';
                fpOtpInput.readOnly   = true;
                fpOtpInput.style.borderColor = '#22c55e';
                btnVerifyOtp.textContent = '✓ Verified';
                btnVerifyOtp.style.background = '#16a34a';
                btnVerifyOtp.disabled = true;

                // Unlock password fields
                fpPassword.disabled = false;
                fpConfirm.disabled  = false;
                btnSave.disabled    = false;
                fpPassword.focus();
                // Re-init pw-toggle for newly enabled fields
                if (window.addPwToggle) {
                    window.addPwToggle(fpPassword);
                    window.addPwToggle(fpConfirm);
                }
            } else {
                otpStatus.textContent = data.message || 'Invalid code. Please try again.';
                otpStatus.className   = 'otp-status err';
                fpOtpInput.value      = '';
                fpOtpInput.style.animation = 'none';
                fpOtpInput.offsetHeight;
                fpOtpInput.style.animation = 'shake .35s ease';
                fpOtpInput.focus();
                btnVerifyOtp.disabled = false;
                btnVerifyOtp.textContent = 'Verify OTP';
            }
        } catch(e) {
            otpStatus.textContent = 'Network error.';
            otpStatus.className   = 'otp-status err';
            btnVerifyOtp.disabled = false;
            btnVerifyOtp.textContent = 'Verify OTP';
        }
    });

    fpOtpInput.addEventListener('keydown', e => { if (e.key === 'Enter') btnVerifyOtp.click(); });

    // ── Live password requirements ───────────────────────
    fpPassword.addEventListener('input', () => {
        const v = fpPassword.value;
        const check = (el, pass) => { el.classList.toggle('ok', pass); };
        check(reqs.len,     v.length >= 12);
        check(reqs.upper,   /[A-Z]/.test(v));
        check(reqs.lower,   /[a-z]/.test(v));
        check(reqs.num,     /[0-9]/.test(v));
        check(reqs.special, /[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/.test(v));

        // Hide mismatch badge while typing
        mismatchBadge.classList.remove('v');
        fpConfirm.style.borderColor = '';
    });

    fpConfirm.addEventListener('input', () => {
        mismatchBadge.classList.remove('v');
        fpConfirm.style.borderColor = '';
    });

    // ── Save password ────────────────────────────────────
    btnSave.addEventListener('click', async () => {
        mismatchBadge.classList.remove('v');
        fpConfirm.style.borderColor = '';
        fpPassword.style.borderColor = '';

        const pwd     = fpPassword.value;
        const confirm = fpConfirm.value;

        if (!pwd || !confirm) {
            mismatchBadge.textContent = 'Please fill in both password fields.';
            mismatchBadge.classList.add('v');
            return;
        }

        if (pwd !== confirm) {
            mismatchBadge.textContent = 'Passwords do not match';
            mismatchBadge.classList.add('v');
            fpConfirm.style.borderColor = '#ef4444';
            fpConfirm.style.animation = 'none';
            fpConfirm.offsetHeight;
            fpConfirm.style.animation = 'shake .35s ease';
            return;
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Saving…';

        try {
            const data = await post({ ajax_action: 'save_password', password: pwd, confirm_password: confirm });
            if (data.ok) {
                successModal.classList.add('v');
            } else if (data.field === 'match') {
                mismatchBadge.textContent = data.message;
                mismatchBadge.classList.add('v');
                fpConfirm.style.borderColor = '#ef4444';
                btnSave.disabled = false;
                btnSave.textContent = 'Save Changes';
            } else {
                mismatchBadge.textContent = data.message || 'An error occurred.';
                mismatchBadge.classList.add('v');
                btnSave.disabled = false;
                btnSave.textContent = 'Save Changes';
            }
        } catch(e) {
            mismatchBadge.textContent = 'Network error. Please try again.';
            mismatchBadge.classList.add('v');
            btnSave.disabled = false;
            btnSave.textContent = 'Save Changes';
        }
    });

    // Shake animation style
    const s = document.createElement('style');
    s.textContent = '@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}';
    document.head.appendChild(s);

})();
</script>
</body>
</html>

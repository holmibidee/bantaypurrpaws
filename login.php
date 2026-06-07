<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/google-oauth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? url('admin/dashboard.php') : url('dashboard.php')));
    exit;
}

$error = flash('error') ?? '';
if (!$error && !empty($_SESSION['force_relogin_msg'])) {
    $error = $_SESSION['force_relogin_msg'];
    unset($_SESSION['force_relogin_msg']);
}

// Google Sign-In redirect
if (isset($_GET['google'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . googleAuthUrl($state));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <style>
        /* ── Login page layout ── */
        body { overflow-x: hidden; }

        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        .login-bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            filter: blur(1px) brightness(0.72);
            transform: scale(1.03);
        }
        .login-bg-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg,
                rgba(8,6,4,0.34) 0%,
                rgba(14,10,8,0.46) 45%,
                rgba(10,8,6,0.72) 100%);
        }

        /* Override .auth-page for the fullscreen bg layout */
        .auth-page {
            background: transparent;
            z-index: 1;
            position: relative;
            justify-content: center;
            padding: 24px;
            min-height: 100vh;
        }

        /* Two-column grid: hero left, card right */
        .auth-grid {
            display: grid;
            grid-template-columns: 1fr minmax(340px, 420px);
            gap: 56px;
            width: min(100%, 1040px);
            margin: 0 auto;
            padding: 0 48px 0 0;
            align-items: center;
            z-index: 2;
            position: relative;
        }

        /* Hero text */
        .login-hero {
            color: #fff;
            padding: 36px 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: left;
            align-items: flex-start;
            margin-right: 24px;
        }
        .login-hero-paws {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 52px;
            border-radius: 999px;
            background: rgba(255,255,255,0.18);
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        .login-hero h1 {
            font-size: clamp(2.4rem, 4vw, 4.4rem);
            line-height: 1.04;
            margin: 18px 0 16px;
            letter-spacing: -0.04em;
            color: #fff;
            font-family: var(--font-display);
        }
        .login-hero p {
            font-size: 1rem;
            line-height: 1.8;
            color: rgba(255,255,255,0.88);
            max-width: 460px;
        }

        /* Card */
        .auth-panel {
            max-width: 100%;
        }

        /* Google button — override inline style from style.css */
        .btn-google {
            margin-bottom: 0;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: var(--text-muted);
            font-size: 13px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── MFA step indicator ── */
        .mfa-steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 20px;
        }
        .mfa-step {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .mfa-step-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.25s;
        }
        .mfa-step.active .mfa-step-dot {
            background: var(--stone-900);
            border-color: var(--stone-900);
            color: #fff;
        }
        .mfa-step.done .mfa-step-dot {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }
        .mfa-step.active { color: var(--text-primary); }
        .mfa-connector {
            flex: 1;
            height: 2px;
            background: var(--border);
            margin: 0 6px;
            min-width: 12px;
            transition: background 0.25s;
        }
        .mfa-connector.done { background: #10b981; }

        /* ── MFA panels ── */
        .mfa-panel {
            display: none;
            flex-direction: column;
            gap: 14px;
        }
        .mfa-panel.active { display: flex; }

        /* OTP input */
        .otp-input {
            letter-spacing: 0.25em;
            font-size: 1.4rem;
            text-align: center;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* OTP info box */
        .otp-info {
            background: var(--surface-2);
            border-radius: var(--radius);
            padding: 14px 16px;
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.6;
            text-align: center;
        }
        .otp-info strong { color: var(--text-primary); }

        /* Resend row */
        .resend-row {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .resend-row button {
            background: none;
            border: none;
            padding: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--accent);
            cursor: pointer;
            text-decoration: underline;
        }
        .resend-row button:disabled {
            color: var(--text-muted);
            cursor: default;
            text-decoration: none;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .auth-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }
            .login-hero {
                text-align: center;
                padding: 24px 0 0;
                align-items: center;   /* stack nicely when single-column */
            }
            .auth-grid { padding: 0; }   /* no right padding when stacked */
            .login-hero p { margin: 0 auto; }
            .auth-panel { margin: 0 auto; }
        }

        @media (max-width: 540px) {
            .auth-page { padding: 16px; }
            .auth-grid { gap: 20px; }
            .login-hero { padding: 16px 0 0; }
            .login-hero h1 { font-size: 2.2rem; }
            .login-hero p { font-size: 0.92rem; }
            .auth-panel { padding: 28px 20px; }
            /* Hide hero text on very small screens to give the card more room */
            .login-hero p,
            .login-hero-paws { display: none; }
            .login-hero h1 { font-size: 1.9rem; margin-top: 0; }
            .mfa-step span { display: none; }
        }
    </style>
</head>
<body>

<!-- Full-screen background -->
<div class="login-bg" aria-hidden="true">
    <img src="<?= url('assets/dog.jpg') ?>" alt="" class="login-bg-img">
    <div class="login-bg-overlay"></div>
</div>

<div class="auth-page">
    <div class="auth-grid">

        <!-- Hero -->
        <div class="login-hero" aria-hidden="true">
            <span class="login-hero-paws">❤️</span>
            <h1>Give a pet a<br>loving home.</h1>
            <p>Report strays, connect with rescuers, and find your next furry family member — all in one place.</p>
        </div>

        <!-- Card -->
        <div class="auth-panel fade-in">

            <div class="auth-logo">
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="auth-logo-img">
                <p>Stray Animal Rescue &amp; Adoption System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
            <?php endif; ?>

            <!-- Google Sign-In -->
            <a href="?google=1" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                    <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                    <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/>
                    <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/>
                    <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/>
                </svg>
                Sign in with Google
            </a>

            <div class="divider">or sign in with email</div>

            <!-- Step indicator -->
            <div class="mfa-steps" id="mfaSteps" role="list" aria-label="Sign-in steps">
                <div class="mfa-step active" id="si1" role="listitem"><div class="mfa-step-dot">1</div><span>Credentials</span></div>
                <div class="mfa-connector" id="c1"></div>
                <div class="mfa-step" id="si2" role="listitem"><div class="mfa-step-dot">2</div><span>OTP</span></div>
                <div class="mfa-connector" id="c2"></div>
                <div class="mfa-step" id="si3" role="listitem"><div class="mfa-step-dot">3</div><span>Done</span></div>
            </div>

            <!-- Panel 1: Email + Password -->
            <div class="mfa-panel active" id="panel1">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="req" aria-hidden="true">*</span></label>
                    <input type="email" id="email" class="form-control" placeholder="you@example.com" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="req" aria-hidden="true">*</span></label>
                    <input type="password" id="password" class="form-control" placeholder="Your password" autocomplete="current-password" required>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:-6px;margin-bottom:2px;">
                    <a href="<?= url('forgot-password.php') ?>" style="font-size:0.8rem;color:var(--text-muted);">Forgot password?</a>
                </div>
                <button type="button" id="btnLogin" class="btn btn-accent">Sign In</button>
                <div id="loginErr" class="alert alert-error" style="display:none;" role="alert"></div>
            </div>

            <!-- Panel 2: OTP verification -->
            <div class="mfa-panel" id="panel2">
                <div class="otp-info">
                    A 6-digit code was sent to <strong id="emailDisplay"></strong>.<br>
                    Enter it below — it expires in 15 minutes.
                </div>
                <div class="form-group">
                    <label class="form-label" for="otpCode">One-Time Password</label>
                    <input type="text" id="otpCode" class="form-control otp-input"
                           placeholder="000000" maxlength="6" inputmode="numeric"
                           autocomplete="one-time-code">
                </div>
                <button type="button" id="btnVerify" class="btn btn-accent">Verify &amp; Sign In</button>
                <div id="otpErr" class="alert alert-error" style="display:none;" role="alert"></div>
                <div class="resend-row">
                    <span>Didn't receive it?</span>
                    <button type="button" id="btnResend" disabled>Resend (<span id="resendCountdown">30</span>s)</button>
                </div>
                <button type="button" id="btnBack" class="btn btn-ghost" style="width:100%;">← Back</button>
            </div>

            <div class="auth-footer">
                Don't have an account? <a href="<?= url('register.php') ?>">Create one</a>
            </div>
            <div class="auth-footer" style="margin-top:6px;">
                <a href="<?= url('index.php') ?>">← Back to home</a>
            </div>

        </div><!-- /.auth-panel -->
    </div><!-- /.auth-grid -->
</div><!-- /.auth-page -->

<script>
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────
    const emailInput  = document.getElementById('email');
    const pwInput     = document.getElementById('password');
    const btnLogin    = document.getElementById('btnLogin');
    const loginErr    = document.getElementById('loginErr');

    const otpInput    = document.getElementById('otpCode');
    const btnVerify   = document.getElementById('btnVerify');
    const otpErr      = document.getElementById('otpErr');
    const btnResend   = document.getElementById('btnResend');
    const countdown   = document.getElementById('resendCountdown');
    const emailDisp   = document.getElementById('emailDisplay');
    const btnBack     = document.getElementById('btnBack');

    const panels  = { 1: document.getElementById('panel1'), 2: document.getElementById('panel2') };
    const steps   = { 1: document.getElementById('si1'),    2: document.getElementById('si2'),    3: document.getElementById('si3') };
    const conns   = { 1: document.getElementById('c1'),     2: document.getElementById('c2') };

    let currentEmail = '';
    let resendTimer  = null;

    // ── Step manager ──────────────────────────────────────
    function setStep(n) {
        Object.values(panels).forEach(p => p.classList.remove('active'));
        Object.values(steps).forEach(s => s.classList.remove('active', 'done'));
        Object.values(conns).forEach(c => c.classList.remove('done'));

        if (panels[n]) panels[n].classList.add('active');

        for (let i = 1; i < n; i++) {
            if (steps[i]) steps[i].classList.add('done');
            if (conns[i]) conns[i].classList.add('done');
        }
        if (steps[n]) steps[n].classList.add('active');
    }

    function showErr(el, msg) {
        el.textContent = '✕ ' + msg;
        el.style.display = 'block';
    }
    function hideErr(el) { el.style.display = 'none'; }

    // ── Step 1: Verify credentials, then issue OTP ────────
    btnLogin.addEventListener('click', async () => {
        hideErr(loginErr);
        const email = emailInput.value.trim();
        const pw    = pwInput.value;
        if (!email || !pw) { showErr(loginErr, 'Please enter your email and password.'); return; }

        btnLogin.disabled = true;
        btnLogin.innerHTML = '<span class="spinner"></span>Signing in…';

        try {
            // 1a. Verify credentials only — no session yet (OTP must pass first)
            const credRes = await fetch(<?= json_encode(url('api/login.php')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'credentials_only', email, password: pw })
            });
            const cred = await credRes.json();

            if (!cred.success) {
                showErr(loginErr, cred.message || 'Invalid email or password.');
                return;
            }

            // 1b. Credentials OK — issue OTP to the verified email
            const otpRes = await fetch(<?= json_encode(url('api/otp.php')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'issue', email, purpose: 'login' })
            });
            const otp = await otpRes.json();

            if (!otp.success) {
                showErr(loginErr, otp.message || 'Could not send OTP. Please try again.');
                return;
            }

            // 1c. Move to OTP panel
            currentEmail = email;
            emailDisp.textContent = email;
            otpInput.value = '';
            hideErr(otpErr);
            setStep(2);
            otpInput.focus();
            startResendTimer();

        } catch (e) {
            showErr(loginErr, 'Network error. Please check your connection.');
        } finally {
            btnLogin.disabled = false;
            btnLogin.textContent = 'Sign In';
        }
    });

    pwInput.addEventListener('keydown', e => { if (e.key === 'Enter') btnLogin.click(); });

    // ── Step 2: Verify OTP, then complete login ───────────
    btnVerify.addEventListener('click', async () => {
        hideErr(otpErr);
        const code = otpInput.value.trim();
        if (code.length !== 6 || !/^\d{6}$/.test(code)) {
            showErr(otpErr, 'Please enter the 6-digit code.');
            return;
        }

        btnVerify.disabled = true;
        btnVerify.innerHTML = '<span class="spinner"></span>Verifying…';

        try {
            // 2a. Verify the OTP code
            const vRes = await fetch(<?= json_encode(url('api/otp.php')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'verify', email: currentEmail, code, purpose: 'login' })
            });
            const v = await vRes.json();

            if (!v.success) {
                const msg = v.message === 'expired'
                    ? 'Code expired. Request a new one below.'
                    : 'Incorrect code. Please try again.';
                showErr(otpErr, msg);
                return;
            }

            // 2b. OTP valid — finalise the session on the server
            const sessRes = await fetch(<?= json_encode(url('api/login.php')) ?>, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'text/html, application/json'      // browser client path
                },
                body: new URLSearchParams({ action: 'login', email: currentEmail, password: pwInput.value })
            });
            const sess = await sessRes.json();

            // 2c. Mark step 3 done and redirect
            if (steps[2]) steps[2].classList.replace('active', 'done');
            if (conns[2]) conns[2].classList.add('done');
            if (steps[3]) steps[3].classList.add('active');

            stopResendTimer();

            const isAdmin = sess.user && (sess.user.role === 'admin' || sess.user.role === 'staff');
            window.location.href = isAdmin
                ? <?= json_encode(url('admin/dashboard.php')) ?>
                : <?= json_encode(url('dashboard.php')) ?>;

        } catch (e) {
            showErr(otpErr, 'Network error. Please try again.');
        } finally {
            btnVerify.disabled = false;
            btnVerify.textContent = 'Verify & Sign In';
        }
    });

    otpInput.addEventListener('keydown', e => { if (e.key === 'Enter') btnVerify.click(); });

    // Auto-submit when 6 digits are typed
    otpInput.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
        if (otpInput.value.length === 6) btnVerify.click();
    });

    // ── Back button ───────────────────────────────────────
    btnBack.addEventListener('click', () => {
        stopResendTimer();
        currentEmail = '';
        otpInput.value = '';
        pwInput.value = '';
        hideErr(loginErr);
        hideErr(otpErr);
        setStep(1);
        emailInput.focus();
    });

    // ── Resend OTP countdown ──────────────────────────────
    function startResendTimer(seconds = 30) {
        stopResendTimer();
        let remaining = seconds;
        btnResend.disabled = true;
        countdown.textContent = remaining;

        resendTimer = setInterval(() => {
            remaining--;
            countdown.textContent = remaining;
            if (remaining <= 0) {
                stopResendTimer();
                btnResend.disabled = false;
                btnResend.textContent = 'Resend code';
            }
        }, 1000);
    }

    function stopResendTimer() {
        if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
    }

    btnResend.addEventListener('click', async () => {
        btnResend.disabled = true;
        btnResend.textContent = 'Sending…';
        hideErr(otpErr);
        try {
            const res = await fetch(<?= json_encode(url('api/otp.php')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'issue', email: currentEmail, purpose: 'login' })
            });
            const j = await res.json();
            if (j.success) {
                startResendTimer(60);  // longer wait on resend
            } else {
                showErr(otpErr, j.message || 'Could not resend. Try again later.');
                btnResend.disabled = false;
                btnResend.textContent = 'Resend code';
            }
        } catch {
            showErr(otpErr, 'Network error. Please try again.');
            btnResend.disabled = false;
            btnResend.textContent = 'Resend code';
        }
    });

    // ── Init ──────────────────────────────────────────────
    setStep(1);
    emailInput.focus();
})();
</script>

<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>
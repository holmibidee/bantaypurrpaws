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
        .divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--text-muted);font-size:13px;}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
        .btn-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:10px 16px;border:1.5px solid var(--border);background:#fff;color:#3c4043;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:border-color .2s,box-shadow .2s;}
        .btn-google:hover{border-color:#4285f4;box-shadow:0 0 0 3px rgba(66,133,244,.12);}

        /* Steps */
        .mfa-steps{display:flex;align-items:center;gap:0;margin-bottom:24px;}
        .mfa-step{display:flex;align-items:center;gap:6px;font-size:0.78rem;font-weight:500;color:var(--text-muted);}
        .mfa-step-dot{width:24px;height:24px;border-radius:50%;background:var(--surface-2);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:0.68rem;font-weight:700;color:var(--text-muted);transition:all .25s;flex-shrink:0;}
        .mfa-step.active .mfa-step-dot{background:var(--accent);border-color:var(--accent);color:#fff;}
        .mfa-step.done .mfa-step-dot{background:#10b981;border-color:#10b981;color:#fff;}
        .mfa-step.active{color:var(--text-primary);}
        .mfa-connector{flex:1;height:2px;background:var(--border);margin:0 6px;min-width:16px;transition:background .25s;}
        .mfa-connector.done{background:#10b981;}
        .mfa-panel{display:none;flex-direction:column;gap:16px;}
        .mfa-panel.active{display:flex;}

        /* Challenge waiting screen */
        .challenge-waiting{text-align:center;padding:20px 0;}
        .challenge-waiting .email-icon{font-size:48px;margin-bottom:12px;animation:pulse 2s infinite;}
        @keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}
        .spinner{display:inline-block;width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle;margin-right:6px;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .status-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:0.78rem;font-weight:600;margin-bottom:16px;}
        .badge-waiting{background:rgba(245,158,11,0.12);color:#d97706;border:1px solid rgba(245,158,11,0.3);}
        .badge-approved{background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);}
        .badge-denied{background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}

         /* Full-screen background */
        .login-bg {position: fixed;inset: 0;z-index: 0;overflow:hidden;}
        .login-bg-img {position: absolute;inset: 0;width: 100%;height: 100%;object-fit: cover;object-position: center;filter: blur(1px) brightness(0.72);transform: scale(1.03);}
        .login-bg-overlay{ position: absolute; inset: 0; background: linear-gradient(135deg, rgba(8, 6, 4, 0.34) 0%, rgba(14, 10, 8, 0.46) 45%, rgba(10, 8, 6, 0.72) 100% );}

        .auth-page {
            background: transparent;
            z-index: 1;
            justify-content: center;
            align-items: center;
            padding: 0 24px;
            min-height: 100vh;
        }

        .auth-grid {
            position: relative;
            display: grid;
            grid-template-columns: minmax(320px, 1.2fr) minmax(360px, 420px);
            gap: 48px;
            width: min(100%, 980px);
            max-width: 980px;
            margin: 0 auto;
            padding: 0;
            align-items: center;
            justify-content: space-around;
            z-index: 2;
        }

        .login-hero {
            color: #fff;
            max-width: 560px;
            padding: 36px 0;
            justify-self: start;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form {
            width: 100%;
            min-width: 0;
            justify-self: end;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-hero-paws {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: rgba(255,255,255,0.18);
            box-shadow: 0 24px 60px rgba(0,0,0,0.22);
            font-size: 1.2rem;
        }
        .login-hero h1 {
            font-size: clamp(2.8rem, 4vw, 4.8rem);
            line-height: 1.02;
            margin: 22px 0 20px;
            letter-spacing: -0.04em;
            color: #fff;
        }
        .login-hero p {
            font-size: 1.05rem;
            line-height: 1.8;
            color: rgba(255,255,255,0.88);
            max-width: 520px;
            margin: 0;
        }
        .mobile-signup-text {
            display: none;
            margin-top: 28px;
            font-size: 1rem;
            color: rgba(255,255,255,0.95);
            text-decoration: underline;
            font-weight: 600;
        }
        .login-form {
            width: 100%;
            min-width: 0;
        }
        .auth-panel {
            max-width: 100%;
        }

        @media (max-width: 980px) {
            .auth-grid {grid-template-columns: 1fr;gap: 32px;}
            .login-hero {text-align: center; padding: 20px 0 0;}
            .login-hero h1 {font-size: clamp(2.4rem, 8vw, 3.2rem);}
            .auth-panel {margin: 0 auto;}
        }

        @media (max-width: 640px) {
            .auth-grid {display: block;}
            .login-hero {padding: 24px 0 0; text-align: center; align-items: center;}
            .login-hero h1 {font-size: clamp(2.2rem, 7vw, 2.8rem);}
            .login-hero p {margin: 0 auto;}
            .mobile-signup-text {display: inline-block;}
            .auth-panel {display: none !important;}
            .auth-page {padding: 18px 16px; align-items: center; justify-content: center;}
            .login-bg-img {filter: blur(1px) brightness(0.65);}
        }

        @media (max-width: 600px) {
            .auth-page {padding: 18px;}
            .auth-panel {padding: 28px 22px;}
            .login-hero h1 {font-size: 2.2rem;}
        }
    </style>
</head>
<body>
<div class="login-bg">
    <img src="<?= url('assets/dog.jpg') ?>" alt="Dog background" class="login-bg-img">
    <div class="login-bg-overlay"></div>
</div>
<div class="auth-page">
    <div class="auth-grid">
        <div class="login-hero" aria-hidden="true">
            <span class="login-hero-paws">❤️</span>
            <h1>Give a pet a<br>loving home.</h1>
            <p>Report strays, connect with rescuers, and find your next furry family member — all in one place.</p>
            <a href="<?= url('register.php') ?>" class="mobile-signup-text">Create your account now</a>
        </div>
        <div class="auth-panel fade-in login-form">
            <div class="auth-logo">
        <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="auth-logo-img">
        <p>Stray Animal Rescue &amp; Adoption System</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <a href="?google=1" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/><path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
        Sign in with Google
    </a>

    <div class="divider">or sign in with email</div>

    <!-- Step Indicators -->
    <div class="mfa-steps" id="mfaSteps">
        <div class="mfa-step active" id="si1"><div class="mfa-step-dot">1</div><span>Email</span></div>
        <div class="mfa-connector" id="c1"></div>
        <div class="mfa-step" id="si2"><div class="mfa-step-dot">2</div><span>Verify</span></div>
        <div class="mfa-connector" id="c2"></div>
        <div class="mfa-step" id="si3"><div class="mfa-step-dot">3</div><span>Match</span></div>
        <div class="mfa-connector" id="c3"></div>
        <div class="mfa-step" id="si4"><div class="mfa-step-dot">4</div><span>OTP</span></div>
    </div>

    <!-- Panel 1: Email + Password -->
    <div class="mfa-panel active" id="panel1">
        <div class="form-group">
            <label class="form-label">Email Address <span class="req">*</span></label>
            <input type="email" id="email" class="form-control" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="form-group">
            <label class="form-label">Password <span class="req">*</span></label>
            <input type="password" id="password" class="form-control" placeholder="Your password" autocomplete="current-password">
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:-8px;margin-bottom:4px;">
            <a href="<?= url('forgot-password.php') ?>" style="font-size:0.8rem;color:var(--text-muted);">Forgot password?</a>
        </div>
        <button type="button" id="btnLogin" class="btn btn-accent">Sign In</button>
        <div id="loginErr" style="display:none;" class="alert alert-error"></div>
    </div>

    <!-- Panel 2: Waiting for email challenge -->
    <div class="mfa-panel" id="panel2">
        <div class="challenge-waiting">
            <div class="email-icon">📧</div>
            <div class="status-badge badge-waiting" id="challengeBadge">
                <span class="spinner"></span>
                Waiting for your response…
            </div>
            <p style="font-size:0.875rem;color:var(--text-muted);margin:0 0 12px;">
                A verification email was sent to <strong id="emailDisplay"></strong>.<br>
                Open it and click <strong>"Yes, It's Me"</strong> to continue.
            </p>
            <p style="font-size:0.78rem;color:var(--text-muted);">This page will update automatically when you respond.</p>
        </div>
        <button type="button" id="btnCancelChallenge" class="btn btn-ghost" style="margin-top:8px;">← Start Over</button>
    </div>

    <div class="auth-footer">
        Don't have an account? <a href="<?= url('register.php') ?>">Create one</a>
    </div>
    <div class="auth-footer" style="margin-top:6px;">
        <a href="<?= url('index.php') ?>">← Back to home</a>
    </div>
</div>
    </div>
</div>

<script>
(function(){
    const emailInput = document.getElementById('email');
    const pwInput    = document.getElementById('password');
    const btnLogin   = document.getElementById('btnLogin');
    const loginErr   = document.getElementById('loginErr');
    const emailDisp  = document.getElementById('emailDisplay');
    const badge      = document.getElementById('challengeBadge');
    const btnCancel  = document.getElementById('btnCancelChallenge');

    const panels = [null, document.getElementById('panel1'), document.getElementById('panel2')];
    const steps  = [null, document.getElementById('si1'), document.getElementById('si2'), document.getElementById('si3'), document.getElementById('si4')];
    const conns  = [null, document.getElementById('c1'), document.getElementById('c2'), document.getElementById('c3')];

    let pollInterval = null;
    let challengeToken = null;

    function setStep(n) {
        panels.filter(Boolean).forEach(p => p.classList.remove('active'));
        steps.filter(Boolean).forEach(s => { s.classList.remove('active','done'); });
        conns.filter(Boolean).forEach(c => c.classList.remove('done'));
        if (n >= 1 && panels[n]) panels[n].classList.add('active');
        for (let i = 1; i < n; i++) { if (steps[i]) steps[i].classList.add('done'); if (conns[i]) conns[i].classList.add('done'); }
        if (steps[n]) steps[n].classList.add('active');
    }

    function showErr(msg) {
        loginErr.textContent = '✕ ' + msg;
        loginErr.style.display = 'block';
    }
    function hideErr() { loginErr.style.display = 'none'; }

    btnLogin.addEventListener('click', async () => {
        hideErr();
        const email = emailInput.value.trim();
        const pw    = pwInput.value;
        if (!email || !pw) { showErr('Please enter your email and password.'); return; }
        btnLogin.disabled = true; btnLogin.textContent = 'Signing in…';
        try {
            const res = await fetch(<?= json_encode(url('api/login.php')) ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action:'login', email, password: pw})
            });
            const j = await res.json();
            if (j.success && j.step === 'challenge') {
                challengeToken = j.token;
                emailDisp.textContent = email;
                setStep(2);
                startPolling();
            } else {
                showErr(j.message || 'Login failed. Please try again.');
            }
        } catch(e) { showErr('Network error. Please check your connection.'); }
        btnLogin.disabled = false; btnLogin.textContent = 'Sign In';
    });

    pwInput.addEventListener('keydown', e => { if (e.key === 'Enter') btnLogin.click(); });

    btnCancel.addEventListener('click', () => {
        stopPolling();
        challengeToken = null;
        pwInput.value = '';
        setStep(1);
        emailInput.focus();
    });

    function startPolling() {
        stopPolling();
        pollInterval = setInterval(pollStatus, 3000);
    }
    function stopPolling() {
        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    }

    async function pollStatus() {
        if (!challengeToken) return;
        try {
            const res = await fetch(<?= json_encode(url('api/auth-status.php')) ?> + '?token=' + encodeURIComponent(challengeToken));
            const j   = await res.json();
            if (j.status === 'approved') {
                stopPolling();
                badge.className = 'status-badge badge-approved';
                badge.innerHTML = '✓ Approved — redirecting…';
                // Redirect to the challenge page which handles number matching + OTP
                window.location.href = <?= json_encode(url('auth/login-challenge.php')) ?> + '?action=approve&token=' + encodeURIComponent(challengeToken);
            } else if (j.status === 'denied') {
                stopPolling();
                badge.className = 'status-badge badge-denied';
                badge.innerHTML = '✕ Login denied — attempt blocked';
                setTimeout(() => { setStep(1); emailInput.value = ''; pwInput.value = ''; }, 3000);
            }
        } catch(e) {}
    }

    // Auto-focus
    emailInput.focus();
})();
</script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>

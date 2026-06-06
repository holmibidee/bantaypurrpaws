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
// Show force-relogin message (from permission change)
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

// Standard email/password login (submitted after OTP verified on client)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } else {
        $result = loginUser($email, $password);

        if (is_array($result)) {
            createSystemNotification('system', 'You logged in successfully.', null, null, (int)$result['id']);
            $redirect = in_array($result['role'], ['admin', 'staff'])
                ? url('admin/dashboard.php')
                : url('dashboard.php');
            header("Location: $redirect");
            exit;
        } else {
            $error = $result;
        }
    }
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
        /* MFA step indicators */
        .mfa-steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 28px;
        }
        .mfa-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .mfa-step-dot {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            transition: all .25s;
            flex-shrink: 0;
        }
        .mfa-step.active .mfa-step-dot {
            background: var(--accent);
            border-color: var(--accent);
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
            margin: 0 8px;
            min-width: 24px;
            transition: background .25s;
        }
        .mfa-connector.done { background: #10b981; }

        .divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--text-muted);font-size:13px;}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}

        .btn-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:10px 16px;border:1.5px solid var(--border);background:#fff;color:var(--text-primary);border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:border-color .2s,box-shadow .2s;}
        .btn-google:hover{border-color:#4285f4;box-shadow:0 0 0 3px rgba(66,133,244,.12);}

        /* Step panels */
        .mfa-panel { display: none; flex-direction: column; gap: 16px; }
        .mfa-panel.active { display: flex; }

        /* Password modal */
        #pwModal {
            display: flex;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        #pwModal.visible { opacity: 1; pointer-events: all; }
        #pwModal .modal-content {
            width: min(100%, 440px);
            padding: 32px;
            border-radius: 20px;
            background: #1c1917;
            border: 1px solid #3a3a3a;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 18px;
            transform: scale(0.95);
            transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        #pwModal.visible .modal-content { transform: scale(1); }
        #pwModal h3 { font-family: var(--font-display); font-size: 1.3rem; color: #fafaf9; margin: 0; }
        #pwModal .form-label { color: #d6d3d1; }
        #pwModal .form-control { background: #292524; border-color: #44403c; color: #fafaf9; }
        #pwModal .form-control::placeholder { color: #78716c; }
        #pwModal .form-control:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(139,58,58,.3); }
        #pwModal .btn { background: #292524; color: #d6d3d1; border: 1px solid #44403c; }
        #pwModal .btn:hover { background: #44403c; color: #fafaf9; }
        #pwModal .btn-primary { background: var(--brand-primary); color: #fff; border: none; }
        #pwModal .btn-primary:hover { background: var(--brand-primary-dark); }
        #pwModal .mfa-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.75rem; font-weight: 600;
            color: #10b981; background: rgba(16,185,129,.12);
            border: 1px solid rgba(16,185,129,.25);
            padding: 4px 12px; border-radius: 999px;
            width: fit-content;
        }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="auth-logo-img">
            <p>Stray Animal Rescue &amp; Adoption System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
        <?php endif; ?>

        <!-- Google sign-in -->
        <a href="?google=1" class="btn-google">
            <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/><path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
            Sign in with Google
        </a>

        <div class="divider">or sign in with email</div>

        <!-- MFA Step Indicator -->
        <div class="mfa-steps" id="mfaSteps">
            <div class="mfa-step active" id="stepIndicator1">
                <div class="mfa-step-dot">1</div>
                <span>Email</span>
            </div>
            <div class="mfa-connector" id="connector1"></div>
            <div class="mfa-step" id="stepIndicator2">
                <div class="mfa-step-dot">2</div>
                <span>Verify OTP</span>
            </div>
            <div class="mfa-connector" id="connector2"></div>
            <div class="mfa-step" id="stepIndicator3">
                <div class="mfa-step-dot">3</div>
                <span>Password</span>
            </div>
        </div>

        <form method="POST" action="" id="loginForm">
            <!-- Step 1: Email -->
            <div class="mfa-panel active" id="panel1">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com"
                           value="<?= sanitize($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>
                <button type="button" id="btnIssueOtp" class="btn btn-accent">Send OTP Code</button>
            </div>

            <!-- Step 2: OTP -->
            <div class="mfa-panel" id="panel2">
                <p style="font-size:.875rem; color: var(--text-secondary); margin-bottom:4px;">
                    A 6-digit code was sent to <strong id="emailDisplay"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="otp">One-Time Password <span class="req">*</span></label>
                    <input type="text" id="otp" name="otp" class="form-control"
                           placeholder="000000" maxlength="6" autocomplete="one-time-code"
                           style="letter-spacing: 0.25em; font-size: 1.25rem; text-align: center;">
                </div>
                <button type="button" id="btnVerifyOtp" class="btn btn-accent">Verify Code</button>
                <button type="button" id="btnBackToEmail" class="btn btn-ghost" style="margin-top:4px;">← Back</button>
            </div>

            <!-- Hidden password field submitted with form -->
            <input type="password" id="hiddenPassword" name="password" style="display:none;" autocomplete="current-password" data-no-pw-toggle="1">

            <button type="submit" id="finalSubmit" style="display:none;">Sign In</button>
        </form>

        <!-- Step 3: Password modal (shown after OTP verified) -->
        <div id="pwModal" class="modal">
            <div class="modal-content">
                <div class="mfa-badge">✓ Email verified</div>
                <h3>Enter your password</h3>
                <div class="form-group">
                    <label class="form-label" for="modal_password">Password <span class="req">*</span></label>
                    <input type="password" id="modal_password" class="form-control"
                           placeholder="Your account password" autocomplete="current-password">
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;align-items:center;">
                    <a href="<?= url('forgot-password.php') ?>" style="font-size:0.8rem;color:#a8a29e;text-decoration:underline;margin-right:auto;">Forgot password?</a>
                    <button type="button" id="btnCancelModal" class="btn">Cancel</button>
                    <button type="button" id="btnModalLogin" class="btn btn-primary">Sign In</button>
                </div>
            </div>
        </div>

        <div class="auth-footer">
            Don't have an account? <a href="<?= url('register.php') ?>">Create one</a>
        </div>
        <div class="auth-footer" style="margin-top:6px;">
            <a href="<?= url('index.php') ?>">← Back to home</a>
        </div>
    </div>
</div>

<script>
(function(){
    const emailInput    = document.getElementById('email');
    const emailDisplay  = document.getElementById('emailDisplay');
    const otpInput      = document.getElementById('otp');
    const hiddenPwd     = document.getElementById('hiddenPassword');
    const loginForm     = document.getElementById('loginForm');

    const btnIssue      = document.getElementById('btnIssueOtp');
    const btnVerify     = document.getElementById('btnVerifyOtp');
    const btnBack       = document.getElementById('btnBackToEmail');
    const btnModalLogin = document.getElementById('btnModalLogin');
    const btnCancel     = document.getElementById('btnCancelModal');
    const modalPwd      = document.getElementById('modal_password');
    const pwModal       = document.getElementById('pwModal');

    const panel1 = document.getElementById('panel1');
    const panel2 = document.getElementById('panel2');

    const si1 = document.getElementById('stepIndicator1');
    const si2 = document.getElementById('stepIndicator2');
    const si3 = document.getElementById('stepIndicator3');
    const c1  = document.getElementById('connector1');
    const c2  = document.getElementById('connector2');

    function setStep(n) {
        [panel1, panel2].forEach(p => p.classList.remove('active'));
        [si1, si2, si3].forEach(s => { s.classList.remove('active','done'); });
        [c1, c2].forEach(c => c.classList.remove('done'));

        if (n === 1) {
            panel1.classList.add('active');
            si1.classList.add('active');
        } else if (n === 2) {
            panel2.classList.add('active');
            si1.classList.add('done'); c1.classList.add('done');
            si2.classList.add('active');
        } else if (n === 3) {
            si1.classList.add('done'); c1.classList.add('done');
            si2.classList.add('done'); c2.classList.add('done');
            si3.classList.add('active');
        }
    }

    btnIssue.addEventListener('click', async function() {
        const e = emailInput.value.trim();
        if (!e) { alert('Enter your email address first.'); return; }
        btnIssue.disabled = true;
        btnIssue.textContent = 'Sending…';
        try {
            const res = await fetch('<?= url('api/otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'issue', email: e })
            });
            const json = await res.json();
            if (json.success) {
                emailDisplay.textContent = e;
                setStep(2);
                otpInput.focus();
            } else {
                alert(json.message || 'Failed to send OTP. Please try again.');
            }
        } catch(err) {
            alert('Network error. Please try again.');
        }
        btnIssue.disabled = false;
        btnIssue.textContent = 'Send OTP Code';
    });

    btnBack.addEventListener('click', function() { setStep(1); });

    btnVerify.addEventListener('click', async function() {
        const e = emailInput.value.trim();
        const c = otpInput.value.trim();
        if (c.length < 6) { alert('Enter the 6-digit OTP code.'); return; }
        btnVerify.disabled = true;
        btnVerify.textContent = 'Verifying…';
        try {
            const res = await fetch('<?= url('api/otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'verify', email: e, code: c })
            });
            const json = await res.json();
            if (json.success) {
                setStep(3);
                pwModal.classList.add('visible');
                if (window.addPwToggle) window.addPwToggle(modalPwd);
                modalPwd.focus();
            } else {
                alert(json.message || 'Invalid or expired OTP. Please try again.');
                btnVerify.disabled = false;
                btnVerify.textContent = 'Verify Code';
            }
        } catch(err) {
            alert('Network error. Please try again.');
            btnVerify.disabled = false;
            btnVerify.textContent = 'Verify Code';
        }
    });

    btnCancel.addEventListener('click', function() {
        pwModal.classList.remove('visible');
        modalPwd.value = '';
        // Reset to step 1
        emailInput.value = '';
        otpInput.value = '';
        setStep(1);
        emailInput.focus();
    });

    btnModalLogin.addEventListener('click', function() {
        const pwd = modalPwd.value;
        if (!pwd) { alert('Please enter your password.'); return; }
        // Copy password into hidden field and submit form
        hiddenPwd.value = pwd;
        pwModal.classList.remove('visible');
        loginForm.submit();
    });

    // Allow Enter key in OTP field
    otpInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnVerify.click();
    });

    // Allow Enter key in password modal
    modalPwd.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnModalLogin.click();
    });
})();
</script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/google-oauth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . dashboardHomeUrl());
    exit;
}

$error = flash('error') ?? '';
if (!$error && !empty($_SESSION['force_relogin_msg'])) {
    $error = $_SESSION['force_relogin_msg'];
    unset($_SESSION['force_relogin_msg']);
}

if (isset($_GET['google'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . googleAuthUrl($state));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } else {
        $result = loginUser($email, $password);

        if (is_array($result)) {
            createSystemNotification('system', 'You logged in successfully.', null, null, (int)$result['id']);
            header('Location: ' . dashboardHomeUrl());
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
</head>
<body>
<div class="auth-split">
    <div class="auth-split-form">
        <div class="auth-split-form-inner fade-in">
            <a href="<?= url('login.php') ?>" class="auth-brand">
                <?php if (is_file(__DIR__ . '/assets/logo.png')): ?>
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="auth-brand-img">
                <?php else: ?>
                <span class="auth-brand-mark">🐾</span>
                <?php endif; ?>
                <span class="auth-brand-name">BantayPurrPaws</span>
            </a>

            <div class="auth-welcome">
                <h1>Hi there, great to see you</h1>
                <p>Sign in to report strays, adopt pets, and stay updated.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
            <?php endif; ?>

            <a href="?google=1" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/><path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
                Sign in with Google
            </a>

            <div class="auth-divider"><span>or sign in with email</span></div>

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

            <form method="POST" action="" id="loginForm" class="auth-form">
                <div class="mfa-panel active" id="panel1">
                    <div class="form-group">
                        <label class="form-label" for="email">Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="email@example.com"
                               value="<?= sanitize($_POST['email'] ?? '') ?>"
                               required autocomplete="email">
                    </div>
                    <button type="button" id="btnIssueOtp" class="btn btn-primary btn-block">Send OTP Code</button>
                </div>

                <div class="mfa-panel" id="panel2">
                    <p class="auth-hint">
                        A 6-digit code was sent to <strong id="emailDisplay"></strong>
                    </p>
                    <div class="form-group">
                        <label class="form-label" for="otp">One-Time Password <span class="req">*</span></label>
                        <input type="text" id="otp" name="otp" class="form-control otp-input"
                               placeholder="000000" maxlength="6" autocomplete="one-time-code">
                    </div>
                    <button type="button" id="btnVerifyOtp" class="btn btn-primary btn-block">Verify Code</button>
                    <button type="button" id="btnBackToEmail" class="btn btn-ghost btn-block">← Back</button>
                </div>

                <input type="password" id="hiddenPassword" name="password" style="display:none;" autocomplete="current-password" data-no-pw-toggle="1">
                <button type="submit" id="finalSubmit" style="display:none;">Sign In</button>
            </form>

            <div id="pwModal" class="auth-modal">
                <div class="auth-modal-content">
                    <div class="mfa-badge">✓ Email verified</div>
                    <h3>Enter your password</h3>
                    <div class="form-group">
                        <label class="form-label" for="modal_password">Password <span class="req">*</span></label>
                        <input type="password" id="modal_password" class="form-control"
                               placeholder="Your account password" autocomplete="current-password">
                    </div>
                    <div class="auth-modal-actions">
                        <a href="<?= url('forgot-password.php') ?>" class="auth-link">Forgot password?</a>
                        <div class="auth-modal-btns">
                            <button type="button" id="btnCancelModal" class="btn btn-ghost">Cancel</button>
                            <button type="button" id="btnModalLogin" class="btn btn-primary">Log In</button>
                        </div>
                    </div>
                </div>
            </div>

            <p class="auth-footer">
                Don't have an account? <a href="<?= url('register.php') ?>">Sign Up</a>
            </p>
        </div>
    </div>

    <div class="auth-split-visual" aria-hidden="true">
        <img src="<?= url('assets/dog.jpg') ?>" alt="" class="auth-split-image">
        <div class="auth-split-overlay"></div>
        <blockquote class="auth-testimonial">
            <p>"Simply all the tools that my team and I need."</p>
            <cite>— BantayPurrPaws Community</cite>
        </blockquote>
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
                body: new URLSearchParams({ action: 'issue', email: e, purpose: 'login' })
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
                body: new URLSearchParams({ action: 'verify', email: e, code: c, purpose: 'login' })
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
        emailInput.value = '';
        otpInput.value = '';
        setStep(1);
        emailInput.focus();
    });

    btnModalLogin.addEventListener('click', function() {
        const pwd = modalPwd.value;
        if (!pwd) { alert('Please enter your password.'); return; }
        hiddenPwd.value = pwd;
        pwModal.classList.remove('visible');
        loginForm.submit();
    });

    otpInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnVerify.click();
    });

    modalPwd.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnModalLogin.click();
    });
})();
</script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>
